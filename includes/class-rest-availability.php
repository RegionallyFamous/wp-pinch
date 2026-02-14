<?php
/**
 * REST API availability check.
 *
 * Detects when the REST API is disabled or blocked (e.g. by Disable REST API
 * plugin, security plugins, or hosting WAF) and shows an admin notice.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * REST API availability checker.
 */
class Rest_Availability {

	const TRANSIENT_KEY  = 'wp_pinch_rest_available';
	const CHECK_INTERVAL = 300; // 5 minutes.

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'handle_dismiss' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_check' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'show_notice' ) );
	}

	/**
	 * Run availability check periodically.
	 */
	public static function maybe_check(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'toplevel_page_wp-pinch' ), true ) ) {
			return;
		}

		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return;
		}

		$available = self::check();
		set_transient( self::TRANSIENT_KEY, $available ? 'yes' : 'no', self::CHECK_INTERVAL );
	}

	/**
	 * Check if the REST API is reachable.
	 *
	 * @return bool True if the health endpoint returns 200.
	 */
	public static function check(): bool {
		$url = rest_url( 'wp-pinch/v1/health' );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 5,
				'sslverify' => is_ssl(),
				'blocking'  => true,
				'cookies'   => array(),
				'headers'   => array( 'X-WP-Pinch-Check' => '1' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Show admin notice when REST API appears disabled.
	 */
	public static function show_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$available = get_transient( self::TRANSIENT_KEY );
		if ( 'no' !== $available ) {
			return;
		}

		$dismissed = get_user_meta( get_current_user_id(), 'wp_pinch_rest_unavailable_dismissed', true );
		if ( $dismissed && version_compare( $dismissed, WP_PINCH_VERSION, '>=' ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg(
				array(
					'wp_pinch_dismiss_rest_notice' => '1',
				),
				admin_url()
			),
			'wp_pinch_dismiss_rest_notice'
		);

		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'WP Pinch:', 'wp-pinch' ); ?></strong>
				<?php
				echo esc_html__(
					'The WordPress REST API appears to be disabled or blocked. WP Pinch requires the REST API for MCP, chat, webhooks, and ability execution. Check for plugins like "Disable REST API" or security plugins that block the REST API. Exclude /wp-json/ from any WAF or caching rules.',
					'wp-pinch'
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-pinch' ) ); ?>"><?php esc_html_e( 'WP Pinch Settings', 'wp-pinch' ); ?></a>
				|
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'wp-pinch' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle dismiss action.
	 */
	public static function handle_dismiss(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['wp_pinch_dismiss_rest_notice'] ) || '1' !== $_GET['wp_pinch_dismiss_rest_notice'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'wp_pinch_dismiss_rest_notice' ) ) {
			return;
		}
		update_user_meta( get_current_user_id(), 'wp_pinch_rest_unavailable_dismissed', WP_PINCH_VERSION );
		wp_safe_redirect( remove_query_arg( array( 'wp_pinch_dismiss_rest_notice', '_wpnonce' ) ) );
		exit;
	}
}
