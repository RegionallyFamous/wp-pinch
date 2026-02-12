<?php
/**
 * Core plugin singleton — activation, deactivation, dependency checks.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin orchestrator.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether required dependencies are available.
	 *
	 * @var bool
	 */
	private bool $dependencies_met = true;

	/**
	 * Missing dependency messages.
	 *
	 * @var string[]
	 */
	private array $missing = array();

	/**
	 * Return the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — wires hooks.
	 */
	private function __construct() {
		register_activation_hook( WP_PINCH_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( WP_PINCH_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
	}

	/**
	 * Activation callback.
	 *
	 * Creates the audit log table and stores the plugin version.
	 */
	public function activate(): void {
		Audit_Table::create_table();
		update_option( 'wp_pinch_version', WP_PINCH_VERSION );

		/**
		 * Fires after WP Pinch activation.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wp_pinch_activated' );
	}

	/**
	 * Deactivation callback.
	 *
	 * Unschedules all Action Scheduler recurring jobs.
	 */
	public function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			$hooks = array(
				'wp_pinch_governance_content_freshness',
				'wp_pinch_governance_seo_health',
				'wp_pinch_governance_comment_sweep',
				'wp_pinch_governance_broken_links',
				'wp_pinch_governance_security_scan',
				'wp_pinch_audit_cleanup',
				'wp_pinch_retry_webhook',
			);
			foreach ( $hooks as $hook ) {
				as_unschedule_all_actions( $hook );
			}
		}

		/**
		 * Fires after WP Pinch deactivation.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wp_pinch_deactivated' );
	}

	/**
	 * Boot the plugin after all plugins are loaded.
	 *
	 * Checks dependencies, then initialises each subsystem.
	 */
	public function boot(): void {
		$this->check_dependencies();

		if ( ! $this->dependencies_met ) {
			add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
			return;
		}

		// Run upgrade routine if version has changed (register_activation_hook doesn't fire on updates).
		$this->maybe_upgrade();

		// Initialize subsystems.
		Audit_Table::init();
		MCP_Server::init();
		Abilities::init();
		Webhook_Dispatcher::init();
		Governance::init();
		Rest_Controller::init();
		Privacy::init();
		Site_Health::init();

		if ( is_admin() ) {
			Settings::init();
			add_action( 'admin_notices', array( $this, 'configuration_notices' ) );
			add_action( 'admin_notices', array( $this, 'circuit_breaker_notice' ) );
		}

		// Load translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Register blocks.
		add_action( 'init', array( $this, 'register_blocks' ) );

		/**
		 * Fires after all WP Pinch subsystems are initialised.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wp_pinch_loaded' );
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wp-pinch', false, dirname( plugin_basename( WP_PINCH_FILE ) ) . '/languages' );
	}

	/**
	 * Register Gutenberg blocks.
	 */
	public function register_blocks(): void {
		$block_dir = WP_PINCH_DIR . 'build/blocks/pinch-chat';

		if ( file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir );
		}
	}

	/**
	 * Verify that the Abilities API and MCP Adapter are available.
	 */
	private function check_dependencies(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->dependencies_met = false;
			$this->missing[]        = __( 'WordPress Abilities API (requires WordPress 6.9+)', 'wp-pinch' );
		}

		// MCP Adapter is recommended but not hard-required — abilities still work without it.
		// Presence is checked at runtime via MCP_Server::init() which safely no-ops.
	}

	/**
	 * Render an admin notice about missing dependencies.
	 */
	public function dependency_notice(): void {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>' . esc_html__( 'WP Pinch:', 'wp-pinch' ) . '</strong> ';
		echo esc_html__( 'The following dependencies are missing:', 'wp-pinch' );
		echo '<ul style="list-style:disc;margin-left:1.5em;">';
		foreach ( $this->missing as $msg ) {
			echo '<li>' . esc_html( $msg ) . '</li>';
		}
		echo '</ul></p></div>';
	}

	// =========================================================================
	// Database Migration System
	// =========================================================================

	/**
	 * Run upgrade routines when the plugin version changes.
	 *
	 * Compares stored DB version with current version and runs any
	 * necessary migration callbacks sequentially. Each migration only
	 * runs once, even when jumping multiple versions.
	 */
	private function maybe_upgrade(): void {
		$stored_version = get_option( 'wp_pinch_version', '' );

		if ( WP_PINCH_VERSION === $stored_version ) {
			return;
		}

		// Always run dbDelta to ensure table schema is current.
		Audit_Table::create_table();

		// Version-specific migrations — add new entries below for each release.
		$migrations = array(
			'1.1.0' => array( $this, 'migrate_1_1_0' ),
		);

		foreach ( $migrations as $version => $callback ) {
			if ( version_compare( $stored_version, $version, '<' ) ) {
				call_user_func( $callback );
			}
		}

		update_option( 'wp_pinch_version', WP_PINCH_VERSION );
	}

	/**
	 * Migration for v1.1.0.
	 *
	 * - Ensures new options exist with sensible defaults.
	 * - Clears stale transient caches from pre-1.1.0 ability format.
	 */
	private function migrate_1_1_0(): void {
		// Ensure governance mode default is set.
		if ( false === get_option( 'wp_pinch_governance_mode' ) ) {
			update_option( 'wp_pinch_governance_mode', 'webhook', false );
		}

		// Ensure rate limit default is set.
		if ( false === get_option( 'wp_pinch_rate_limit' ) ) {
			update_option( 'wp_pinch_rate_limit', 30, false );
		}

		// Clear stale ability caches so new abilities are picked up.
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_wp_pinch_' ) . '%'
			)
		);
	}

	// =========================================================================
	// Configuration Notices
	// =========================================================================

	/**
	 * Show dismissible admin notices for incomplete configuration.
	 *
	 * Only shown on WP Pinch settings page and the main dashboard.
	 */
	public function configuration_notices(): void {
		// Only show on relevant screens.
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'toplevel_page_wp-pinch', 'dashboard' ), true ) ) {
			return;
		}

		// Don't show if the user dismissed it.
		$dismissed = get_user_meta( get_current_user_id(), 'wp_pinch_dismissed_config_notice', true );
		if ( $dismissed && version_compare( $dismissed, WP_PINCH_VERSION, '>=' ) ) {
			return;
		}

		// Handle dismissal via AJAX-free approach (query param).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wp_pinch_dismiss_notice'] ) && '1' === $_GET['wp_pinch_dismiss_notice'] ) {
			if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'wp_pinch_dismiss_notice' ) ) {
				update_user_meta( get_current_user_id(), 'wp_pinch_dismissed_config_notice', WP_PINCH_VERSION );
				return;
			}
		}

		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = get_option( 'wp_pinch_api_token', '' );

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			$settings_url = admin_url( 'admin.php?page=wp-pinch' );
			$dismiss_url  = wp_nonce_url(
				add_query_arg( 'wp_pinch_dismiss_notice', '1' ),
				'wp_pinch_dismiss_notice'
			);

			printf(
				'<div class="notice notice-warning is-dismissible" data-wp-pinch-dismiss="%s"><p>',
				esc_url( $dismiss_url )
			);
			printf(
				'<strong>%s</strong> %s <a href="%s">%s</a>',
				esc_html__( 'WP Pinch:', 'wp-pinch' ),
				esc_html__( 'The gateway URL and API token are not configured. AI chat, webhooks, and governance features will not work.', 'wp-pinch' ),
				esc_url( $settings_url ),
				esc_html__( 'Configure now &rarr;', 'wp-pinch' )
			);
			echo '</p></div>';
		}
	}

	/**
	 * Show an admin notice when the circuit breaker is open.
	 *
	 * Displayed to administrators on all admin pages so they're aware
	 * that the AI gateway is unreachable and chat is temporarily disabled.
	 */
	public function circuit_breaker_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Feature_Flags::is_enabled( 'circuit_breaker' ) ) {
			return;
		}

		$state = Circuit_Breaker::get_state();

		if ( Circuit_Breaker::STATE_OPEN !== $state && Circuit_Breaker::STATE_HALF_OPEN !== $state ) {
			return;
		}

		$retry_after  = Circuit_Breaker::get_retry_after();
		$features_url = admin_url( 'admin.php?page=wp-pinch&tab=features' );

		echo '<div class="notice notice-error"><p>';
		printf(
			'<strong>%s</strong> ',
			esc_html__( 'WP Pinch:', 'wp-pinch' )
		);

		if ( Circuit_Breaker::STATE_OPEN === $state ) {
			printf(
				/* translators: 1: seconds until retry, 2: URL to features tab */
				esc_html__( 'The AI gateway is unreachable. Chat requests are failing fast to protect performance. The circuit will probe again in %1$d seconds. %2$s', 'wp-pinch' ),
				absint( $retry_after ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $features_url ),
					esc_html__( 'View status &rarr;', 'wp-pinch' )
				)
			);
		} else {
			printf(
				/* translators: %s: URL to features tab */
				esc_html__( 'The AI gateway is being probed after an outage. The next request will determine if the connection has recovered. %s', 'wp-pinch' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $features_url ),
					esc_html__( 'View status &rarr;', 'wp-pinch' )
				)
			);
		}

		echo '</p></div>';
	}
}
