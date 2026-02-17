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
		update_option( 'wp_pinch_activation_redirect', true );

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
				'wp_pinch_governance_draft_necromancer',
				'wp_pinch_governance_spaced_resurfacing',
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
	 * Whether the WP Pinch API is disabled (kill switch).
	 *
	 * Checks WP_PINCH_DISABLED constant first, then wp_pinch_api_disabled option.
	 *
	 * @return bool
	 */
	public static function is_api_disabled(): bool {
		if ( defined( 'WP_PINCH_DISABLED' ) && WP_PINCH_DISABLED ) {
			return true;
		}
		return (bool) get_option( 'wp_pinch_api_disabled', false );
	}

	/**
	 * Whether Action Scheduler is available (optional dependency for governance, webhook retry, audit cleanup).
	 *
	 * @return bool
	 */
	public static function has_action_scheduler(): bool {
		return function_exists( 'as_has_scheduled_action' );
	}

	/**
	 * Whether read-only mode is active (blocks all write abilities).
	 *
	 * Checks WP_PINCH_READ_ONLY constant first, then wp_pinch_read_only_mode option.
	 *
	 * @return bool
	 */
	public static function is_read_only_mode(): bool {
		if ( defined( 'WP_PINCH_READ_ONLY' ) && WP_PINCH_READ_ONLY ) {
			return true;
		}
		return (bool) get_option( 'wp_pinch_read_only_mode', false );
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
		OpenClaw_Role::init();
		MCP_Server::init();
		Abilities::init();
		Webhook_Dispatcher::init();
		Governance::init();
		Rest_Controller::init();
		Privacy::init();
		Site_Health::init();

		if ( is_admin() ) {
			Settings::init();
			Approval_Queue::init();
			Rest_Availability::init();
			Dashboard_Widget::init();
			add_action( 'admin_notices', array( $this, 'configuration_notices' ) );
			add_action( 'admin_notices', array( $this, 'action_scheduler_notice' ) );
			add_action( 'admin_notices', array( $this, 'circuit_breaker_notice' ) );
		}

		// Register blocks.
		add_action( 'init', array( $this, 'register_blocks' ) );

		// Block Bindings (agentId, placeholder bindable to post meta / options).
		Block_Bindings::init();

		// Allow themes/plugins to customize block registration.
		add_filter( 'register_block_type_args', array( $this, 'filter_block_type_args' ), 10, 2 );

		/**
		 * Fires after all WP Pinch subsystems are initialised.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wp_pinch_loaded' );
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
	 * Filter block type registration args (enables wp_pinch_block_type_metadata).
	 *
	 * @param array  $args       Block type arguments.
	 * @param string $block_type Block type name.
	 * @return array
	 */
	public function filter_block_type_args( array $args, string $block_type ): array {
		if ( 'wp-pinch/chat' === $block_type ) {
			$args = apply_filters( 'wp_pinch_block_type_metadata', $args, $block_type );
		}
		return $args;
	}

	/**
	 * Invalidate ability cache when content changes (save_post / deleted_post).
	 */
	public function invalidate_ability_cache_on_post_change(): void {
		Abilities::invalidate_ability_cache();
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
			'2.5.0' => array( $this, 'migrate_2_5_0' ),
			'2.6.0' => array( $this, 'migrate_2_6_0' ),
			'2.7.0' => array( $this, 'migrate_2_7_0' ),
			'3.0.0' => array( $this, 'migrate_3_0_0' ),
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
		$like  = $wpdb->esc_like( '_transient_wp_pinch_' ) . '%';
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);
		foreach ( (array) $names as $option_name ) {
			if ( str_starts_with( $option_name, '_transient_' ) && ! str_starts_with( $option_name, '_transient_timeout_' ) ) {
				delete_transient( str_replace( '_transient_', '', $option_name ) );
			}
		}
	}

	/**
	 * Migration for v2.5.0.
	 *
	 * - Creates OpenClaw agent role if it does not exist.
	 */
	private function migrate_2_5_0(): void {
		OpenClaw_Role::ensure_role_exists();
	}

	/**
	 * Migration for v2.6.0.
	 *
	 * - Ensures OpenClaw role exists for sites upgrading from 2.5.0.
	 */
	private function migrate_2_6_0(): void {
		OpenClaw_Role::ensure_role_exists();
	}

	/**
	 * Migration for v2.7.0.
	 *
	 * - Sets autoload=no on all WP Pinch options to avoid options table bloat.
	 */
	private function migrate_2_7_0(): void {
		$options = array(
			'wp_pinch_gateway_url',
			'wp_pinch_api_token',
			'wp_pinch_api_disabled',
			'wp_pinch_read_only_mode',
			'wp_pinch_agent_id',
			'wp_pinch_rate_limit',
			'wp_pinch_version',
			'wp_pinch_wizard_completed',
			'wp_pinch_webhook_events',
			'wp_pinch_webhook_channel',
			'wp_pinch_webhook_to',
			'wp_pinch_webhook_deliver',
			'wp_pinch_webhook_model',
			'wp_pinch_webhook_thinking',
			'wp_pinch_webhook_timeout',
			'wp_pinch_webhook_wake_modes',
			'wp_pinch_webhook_endpoint_types',
			'wp_pinch_chat_model',
			'wp_pinch_chat_thinking',
			'wp_pinch_chat_timeout',
			'wp_pinch_chat_placeholder',
			'wp_pinch_session_idle_minutes',
			'wp_pinch_public_chat_rate_limit',
			'wp_pinch_sse_max_connections_per_ip',
			'wp_pinch_chat_max_response_length',
			'wp_pinch_ability_cache_ttl',
			'wp_pinch_ability_cache_generation',
			'wp_pinch_governance_tasks',
			'wp_pinch_governance_mode',
			'wp_pinch_governance_schedule_hash',
			'wp_pinch_feature_flags',
			'wp_pinch_disabled_abilities',
			'wp_pinch_circuit_last_opened_at',
			'wp_pinch_ghost_writer_threshold',
			'wp_pinch_openclaw_user_id',
			'wp_pinch_openclaw_capability_groups',
			'wp_pinch_approval_queue',
			'wp_pinch_pinchdrop_enabled',
			'wp_pinch_pinchdrop_default_outputs',
			'wp_pinch_pinchdrop_auto_save_drafts',
			'wp_pinch_pinchdrop_allowed_sources',
			'wp_pinch_capture_token',
			'wp_pinch_activation_redirect',
		);

		foreach ( $options as $option ) {
			$value = get_option( $option );
			if ( false !== $value ) {
				// @phpstan-ignore argument.type (WordPress update_option accepts 'yes'|'no' for autoload)
				update_option( $option, $value, 'no' );
			}
		}
	}

	/**
	 * Migration for v3.0.0.
	 *
	 * - Adds user_id column to audit table for privacy export/erase queries.
	 */
	private function migrate_3_0_0(): void {
		Audit_Table::create_table();
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
		$api_token   = Settings::get_api_token();

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
				esc_html__( 'The gateway URL and API token aren\'t configured yet. No claws, no pinch — chat, webhooks, and governance won\'t work until you connect.', 'wp-pinch' ),
				esc_url( $settings_url ),
				esc_html__( 'Let\'s get pinching &rarr;', 'wp-pinch' )
			);
			echo '</p></div>';
		}
	}

	/**
	 * Show a dismissible admin notice when Action Scheduler is not available.
	 *
	 * Governance schedules, webhook retries, and audit log cleanup require Action Scheduler.
	 * Only shown on WP Pinch settings page and dashboard.
	 */
	public function action_scheduler_notice(): void {
		if ( self::has_action_scheduler() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'toplevel_page_wp-pinch', 'dashboard' ), true ) ) {
			return;
		}

		$dismissed = get_user_meta( get_current_user_id(), 'wp_pinch_dismissed_as_notice', true );
		if ( $dismissed && version_compare( $dismissed, WP_PINCH_VERSION, '>=' ) ) {
			return;
		}

		// Handle dismissal.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wp_pinch_dismiss_as_notice'] ) && '1' === $_GET['wp_pinch_dismiss_as_notice'] ) {
			if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'wp_pinch_dismiss_as_notice' ) ) {
				update_user_meta( get_current_user_id(), 'wp_pinch_dismissed_as_notice', WP_PINCH_VERSION );
				return;
			}
		}

		$standalone_url = 'https://github.com/woocommerce/action-scheduler/releases';
		$dismiss_url    = wp_nonce_url(
			add_query_arg( 'wp_pinch_dismiss_as_notice', '1' ),
			'wp_pinch_dismiss_as_notice'
		);

		echo '<div class="notice notice-warning is-dismissible"><p>';
		printf(
			'<strong>%s</strong> %s <a href="%s" target="_blank" rel="noopener">%s</a>. ',
			esc_html__( 'WP Pinch:', 'wp-pinch' ),
			esc_html__( 'Governance schedules, webhook retries, and audit log cleanup require the Action Scheduler plugin. Install it from WooCommerce or', 'wp-pinch' ),
			esc_url( $standalone_url ),
			esc_html__( 'standalone', 'wp-pinch' )
		);
		printf(
			'<a href="%s" class="wp-pinch-dismiss-as">%s</a>',
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'wp-pinch' )
		);
		echo '</p></div>';
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
				esc_html__( 'Claws are up — the AI gateway is unreachable. Chat requests are failing fast to protect your site. We\'ll probe again in %1$d seconds. %2$s', 'wp-pinch' ),
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
				esc_html__( 'We\'re poking a claw out to test the waters. The next request will tell us if the gateway has recovered. %s', 'wp-pinch' ),
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
