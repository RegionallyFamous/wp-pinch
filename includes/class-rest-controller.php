<?php
/**
 * REST Controller — chat proxy and status endpoints.
 *
 * POST /wp-json/wp-pinch/v1/chat  — forwards message to OpenClaw.
 * GET  /wp-json/wp-pinch/v1/status — pings the OpenClaw gateway.
 *
 * Both require edit_posts capability and are rate-limited.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * REST API endpoints for the chat block and status checks.
 */
class Rest_Controller {

	/**
	 * Default rate limit: requests per minute per user.
	 * Overridden by the wp_pinch_rate_limit option when set.
	 */
	const DEFAULT_RATE_LIMIT = 10;

	/**
	 * Maximum allowed message length in characters.
	 */
	const MAX_MESSAGE_LENGTH = 4000;

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_security_headers' ), 10, 3 );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'wp-pinch/v1',
			'/chat',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_chat' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'message'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $value ) {
								if ( ! is_string( $value ) || '' === trim( $value ) ) {
									return new \WP_Error(
										'rest_invalid_param',
										__( 'Message cannot be empty.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								if ( mb_strlen( $value ) > self::MAX_MESSAGE_LENGTH ) {
									return new \WP_Error(
										'rest_invalid_param',
										__( 'Message is too long.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								return true;
							},
						),
						'session_key' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				'schema' => array( __CLASS__, 'get_chat_schema' ),
			)
		);

		register_rest_route(
			'wp-pinch/v1',
			'/status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_status' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				'schema' => array( __CLASS__, 'get_status_schema' ),
			)
		);

		// Public lightweight health check (no auth required).
		register_rest_route(
			'wp-pinch/v1',
			'/health',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_health' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// SSE streaming chat endpoint.
		if ( Feature_Flags::is_enabled( 'streaming_chat' ) ) {
			register_rest_route(
				'wp-pinch/v1',
				'/chat/stream',
				array(
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( __CLASS__, 'handle_chat_stream' ),
						'permission_callback' => array( __CLASS__, 'check_permission' ),
						'args'                => array(
							'message'     => array(
								'required'          => true,
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_text_field',
								'validate_callback' => function ( $value ) {
									if ( ! is_string( $value ) || '' === trim( $value ) ) {
										return new \WP_Error(
											'rest_invalid_param',
											__( 'Message cannot be empty.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									if ( mb_strlen( $value ) > self::MAX_MESSAGE_LENGTH ) {
										return new \WP_Error(
											'rest_invalid_param',
											__( 'Message is too long.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									return true;
								},
							),
							'session_key' => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_key',
							),
						),
					),
				)
			);
		}
	}

	// =========================================================================
	// Security Headers
	// =========================================================================

	/**
	 * Add security headers to WP Pinch REST responses.
	 *
	 * @param \WP_REST_Response $response Result to send to the client.
	 * @param \WP_REST_Server   $server   Server instance.
	 * @param \WP_REST_Request  $request  Request used to generate the response.
	 * @return \WP_REST_Response
	 */
	public static function add_security_headers( \WP_REST_Response $response, \WP_REST_Server $server, \WP_REST_Request $request ): \WP_REST_Response {
		$route = $request->get_route();

		// Only apply to our own endpoints.
		if ( 0 !== strpos( $route, '/wp-pinch/' ) ) {
			return $response;
		}

		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'X-Frame-Options', 'DENY' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );

		return $response;
	}

	// =========================================================================
	// REST Schemas
	// =========================================================================

	/**
	 * Schema for the /chat endpoint.
	 *
	 * @return array JSON Schema.
	 */
	public static function get_chat_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wp-pinch-chat',
			'type'       => 'object',
			'properties' => array(
				'reply'       => array(
					'description' => __( 'The AI assistant reply text.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'session_key' => array(
					'description' => __( 'Session key for conversation continuity.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Schema for the /status endpoint.
	 *
	 * @return array JSON Schema.
	 */
	public static function get_status_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wp-pinch-status',
			'type'       => 'object',
			'properties' => array(
				'plugin_version' => array(
					'description' => __( 'Current WP Pinch plugin version.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'configured'     => array(
					'description' => __( 'Whether the plugin is configured with gateway URL and API token.', 'wp-pinch' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'mcp_endpoint'   => array(
					'description' => __( 'The MCP server REST endpoint URL.', 'wp-pinch' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'gateway'        => array(
					'description' => __( 'Gateway connection status.', 'wp-pinch' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'connected' => array(
							'type' => 'boolean',
						),
						'status'    => array(
							'type' => 'integer',
						),
					),
				),
			),
		);
	}

	/**
	 * Permission callback — require edit_posts.
	 *
	 * @return bool|\WP_Error
	 */
	public static function check_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use WP Pinch.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle chat message — forward to OpenClaw.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_chat( \WP_REST_Request $request ) {
		if ( ! self::check_rate_limit() ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
				),
				429
			);
			$response->header( 'Retry-After', '60' );
			return $response;
		}

		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = get_option( 'wp_pinch_api_token', '' );

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WP Pinch is not configured. Please set your Gateway URL and API token in the WP Pinch settings.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		// Circuit breaker — fail fast if the gateway is known to be down.
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			$retry = Circuit_Breaker::get_retry_after();
			$response = new \WP_REST_Response(
				array(
					'code'    => 'gateway_unavailable',
					'message' => __( 'The AI gateway is temporarily unavailable. Please try again shortly.', 'wp-pinch' ),
				),
				503
			);
			$response->header( 'Retry-After', (string) max( 1, $retry ) );
			return $response;
		}

		$message = $request->get_param( 'message' );
		$user    = wp_get_current_user();

		// Always derive session key from the authenticated user to prevent cross-user session hijacking.
		$session_key = 'wp-pinch-chat-' . $user->ID;

		$payload = array(
			'message'    => $message,
			'sessionKey' => $session_key,
			'wakeMode'   => 'always',
			'channel'    => 'wp-pinch',
		);

		/**
		 * Filter the chat payload before sending to OpenClaw.
		 *
		 * @since 1.0.0
		 *
		 * @param array            $payload The chat payload.
		 * @param \WP_REST_Request $request The REST request.
		 */
		$payload = apply_filters( 'wp_pinch_chat_payload', $payload, $request );

		$response = wp_remote_post(
			trailingslashit( $gateway_url ) . 'hooks/agent',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Circuit_Breaker::record_failure();
			// Log the real error server-side; return a generic message to the client.
			Audit_Table::insert( 'gateway_error', 'chat', $response->get_error_message() );
			return new \WP_Error(
				'gateway_error',
				__( 'Unable to reach the AI gateway. Please try again later.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 ) {
			Circuit_Breaker::record_failure();
			return new \WP_Error(
				'gateway_error',
				/* translators: %d: HTTP status code returned by the OpenClaw gateway. */
				sprintf( __( 'OpenClaw returned HTTP %d.', 'wp-pinch' ), $status ),
				array( 'status' => 502 )
			);
		}

		Circuit_Breaker::record_success();

		// Only return expected string responses; never forward raw gateway body.
		$reply = $data['response'] ?? $data['message'] ?? null;
		$result = array(
			'reply'       => is_string( $reply ) ? wp_kses_post( $reply ) : __( 'Received an unexpected response from the gateway.', 'wp-pinch' ),
			'session_key' => $session_key,
		);

		/**
		 * Filter the chat response before returning to the client.
		 *
		 * @since 1.0.0
		 *
		 * @param array $result The response data.
		 * @param array $data   Raw OpenClaw response.
		 */
		$result = apply_filters( 'wp_pinch_chat_response', $result, $data );

		Audit_Table::insert(
			'chat_message',
			'chat',
			sprintf( 'Chat message from user #%d.', $user->ID ),
			array( 'user_id' => $user->ID )
		);

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle status check — ping the OpenClaw gateway.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function handle_status( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! self::check_rate_limit() ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
				),
				429
			);
			$response->header( 'Retry-After', '60' );
			return $response;
		}

		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = get_option( 'wp_pinch_api_token', '' );

		$result = array(
			'plugin_version' => WP_PINCH_VERSION,
			'configured'     => ! empty( $gateway_url ) && ! empty( $api_token ),
			'mcp_endpoint'   => rest_url( 'wp-pinch/mcp' ),
			'gateway'        => array(
				'connected' => false,
			),
		);

		// Only expose the gateway URL to administrators.
		if ( current_user_can( 'manage_options' ) ) {
			$result['gateway']['url'] = $gateway_url ? trailingslashit( $gateway_url ) : '';
		}

		if ( $result['configured'] ) {
			$response = wp_remote_get(
				trailingslashit( $gateway_url ) . 'api/v1/status',
				array(
					'timeout' => 5,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_token,
					),
				)
			);

			if ( ! is_wp_error( $response ) ) {
				$code                           = wp_remote_retrieve_response_code( $response );
				$result['gateway']['connected'] = ( $code >= 200 && $code < 300 );
				$result['gateway']['status']    = $code;
			}
		}

		return new \WP_REST_Response( $result, 200 );
	}

	// =========================================================================
	// Health Endpoint
	// =========================================================================

	/**
	 * Lightweight public health check — no authentication required.
	 *
	 * Returns plugin status, circuit breaker state, and basic config info.
	 * Does NOT expose credentials or sensitive data.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_health(): \WP_REST_Response {
		$configured = ! empty( get_option( 'wp_pinch_gateway_url', '' ) )
			&& ! empty( get_option( 'wp_pinch_api_token', '' ) );

		$circuit_state = Circuit_Breaker::get_state();
		$retry_after   = Circuit_Breaker::get_retry_after();

		$result = array(
			'status'    => 'ok',
			'version'   => WP_PINCH_VERSION,
			'configured' => $configured,
			'circuit'   => array(
				'state'       => $circuit_state,
				'retry_after' => $retry_after,
			),
			'timestamp' => gmdate( 'c' ),
		);

		$response = new \WP_REST_Response( $result, 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );

		return $response;
	}

	// =========================================================================
	// SSE Streaming
	// =========================================================================

	/**
	 * Handle chat message via Server-Sent Events streaming.
	 *
	 * Sends a request to the gateway and streams the response back
	 * to the client as SSE events. Falls back to buffered response
	 * if the gateway doesn't support streaming.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_chat_stream( \WP_REST_Request $request ) {
		if ( ! self::check_rate_limit() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
				),
				429
			);
		}

		// Circuit breaker check.
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			$retry = Circuit_Breaker::get_retry_after();
			$response = new \WP_REST_Response(
				array(
					'code'    => 'gateway_unavailable',
					'message' => __( 'The AI gateway is temporarily unavailable.', 'wp-pinch' ),
				),
				503
			);
			$response->header( 'Retry-After', (string) max( 1, $retry ) );
			return $response;
		}

		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = get_option( 'wp_pinch_api_token', '' );

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WP Pinch is not configured.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		$message     = $request->get_param( 'message' );
		$user        = wp_get_current_user();
		$session_key = 'wp-pinch-chat-' . $user->ID;

		$payload = array(
			'message'    => $message,
			'sessionKey' => $session_key,
			'wakeMode'   => 'always',
			'channel'    => 'wp-pinch',
			'stream'     => true,
		);

		/** This filter is documented in class-rest-controller.php */
		$payload = apply_filters( 'wp_pinch_chat_payload', $payload, $request );

		// Set SSE headers and flush.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' ); // Disable nginx buffering.

		if ( ob_get_level() ) {
			ob_end_flush();
		}

		// Make a blocking request to the gateway.
		$response = wp_remote_post(
			trailingslashit( $gateway_url ) . 'hooks/agent',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
					'Accept'        => 'text/event-stream',
				),
				'body' => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Circuit_Breaker::record_failure();
			echo "event: error\n";
			echo 'data: ' . wp_json_encode( array( 'message' => __( 'Unable to reach the AI gateway.', 'wp-pinch' ) ) ) . "\n\n";
			echo "event: done\ndata: {}\n\n";
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			exit;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 300 ) {
			Circuit_Breaker::record_failure();
			echo "event: error\n";
			echo 'data: ' . wp_json_encode( array( 'message' => __( 'Gateway returned an error.', 'wp-pinch' ) ) ) . "\n\n";
			echo "event: done\ndata: {}\n\n";
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			exit;
		}

		Circuit_Breaker::record_success();

		// Send the response as a single SSE message (gateway may not stream back).
		$data  = json_decode( $body, true );
		$reply = $data['response'] ?? $data['message'] ?? '';
		$reply = is_string( $reply ) ? wp_kses_post( $reply ) : '';

		echo "event: message\n";
		echo 'data: ' . wp_json_encode(
			array(
				'reply'       => $reply,
				'session_key' => $session_key,
			)
		) . "\n\n";

		echo "event: done\ndata: {}\n\n";

		flush();

		Audit_Table::insert(
			'chat_message',
			'chat',
			sprintf( 'Streamed chat message from user #%d.', $user->ID ),
			array( 'user_id' => $user->ID )
		);

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		exit;
	}

	// =========================================================================
	// Rate Limiting
	// =========================================================================

	/**
	 * Per-user rate limiting for REST endpoints.
	 *
	 * Uses wp_cache (object cache) when a persistent backend is available
	 * for better performance and atomic increments. Falls back to transients.
	 *
	 * @return bool
	 */
	private static function check_rate_limit(): bool {
		$user_id = get_current_user_id();
		$key     = 'wp_pinch_rest_rate_' . $user_id;
		$limit   = max( 1, (int) get_option( 'wp_pinch_rate_limit', self::DEFAULT_RATE_LIMIT ) );

		// Prefer object cache when a persistent backend (Redis, Memcached) is available.
		if ( wp_using_ext_object_cache() ) {
			$count = (int) wp_cache_get( $key, 'wp-pinch' );

			if ( $count >= $limit ) {
				return false;
			}

			if ( 0 === $count ) {
				wp_cache_set( $key, 1, 'wp-pinch', 60 );
			} else {
				wp_cache_incr( $key, 1, 'wp-pinch' );
			}

			return true;
		}

		// Fallback to transients.
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, 60 );
		} else {
			$option_key = '_transient_' . $key;
			update_option( $option_key, $count + 1, false );
		}

		return true;
	}
}
