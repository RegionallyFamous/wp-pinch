<?php
/**
 * Webhook Dispatcher — sends events to OpenClaw via HTTP.
 *
 * Features:
 * - Listens to configurable WordPress hooks.
 * - Async dispatch via wp_remote_post with non-blocking.
 * - Failed webhook retry via Action Scheduler with exponential backoff.
 * - Rate limiting (default 30/min).
 * - All payloads filterable.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatches webhooks to the OpenClaw gateway.
 */
class Webhook_Dispatcher {

	/**
	 * Rate limit window in seconds.
	 */
	const RATE_WINDOW = 60;

	/**
	 * Retry schedule intervals in seconds (exponential backoff).
	 * 5 min → 30 min → 2 hours → 12 hours.
	 *
	 * @var int[]
	 */
	const RETRY_INTERVALS = array( 300, 1800, 7200, 43200 );

	/**
	 * Maximum retry attempts.
	 */
	const MAX_RETRIES = 4;

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'transition_post_status', array( __CLASS__, 'on_post_status_change' ), 10, 3 );
		add_action( 'wp_insert_comment', array( __CLASS__, 'on_new_comment' ), 10, 2 );
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_woo_order_change' ), 10, 3 );
		add_action( 'delete_post', array( __CLASS__, 'on_post_delete' ) );

		// Retry handler.
		add_action( 'wp_pinch_retry_webhook', array( __CLASS__, 'execute_retry' ), 10, 4 );
	}

	/**
	 * Post status transition webhook.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public static function on_post_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( ! self::is_event_enabled( 'post_status_change' ) ) {
			return;
		}

		// Only fire on meaningful transitions.
		if ( $new_status === $old_status ) {
			return;
		}

		self::dispatch(
			'post_status_change',
			sprintf( 'Post "%s" changed from %s to %s.', $post->post_title, $old_status, $new_status ),
			array(
				'post_id'    => $post->ID,
				'post_title' => $post->post_title,
				'post_type'  => $post->post_type,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'url'        => get_permalink( $post->ID ),
				'author'     => get_the_author_meta( 'display_name', (int) $post->post_author ),
			)
		);
	}

	/**
	 * New comment webhook.
	 *
	 * @param int         $comment_id Comment ID.
	 * @param \WP_Comment $comment    Comment object.
	 */
	public static function on_new_comment( int $comment_id, \WP_Comment $comment ): void {
		if ( ! self::is_event_enabled( 'new_comment' ) ) {
			return;
		}

		self::dispatch(
			'new_comment',
			sprintf( 'New comment by %s on post #%d.', $comment->comment_author, $comment->comment_post_ID ),
			array(
				'comment_id' => $comment_id,
				'post_id'    => (int) $comment->comment_post_ID,
				'author'     => $comment->comment_author,
				'content'    => wp_trim_words( $comment->comment_content, 50 ),
				'status'     => wp_get_comment_status( $comment ),
			)
		);
	}

	/**
	 * User registration webhook.
	 *
	 * @param int $user_id User ID.
	 */
	public static function on_user_register( int $user_id ): void {
		if ( ! self::is_event_enabled( 'user_register' ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::dispatch(
			'user_register',
			sprintf( 'New user registered: %s.', $user->display_name ),
			array(
				'user_id'      => $user_id,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'roles'        => $user->roles,
			)
		);
	}

	/**
	 * WooCommerce order status change webhook.
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $old       Old status.
	 * @param string $new       New status.
	 */
	public static function on_woo_order_change( int $order_id, string $old, string $new ): void {
		if ( ! self::is_event_enabled( 'woo_order_change' ) ) {
			return;
		}

		self::dispatch(
			'woo_order_change',
			sprintf( 'WooCommerce order #%d changed from %s to %s.', $order_id, $old, $new ),
			array(
				'order_id'   => $order_id,
				'old_status' => $old,
				'new_status' => $new,
			)
		);
	}

	/**
	 * Post deletion webhook.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_post_delete( int $post_id ): void {
		if ( ! self::is_event_enabled( 'post_delete' ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || wp_is_post_revision( $post ) ) {
			return;
		}

		self::dispatch(
			'post_delete',
			sprintf( 'Post "%s" (#%d) was deleted.', $post->post_title, $post_id ),
			array(
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
				'post_type'  => $post->post_type,
			)
		);
	}

	// =========================================================================
	// Core dispatch logic
	// =========================================================================

	/**
	 * Dispatch a webhook to the OpenClaw gateway.
	 *
	 * @param string $event   Event name.
	 * @param string $message Human-readable message for the agent.
	 * @param array  $data    Event data.
	 * @param int    $attempt Current retry attempt (0 = first try).
	 * @return bool
	 */
	public static function dispatch( string $event, string $message, array $data = array(), int $attempt = 0 ): bool {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = get_option( 'wp_pinch_api_token', '' );

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return false;
		}

		// Rate limiting.
		if ( ! self::check_rate_limit() ) {
			Audit_Table::insert( 'webhook_rate_limited', 'webhook', sprintf( 'Webhook "%s" dropped — rate limit exceeded.', $event ) );
			return false;
		}

		/**
		 * Fires before a webhook is dispatched.
		 *
		 * @since 1.0.0
		 *
		 * @param string $event   Event name.
		 * @param string $message Human-readable message.
		 * @param array  $data    Event data.
		 */
		do_action( 'wp_pinch_before_webhook', $event, $message, $data );

		$payload = array(
			'message'    => sprintf( '[WordPress – %s] %s', $event, $message ),
			'sessionKey' => 'wp-pinch-' . sanitize_key( $event ),
			'wakeMode'   => 'always',
			'channel'    => 'wp-pinch',
			'metadata'   => array(
				'event'     => $event,
				'site_url'  => home_url(),
				'timestamp' => gmdate( 'c' ),
				'data'      => $data,
			),
		);

		/**
		 * Filter the webhook payload before sending.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $payload The webhook payload.
		 * @param string $event   Event name.
		 * @param array  $data    Event data.
		 */
		$payload = apply_filters( 'wp_pinch_webhook_payload', $payload, $event, $data );

		$webhook_url = trailingslashit( $gateway_url ) . 'hooks/agent';

		$is_blocking = ( $attempt > 0 );

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout'   => 5,
				'blocking'  => $is_blocking, // Non-blocking for initial sends, blocking for retries (Action Scheduler).
				'headers'   => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'      => wp_json_encode( $payload ),
				'sslverify' => true,
			)
		);

		// Non-blocking requests return immediately — we cannot inspect the response.
		// Schedule a verification retry so events are not silently lost if the gateway is down.
		if ( ! $is_blocking ) {
			if ( is_wp_error( $response ) ) {
				Audit_Table::insert(
					'webhook_failed',
					'webhook',
					sprintf( 'Webhook "%s" dispatch error: %s', $event, $response->get_error_message() ),
					array(
						'event' => $event,
						'error' => $response->get_error_message(),
					)
				);

				// Schedule a blocking retry so we don't lose the event.
				if ( function_exists( 'as_schedule_single_action' ) ) {
					try {
						as_schedule_single_action(
							time() + self::RETRY_INTERVALS[0],
							'wp_pinch_retry_webhook',
							array( $event, $message, $data, 1 ),
							'wp-pinch'
						);
					} catch ( \Throwable $e ) {
						Audit_Table::insert(
							'scheduler_error',
							'webhook',
							sprintf( 'Failed to schedule webhook retry: %s', $e->getMessage() ),
							array(
								'event' => $event,
								'error' => $e->getMessage(),
							)
						);
					}
				}

				return false;
			}

			Audit_Table::insert(
				'webhook_sent',
				'webhook',
				sprintf( 'Webhook "%s" dispatched (non-blocking).', $event ),
				array( 'event' => $event )
			);

			self::increment_rate_counter();
			return true;
		}

		// Blocking request — check the actual response.
		$is_error = is_wp_error( $response );
		$status   = $is_error ? 0 : wp_remote_retrieve_response_code( $response );
		$success  = ! $is_error && $status >= 200 && $status < 300;

		if ( $success ) {
			Audit_Table::insert(
				'webhook_sent',
				'webhook',
				sprintf( 'Webhook "%s" delivered (attempt %d).', $event, $attempt + 1 ),
				array(
					'event'  => $event,
					'status' => $status,
				)
			);

			self::increment_rate_counter();
			return true;
		}

		// Schedule retry.
		$error_msg = $is_error ? $response->get_error_message() : "HTTP {$status}";

		Audit_Table::insert(
			'webhook_failed',
			'webhook',
			sprintf( 'Webhook "%s" failed (attempt %d): %s', $event, $attempt + 1, $error_msg ),
			array(
				'event'   => $event,
				'attempt' => $attempt,
				'error'   => $error_msg,
			)
		);

		if ( $attempt < self::MAX_RETRIES && function_exists( 'as_schedule_single_action' ) ) {
			$intervals = self::RETRY_INTERVALS;
			$delay     = $intervals[ $attempt ] ?? $intervals[ count( $intervals ) - 1 ];

			try {
				as_schedule_single_action(
					time() + $delay,
					'wp_pinch_retry_webhook',
					array( $event, $message, $data, $attempt + 1 ),
					'wp-pinch'
				);
			} catch ( \Throwable $e ) {
				Audit_Table::insert(
					'scheduler_error',
					'webhook',
					sprintf( 'Failed to schedule webhook retry (attempt %d): %s', $attempt + 1, $e->getMessage() ),
					array(
						'event'   => $event,
						'attempt' => $attempt + 1,
						'error'   => $e->getMessage(),
					)
				);
			}
		}

		return false;
	}

	/**
	 * Execute a retry from Action Scheduler.
	 *
	 * @param string $event   Event name.
	 * @param string $message Human-readable message.
	 * @param array  $data    Event data.
	 * @param int    $attempt Retry attempt number.
	 */
	public static function execute_retry( string $event, string $message, array $data, int $attempt ): void {
		self::dispatch( $event, $message, $data, $attempt );
	}

	// =========================================================================
	// Rate limiting
	// =========================================================================

	/**
	 * Check whether we're within the rate limit.
	 *
	 * @return bool
	 */
	private static function check_rate_limit(): bool {
		$max     = (int) get_option( 'wp_pinch_rate_limit', 30 );
		$counter = (int) get_transient( 'wp_pinch_webhook_counter' );

		return $counter < $max;
	}

	/**
	 * Increment the rate-limit counter.
	 *
	 * Only sets the transient expiry on the first request in a window
	 * so the window is fixed-duration rather than sliding.
	 */
	private static function increment_rate_counter(): void {
		$key     = 'wp_pinch_webhook_counter';
		$counter = (int) get_transient( $key );

		if ( 0 === $counter ) {
			// First request in this window — set with expiry.
			set_transient( $key, 1, self::RATE_WINDOW );
		} else {
			// Subsequent requests — increment without resetting expiry.
			// Use the options API directly to preserve the existing TTL.
			$option_key    = '_transient_' . $key;
			$current_value = get_option( $option_key, 0 );
			update_option( $option_key, (int) $current_value + 1, false );
		}
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Check whether a webhook event is enabled.
	 *
	 * @param string $event Event name.
	 * @return bool
	 */
	private static function is_event_enabled( string $event ): bool {
		$enabled = get_option( 'wp_pinch_webhook_events', array() );

		// If no events configured, default all to enabled.
		if ( empty( $enabled ) ) {
			return true;
		}

		return in_array( $event, $enabled, true );
	}

	/**
	 * Get all available webhook events.
	 *
	 * @return array<string, string> event_name => label.
	 */
	public static function get_available_events(): array {
		return array(
			'post_status_change' => __( 'Post status change (publish, draft, etc.)', 'wp-pinch' ),
			'new_comment'        => __( 'New comment posted', 'wp-pinch' ),
			'user_register'      => __( 'New user registration', 'wp-pinch' ),
			'woo_order_change'   => __( 'WooCommerce order status change', 'wp-pinch' ),
			'post_delete'        => __( 'Post deleted', 'wp-pinch' ),
			'governance_finding' => __( 'Governance finding reported', 'wp-pinch' ),
		);
	}
}
