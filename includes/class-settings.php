<?php
/**
 * Settings page — tabbed admin UI at top-level WP Pinch menu.
 *
 * Tabs: Connection, Webhooks, Governance, Audit Log.
 * AJAX test-connection endpoint. Nonce verification on all forms.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page.
 */
class Settings {

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_activation_redirect' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_finish_wizard' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_skip_wizard' ) );
		add_action( 'wp_ajax_wp_pinch_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_audit_export' ) );

		add_filter(
			'plugin_action_links_' . plugin_basename( WP_PINCH_FILE ),
			array( __CLASS__, 'add_action_links' )
		);
	}

	/**
	 * Redirect to WP Pinch settings (or wizard) after first activation.
	 */
	public static function maybe_activation_redirect(): void {
		if ( ! get_option( 'wp_pinch_activation_redirect', false ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_option( 'wp_pinch_activation_redirect' );
		wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch' ) );
		exit;
	}

	/**
	 * Handle "Finish wizard" link: set wizard completed and redirect to settings.
	 */
	public static function maybe_finish_wizard(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['wp_pinch_finish_wizard'] ) || '1' !== $_GET['wp_pinch_finish_wizard'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'wp_pinch_finish_wizard' ) ) {
			return;
		}
		update_option( 'wp_pinch_wizard_completed', true );
		wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch' ) );
		exit;
	}

	/**
	 * Handle "Skip setup" link: set wizard completed and redirect to settings.
	 */
	public static function maybe_skip_wizard(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['wp_pinch_skip_wizard'] ) || '1' !== $_GET['wp_pinch_skip_wizard'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'wp_pinch_skip_wizard' ) ) {
			return;
		}
		update_option( 'wp_pinch_wizard_completed', true );
		wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch' ) );
		exit;
	}

	/**
	 * Add a "Settings" link on the Plugins list page.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public static function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wp-pinch' ) ),
			esc_html__( 'Settings', 'wp-pinch' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Add the top-level admin menu page.
	 */
	public static function add_menu(): void {
		add_menu_page(
			__( 'WP Pinch', 'wp-pinch' ),
			__( 'WP Pinch', 'wp-pinch' ),
			'manage_options',
			'wp-pinch',
			array( __CLASS__, 'render_page' ),
			'dashicons-controls-repeat',
			80
		);
	}

	/**
	 * Register settings.
	 *
	 * Each tab has its own settings group so that saving one tab
	 * does not overwrite options managed by a different tab.
	 */
	public static function register_settings(): void {
		// Connection tab settings.
		register_setting(
			'wp_pinch_connection',
			'wp_pinch_gateway_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_api_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					// If the placeholder mask is submitted, keep the existing token.
					if ( str_repeat( "\u{2022}", 8 ) === $value || '' === $value ) {
						return get_option( 'wp_pinch_api_token', '' );
					}
					return sanitize_text_field( $value );
				},
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_rate_limit',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 30,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_agent_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_webhook_deliver',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_webhook_channel',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_webhook_to',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_webhook_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_webhook_thinking',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$allowed = array( '', 'off', 'low', 'medium', 'high' );
					return in_array( $value, $allowed, true ) ? $value : '';
				},
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_webhook_timeout',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_chat_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_chat_thinking',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$allowed = array( '', 'off', 'low', 'medium', 'high' );
					return in_array( $value, $allowed, true ) ? $value : '';
				},
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_chat_timeout',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_session_idle_minutes',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_pinchdrop_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_pinchdrop_default_outputs',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					$allowed = array( 'post', 'product_update', 'changelog', 'social' );
					if ( ! is_array( $value ) ) {
						return array( 'post', 'product_update', 'changelog', 'social' );
					}
					$value = array_values( array_intersect( $allowed, array_map( 'sanitize_key', $value ) ) );
					return empty( $value ) ? array( 'post', 'product_update', 'changelog', 'social' ) : $value;
				},
				'default'           => array( 'post', 'product_update', 'changelog', 'social' ),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_pinchdrop_auto_save_drafts',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_pinchdrop_allowed_sources',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$parts = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) $value ) ) ) );
					return implode( ',', $parts );
				},
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		// Webhooks tab settings.
		register_setting(
			'wp_pinch_webhooks',
			'wp_pinch_webhook_events',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					return is_array( $value ) ? array_map( 'sanitize_key', $value ) : array();
				},
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_webhooks',
			'wp_pinch_webhook_wake_modes',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					$allowed = array( 'now', 'next-heartbeat' );
					return array_map(
						function ( $v ) use ( $allowed ) {
							return in_array( $v, $allowed, true ) ? $v : 'now';
						},
						$value
					);
				},
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_webhooks',
			'wp_pinch_webhook_endpoint_types',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					$allowed = array( 'agent', 'wake' );
					return array_map(
						function ( $v ) use ( $allowed ) {
							return in_array( $v, $allowed, true ) ? $v : 'agent';
						},
						$value
					);
				},
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		// Governance tab settings.
		register_setting(
			'wp_pinch_governance',
			'wp_pinch_governance_tasks',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					return is_array( $value ) ? array_map( 'sanitize_key', $value ) : array();
				},
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_governance',
			'wp_pinch_governance_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => 'webhook',
				'show_in_rest'      => false,
			)
		);

		// Abilities tab settings.
		register_setting(
			'wp_pinch_abilities',
			'wp_pinch_disabled_abilities',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
				},
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		// Feature flags settings.
		register_setting(
			'wp_pinch_features',
			'wp_pinch_feature_flags',
			array(
				'type'              => 'object',
				'sanitize_callback' => function ( $value ) {
					// When no checkboxes are checked, $value may be null or empty.
					// Build the explicit map from DEFAULTS keys: checked = true, unchecked = false.
					if ( ! is_array( $value ) ) {
						$value = array();
					}
					$sanitized = array();
					foreach ( Feature_Flags::DEFAULTS as $flag => $default ) {
						$sanitized[ $flag ] = isset( $value[ $flag ] ) && '1' === $value[ $flag ];
					}
					return $sanitized;
				},
				'default'           => Feature_Flags::DEFAULTS,
				'show_in_rest'      => false,
			)
		);

		// Ghost Writer settings.
		register_setting(
			'wp_pinch_governance',
			'wp_pinch_ghost_writer_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 30,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'wp_pinch_connection',
			'wp_pinch_wizard_completed',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
				'show_in_rest'      => false,
			)
		);

		// Web Clipper: token for one-shot capture from browser (bookmarklet).
		register_setting(
			'wp_pinch_connection',
			'wp_pinch_capture_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$value = sanitize_text_field( (string) $value );
					// Preserve existing token when user did not change it (placeholder shown).
					if ( '' === $value || str_repeat( "\u{2022}", 8 ) === $value ) {
						return get_option( 'wp_pinch_capture_token', '' );
					}
					return $value;
				},
				'default'           => '',
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Enqueue admin scripts for the settings page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_wp-pinch' !== $hook_suffix ) {
			return;
		}

		$asset_file = WP_PINCH_DIR . 'build/admin.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'jquery' ),
			'version'      => WP_PINCH_VERSION,
		);

		wp_enqueue_script(
			'wp-pinch-admin',
			WP_PINCH_URL . 'build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'wp-pinch-admin',
			'wpPinchAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wp_pinch_test_connection' ),
				'wizard'  => array(
					/* translators: 1: current step number, 2: total steps */
					'stepOf'        => __( 'Step %1$d of %2$d', 'wp-pinch' ),
					'pleaseGateway' => __( 'Please enter a gateway URL first.', 'wp-pinch' ),
					'testing'       => __( 'Testing…', 'wp-pinch' ),
					'connected'     => __( 'Connected!', 'wp-pinch' ),
					/* translators: %s: HTTP status code */
					'failedHttp'    => __( 'Connection failed (HTTP %s).', 'wp-pinch' ),
					'unableReach'   => __( 'Unable to reach gateway. Check the URL.', 'wp-pinch' ),
					'copied'        => __( 'Copied!', 'wp-pinch' ),
				),
			)
		);

		wp_enqueue_style(
			'wp-pinch-admin',
			WP_PINCH_URL . 'build/admin.css',
			array(),
			$asset['version']
		);
	}

	/**
	 * AJAX: test connection to OpenClaw gateway.
	 */
	public static function ajax_test_connection(): void {
		check_ajax_referer( 'wp_pinch_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-pinch' ) );
		}

		// Rate limit: one test per 5 seconds per user.
		$cooldown_key = 'wp_pinch_test_cd_' . get_current_user_id();
		if ( get_transient( $cooldown_key ) ) {
			wp_send_json_error( __( 'Please wait a few seconds before testing again.', 'wp-pinch' ) );
		}
		set_transient( $cooldown_key, 1, 5 );

		$url   = get_option( 'wp_pinch_gateway_url', '' );
		$token = get_option( 'wp_pinch_api_token', '' );

		if ( empty( $url ) ) {
			wp_send_json_error( __( 'Gateway URL is not configured.', 'wp-pinch' ) );
		}

		$status_url = trailingslashit( $url ) . 'api/v1/status';
		if ( ! wp_http_validate_url( $status_url ) ) {
			wp_send_json_error( __( 'Gateway URL failed security validation. Use a public HTTP or HTTPS URL.', 'wp-pinch' ) );
		}

		$response = wp_safe_remote_get(
			$status_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Log the real error; return a generic message to the client.
			Audit_Table::insert( 'gateway_error', 'admin', $response->get_error_message() );
			wp_send_json_error( __( 'Unable to reach the gateway. Check the URL and try again.', 'wp-pinch' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_success(
				array(
					'message' => __( 'Connected successfully!', 'wp-pinch' ),
					'status'  => $code,
				)
			);
		} else {
			/* translators: %d: HTTP status code returned by the gateway. */
			wp_send_json_error( sprintf( __( 'Connection failed (HTTP %d).', 'wp-pinch' ), $code ) );
		}
	}

	/**
	 * Render the first-run onboarding wizard.
	 *
	 * @param int $initial_step Which step to show initially (1, 2, or 3). Step 3 when gateway + token already saved.
	 */
	public static function render_wizard( int $initial_step = 1 ): void {
		$mcp_url    = rest_url( 'wp-pinch/v1/mcp' );
		$gateway    = get_option( 'wp_pinch_gateway_url', '' );
		$token      = get_option( 'wp_pinch_api_token', '' );
		$show_s1    = ( 1 === $initial_step ) ? 'block' : 'none';
		$show_s2    = ( 2 === $initial_step ) ? 'block' : 'none';
		$show_s3    = ( 3 === $initial_step ) ? 'block' : 'none';
		$finish_url = wp_nonce_url( admin_url( 'admin.php?page=wp-pinch&wp_pinch_finish_wizard=1' ), 'wp_pinch_finish_wizard' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Welcome to WP Pinch', 'wp-pinch' ); ?></h1>

			<div class="wp-pinch-wizard" id="wp-pinch-wizard" aria-live="polite">
				<p class="wp-pinch-wizard__step-indicator" id="wp-pinch-wizard-step-label" aria-live="polite" aria-atomic="true">
					<?php
					/* translators: 1: current step number, 2: total steps */
					echo esc_html( sprintf( __( 'Step %1$d of %2$d', 'wp-pinch' ), (int) $initial_step, 3 ) );
					?>
				</p>

				<!-- Step 1: Welcome -->
				<div class="wp-pinch-wizard__step" data-step="1" id="wp-pinch-wizard-step-1" style="display: <?php echo esc_attr( $show_s1 ); ?>;">
					<div class="wp-pinch-wizard__card">
						<h2><?php esc_html_e( 'Connect WordPress to OpenClaw', 'wp-pinch' ); ?></h2>
						<p>
							<?php esc_html_e( 'WP Pinch bridges your WordPress site with OpenClaw, letting you manage your site from WhatsApp, Telegram, Slack, Discord, or any messaging platform OpenClaw supports.', 'wp-pinch' ); ?>
						</p>
						<h3><?php esc_html_e( 'What you\'ll need:', 'wp-pinch' ); ?></h3>
						<ul class="wp-pinch-wizard-list">
							<li><?php esc_html_e( 'OpenClaw installed and running (local or remote)', 'wp-pinch' ); ?></li>
							<li><?php esc_html_e( 'Your OpenClaw gateway URL and API token', 'wp-pinch' ); ?></li>
						</ul>
						<p>
							<?php
							printf(
								/* translators: %s: link to OpenClaw install docs */
								esc_html__( 'Don\'t have OpenClaw yet? %s', 'wp-pinch' ),
								'<a href="https://docs.openclaw.ai/install" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Install it here', 'wp-pinch' ) . ' &rarr;</a>'
							);
							?>
						</p>
						<p class="wp-pinch-wizard-actions">
							<button type="button" class="button button-primary button-hero" data-wizard-action="go" data-wizard-to="2">
								<?php esc_html_e( 'Let\'s Connect', 'wp-pinch' ); ?> &rarr;
							</button>
						</p>
						<p class="wp-pinch-wizard-skip">
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wp-pinch&wp_pinch_skip_wizard=1' ), 'wp_pinch_skip_wizard' ) ); ?>"><?php esc_html_e( 'Skip setup for now', 'wp-pinch' ); ?></a>
						</p>
					</div>
				</div>

				<!-- Step 2: Connect -->
				<div class="wp-pinch-wizard__step" data-step="2" id="wp-pinch-wizard-step-2" style="display: <?php echo esc_attr( $show_s2 ); ?>;">
					<div class="wp-pinch-wizard__card">
						<h2><?php esc_html_e( 'Configure Connection', 'wp-pinch' ); ?></h2>

						<form method="post" action="options.php" id="wp-pinch-wizard-form">
							<?php settings_fields( 'wp_pinch_connection' ); ?>

							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">
										<label for="wp_pinch_gateway_url"><?php esc_html_e( 'Gateway URL', 'wp-pinch' ); ?></label>
									</th>
									<td>
										<input
											type="url"
											id="wp_pinch_gateway_url"
											name="wp_pinch_gateway_url"
											value="<?php echo esc_attr( $gateway ); ?>"
											class="regular-text"
											placeholder="http://127.0.0.1:18789"
											required
										/>
										<p class="description">
											<?php esc_html_e( 'The URL of your OpenClaw gateway (usually http://127.0.0.1:18789 for local installs).', 'wp-pinch' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wp_pinch_api_token"><?php esc_html_e( 'API Token', 'wp-pinch' ); ?></label>
									</th>
									<td>
										<input
											type="password"
											id="wp_pinch_api_token"
											name="wp_pinch_api_token"
											value="<?php echo esc_attr( $token ); ?>"
											class="regular-text"
											required
										/>
										<p class="description">
											<?php esc_html_e( 'Your OpenClaw gateway token (OPENCLAW_GATEWAY_TOKEN).', 'wp-pinch' ); ?>
										</p>
									</td>
								</tr>
							</table>

							<div class="wp-pinch-wizard-test-row">
								<button type="button" class="button" id="wp-pinch-wizard-test" aria-busy="false" aria-live="polite">
									<?php esc_html_e( 'Test Connection', 'wp-pinch' ); ?>
								</button>
								<span id="wp-pinch-wizard-test-result" class="wp-pinch-wizard-test-result" aria-live="polite"></span>
							</div>

							<hr />

							<h3><?php esc_html_e( 'Your MCP Endpoint', 'wp-pinch' ); ?></h3>
							<p class="description">
								<?php esc_html_e( 'Use this URL to connect OpenClaw to your WordPress site via MCP:', 'wp-pinch' ); ?>
							</p>
							<div class="wp-pinch-copy-row">
								<code id="wp-pinch-mcp-url" class="wp-pinch-copy-code"><?php echo esc_html( $mcp_url ); ?></code>
								<button type="button" class="button wp-pinch-copy-btn" data-wizard-copy="wp-pinch-mcp-url" aria-label="<?php esc_attr_e( 'Copy MCP URL', 'wp-pinch' ); ?>"><?php esc_html_e( 'Copy', 'wp-pinch' ); ?></button>
								<span class="wp-pinch-copy-feedback" id="wp-pinch-copy-feedback-mcp" aria-live="polite"></span>
							</div>
							<p class="description">
								<?php esc_html_e( 'Or run this command in your OpenClaw CLI:', 'wp-pinch' ); ?>
							</p>
							<div class="wp-pinch-copy-row">
								<code id="wp-pinch-cli-cmd" class="wp-pinch-copy-code">npx openclaw connect --mcp-url <?php echo esc_html( $mcp_url ); ?></code>
								<button type="button" class="button wp-pinch-copy-btn" data-wizard-copy="wp-pinch-cli-cmd" aria-label="<?php esc_attr_e( 'Copy command', 'wp-pinch' ); ?>"><?php esc_html_e( 'Copy', 'wp-pinch' ); ?></button>
								<span class="wp-pinch-copy-feedback" id="wp-pinch-copy-feedback-cli" aria-live="polite"></span>
							</div>

							<p class="wp-pinch-wizard-actions wp-pinch-wizard-step-footer">
								<button type="button" class="button" data-wizard-action="go" data-wizard-to="1">
									&larr; <?php esc_html_e( 'Back', 'wp-pinch' ); ?>
								</button>
								<?php submit_button( __( 'Save & Continue', 'wp-pinch' ), 'primary', 'submit', false ); ?>
							</p>
						</form>
					</div>
				</div>

				<!-- Step 3: Try it -->
				<div class="wp-pinch-wizard__step" data-step="3" id="wp-pinch-wizard-step-3" style="display: <?php echo esc_attr( $show_s3 ); ?>;">
					<div class="wp-pinch-wizard__card">
						<h2><?php esc_html_e( 'Try it', 'wp-pinch' ); ?></h2>
						<p>
							<?php esc_html_e( 'Send a message from WhatsApp, Telegram, Slack, or Discord to your OpenClaw agent. Your agent can now use your WordPress site via the MCP endpoint below.', 'wp-pinch' ); ?>
						</p>
						<div class="wp-pinch-copy-row">
							<code class="wp-pinch-copy-code"><?php echo esc_html( $mcp_url ); ?></code>
						</div>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to OpenClaw docs */
								esc_html__( 'Need help connecting a channel? See the %s.', 'wp-pinch' ),
								'<a href="https://docs.openclaw.ai" target="_blank" rel="noopener noreferrer">' . esc_html__( 'OpenClaw docs', 'wp-pinch' ) . ' &rarr;</a>'
							);
							?>
						</p>
						<p class="wp-pinch-wizard-step-footer">
							<a href="<?php echo esc_url( $finish_url ); ?>" class="button button-primary button-hero"><?php esc_html_e( 'Go to Settings', 'wp-pinch' ); ?></a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show onboarding wizard for first-time setup.
		if ( ! get_option( 'wp_pinch_wizard_completed', false ) ) {
			$gateway     = get_option( 'wp_pinch_gateway_url', '' );
			$token       = get_option( 'wp_pinch_api_token', '' );
			$wizard_step = ( '' !== $gateway && '' !== $token ) ? 3 : 1;
			self::render_wizard( $wizard_step );
			return;
		}

		$active_tab = sanitize_key( $_GET['tab'] ?? 'connection' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs       = array(
			'what_can_i_do' => __( 'What can I do?', 'wp-pinch' ),
			'connection'    => __( 'Connection', 'wp-pinch' ),
			'webhooks'      => __( 'Webhooks', 'wp-pinch' ),
			'governance'    => __( 'Governance', 'wp-pinch' ),
			'abilities'     => __( 'Abilities', 'wp-pinch' ),
			'features'      => __( 'Features', 'wp-pinch' ),
			'audit'         => __( 'Audit Log', 'wp-pinch' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Pinch Settings', 'wp-pinch' ); ?></h1>

			<?php
			if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				add_settings_error(
					'wp_pinch_messages',
					'settings_updated',
					__( 'Settings saved.', 'wp-pinch' ),
					'success'
				);
			}
			settings_errors();
			?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings tabs', 'wp-pinch' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>"
						class="<?php echo esc_attr( $active_tab === $slug ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="wp-pinch-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'what_can_i_do':
						self::render_tab_what_can_i_do();
						break;
					case 'webhooks':
						self::render_tab_webhooks();
						break;
					case 'governance':
						self::render_tab_governance();
						break;
					case 'abilities':
						self::render_tab_abilities();
						break;
					case 'features':
						self::render_tab_features();
						break;
					case 'audit':
						self::render_tab_audit();
						break;
					case 'connection':
					default:
						self::render_tab_connection();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * "What can I do?" tab — discoverability of main features.
	 */
	private static function render_tab_what_can_i_do(): void {
		$wiki = 'https://github.com/RegionallyFamous/wp-pinch/wiki';
		?>
		<div class="wp-pinch-what-can-i-do">
			<p><?php esc_html_e( 'Here’s what you can do with WP Pinch:', 'wp-pinch' ); ?></p>
			<ul>
				<li>
					<strong><?php esc_html_e( 'Capture from channels (PinchDrop)', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'Send ideas from WhatsApp, Telegram, Slack, or Discord; turn them into draft packs or quick notes.', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/PinchDrop' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PinchDrop guide', 'wp-pinch' ); ?> &rarr;</a>
				</li>
				<li>
					<strong><?php esc_html_e( 'Chat with your site (block)', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'Add a chat block to any post or page so visitors (or you) can talk to your site.', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/Chat-Block' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Chat block', 'wp-pinch' ); ?> &rarr;</a>
				</li>
				<li>
					<strong><?php esc_html_e( 'Daily digest (Tide Report)', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'Governance findings (stale posts, SEO, drafts) bundled into one daily webhook.', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/Configuration' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Configuration', 'wp-pinch' ); ?> &rarr;</a>
				</li>
				<li>
					<strong><?php esc_html_e( 'Synthesize across posts (Weave)', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'Search content and get a payload ready for synthesis; build answers from your existing posts.', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/Abilities-Reference' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Abilities Reference', 'wp-pinch' ); ?> &rarr;</a>
				</li>
				<li>
					<strong><?php esc_html_e( 'Quick tools', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'TL;DR on publish, Link Suggester (suggest internal links), Quote Bank (extract notable sentences).', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/Abilities-Reference' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Abilities Reference', 'wp-pinch' ); ?> &rarr;</a>
				</li>
			</ul>
			<p>
				<a href="<?php echo esc_url( $wiki . '/Abilities-Reference' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Full Abilities Reference', 'wp-pinch' ); ?> &rarr;</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Connection tab.
	 */
	private static function render_tab_connection(): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wp_pinch_connection' ); ?>

			<div class="wp-pinch-card">
				<h3 class="wp-pinch-card__title"><?php esc_html_e( 'Gateway & API', 'wp-pinch' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wp_pinch_gateway_url"><?php esc_html_e( 'OpenClaw Gateway URL', 'wp-pinch' ); ?></label>
						</th>
						<td>
							<input type="url" id="wp_pinch_gateway_url" name="wp_pinch_gateway_url"
									value="<?php echo esc_attr( get_option( 'wp_pinch_gateway_url' ) ); ?>"
									class="regular-text" placeholder="http://127.0.0.1:3000" />
							<p class="description"><?php esc_html_e( 'The URL of your OpenClaw Gateway instance.', 'wp-pinch' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_pinch_api_token"><?php esc_html_e( 'API Token', 'wp-pinch' ); ?></label>
						</th>
						<td>
						<?php $has_token = ! empty( get_option( 'wp_pinch_api_token' ) ); ?>
						<input type="password" id="wp_pinch_api_token" name="wp_pinch_api_token"
								value="<?php echo $has_token ? esc_attr( str_repeat( "\u{2022}", 8 ) ) : ''; ?>"
								class="regular-text" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Bearer token for authenticating with the OpenClaw webhook API.', 'wp-pinch' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_pinch_rate_limit"><?php esc_html_e( 'Rate Limit', 'wp-pinch' ); ?></label>
						</th>
						<td>
							<input type="number" id="wp_pinch_rate_limit" name="wp_pinch_rate_limit"
									value="<?php echo esc_attr( get_option( 'wp_pinch_rate_limit', 30 ) ); ?>"
									class="small-text" min="1" max="1000" />
							<span><?php esc_html_e( 'webhooks per minute', 'wp-pinch' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_pinch_agent_id"><?php esc_html_e( 'Agent ID', 'wp-pinch' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="wp_pinch_agent_id"
								name="wp_pinch_agent_id"
								value="<?php echo esc_attr( get_option( 'wp_pinch_agent_id', '' ) ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. hooks or main', 'wp-pinch' ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Optional. Route webhooks and chat to a specific OpenClaw agent. Leave blank for default agent.', 'wp-pinch' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="wp-pinch-card">
				<h3 class="wp-pinch-card__title"><?php esc_html_e( 'Webhook defaults', 'wp-pinch' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wp_pinch_webhook_channel"><?php esc_html_e( 'Delivery Channel', 'wp-pinch' ); ?></label>
						</th>
					<td>
						<select id="wp_pinch_webhook_channel" name="wp_pinch_webhook_channel">
							<option value="" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), '' ); ?>><?php esc_html_e( 'None (agent only)', 'wp-pinch' ); ?></option>
							<option value="last" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'last' ); ?>><?php esc_html_e( 'Last active channel', 'wp-pinch' ); ?></option>
							<option value="whatsapp" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'whatsapp' ); ?>>WhatsApp</option>
							<option value="telegram" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'telegram' ); ?>>Telegram</option>
							<option value="discord" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'discord' ); ?>>Discord</option>
							<option value="slack" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'slack' ); ?>>Slack</option>
							<option value="signal" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'signal' ); ?>>Signal</option>
							<option value="imessage" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'imessage' ); ?>>iMessage</option>
							<option value="msteams" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'msteams' ); ?>>Microsoft Teams</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Optional. Deliver webhook responses to a messaging channel.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_webhook_to"><?php esc_html_e( 'Recipient', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="wp_pinch_webhook_to"
							name="wp_pinch_webhook_to"
							value="<?php echo esc_attr( get_option( 'wp_pinch_webhook_to', '' ) ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. +15551234567 or channel ID', 'wp-pinch' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Optional. Recipient identifier for the delivery channel (phone number, chat ID, etc.).', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Deliver Responses', 'wp-pinch' ); ?>
					</th>
					<td>
						<label for="wp_pinch_webhook_deliver">
							<input
								type="checkbox"
								id="wp_pinch_webhook_deliver"
								name="wp_pinch_webhook_deliver"
								value="1"
								<?php checked( get_option( 'wp_pinch_webhook_deliver', true ) ); ?>
							/>
							<?php esc_html_e( 'Send agent responses to the delivery channel', 'wp-pinch' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, OpenClaw will deliver webhook responses to the configured messaging channel.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_webhook_model"><?php esc_html_e( 'Webhook Model', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="wp_pinch_webhook_model"
							name="wp_pinch_webhook_model"
							value="<?php echo esc_attr( get_option( 'wp_pinch_webhook_model', '' ) ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. openai/gpt-5.2-mini', 'wp-pinch' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Override which model processes webhook events. Leave empty for the agent default.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_webhook_thinking"><?php esc_html_e( 'Thinking Level', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<select id="wp_pinch_webhook_thinking" name="wp_pinch_webhook_thinking">
							<option value="" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), '' ); ?>><?php esc_html_e( 'Default', 'wp-pinch' ); ?></option>
							<option value="off" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), 'off' ); ?>><?php esc_html_e( 'Off', 'wp-pinch' ); ?></option>
							<option value="low" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), 'low' ); ?>><?php esc_html_e( 'Low', 'wp-pinch' ); ?></option>
							<option value="medium" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), 'medium' ); ?>><?php esc_html_e( 'Medium', 'wp-pinch' ); ?></option>
							<option value="high" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), 'high' ); ?>><?php esc_html_e( 'High', 'wp-pinch' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Control the thinking level for webhook-triggered agent turns.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_webhook_timeout"><?php esc_html_e( 'Timeout (seconds)', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="wp_pinch_webhook_timeout"
							name="wp_pinch_webhook_timeout"
							value="<?php echo esc_attr( get_option( 'wp_pinch_webhook_timeout', 0 ) ); ?>"
							class="small-text"
							min="0"
							max="600"
							placeholder="0"
						/>
						<p class="description">
							<?php esc_html_e( 'Maximum seconds for webhook agent runs (0 = no limit).', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				</table>
			</div>

			<div class="wp-pinch-card">
				<h3 class="wp-pinch-card__title"><?php esc_html_e( 'Chat Settings', 'wp-pinch' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Configure the interactive chat block behavior.', 'wp-pinch' ); ?></p>
				<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wp_pinch_chat_model"><?php esc_html_e( 'Chat Model', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input type="text" id="wp_pinch_chat_model" name="wp_pinch_chat_model"
								value="<?php echo esc_attr( get_option( 'wp_pinch_chat_model', '' ) ); ?>"
								class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'e.g. anthropic/claude-sonnet-4-5 — leave empty for gateway default.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_chat_thinking"><?php esc_html_e( 'Chat Thinking Level', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<select id="wp_pinch_chat_thinking" name="wp_pinch_chat_thinking">
							<?php
							$current_thinking = get_option( 'wp_pinch_chat_thinking', '' );
							$thinking_options = array(
								''       => __( 'Default (gateway decides)', 'wp-pinch' ),
								'off'    => __( 'Off', 'wp-pinch' ),
								'low'    => __( 'Low', 'wp-pinch' ),
								'medium' => __( 'Medium', 'wp-pinch' ),
								'high'   => __( 'High', 'wp-pinch' ),
							);
							foreach ( $thinking_options as $val => $label ) :
								?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_thinking, $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_chat_timeout"><?php esc_html_e( 'Chat Timeout (seconds)', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input type="number" id="wp_pinch_chat_timeout" name="wp_pinch_chat_timeout"
								value="<?php echo esc_attr( get_option( 'wp_pinch_chat_timeout', 0 ) ); ?>"
								min="0" max="600" step="1" class="small-text" />
						<p class="description">
							<?php esc_html_e( '0 = gateway default. Maximum 600 seconds.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_session_idle_minutes"><?php esc_html_e( 'Session Idle Timeout (minutes)', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input type="number" id="wp_pinch_session_idle_minutes" name="wp_pinch_session_idle_minutes"
								value="<?php echo esc_attr( get_option( 'wp_pinch_session_idle_minutes', 0 ) ); ?>"
								min="0" max="1440" step="1" class="small-text" />
						<p class="description">
							<?php esc_html_e( '0 = gateway default. After this many idle minutes, a new session starts.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_capture_token"><?php esc_html_e( 'Web Clipper capture token', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<?php $has_capture_token = ! empty( get_option( 'wp_pinch_capture_token' ) ); ?>
						<input
							type="password"
							id="wp_pinch_capture_token"
							name="wp_pinch_capture_token"
							value="<?php echo $has_capture_token ? esc_attr( str_repeat( "\u{2022}", 8 ) ) : ''; ?>"
							class="regular-text"
							autocomplete="off"
						/>
						<p class="description">
							<?php esc_html_e( 'Optional. Long-lived secret token for the Web Clipper / bookmarklet capture endpoint. If set, one-shot captures from the browser use this token (query param or X-WP-Pinch-Capture-Token header). Keep it secret; the URL may contain the token.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				</table>
			</div>

			<div class="wp-pinch-card">
				<h3 class="wp-pinch-card__title"><?php esc_html_e( 'PinchDrop (Capture Anywhere)', 'wp-pinch' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Capture ideas from OpenClaw channels and auto-generate draft packs.', 'wp-pinch' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable PinchDrop', 'wp-pinch' ); ?></th>
					<td>
						<label for="wp_pinch_pinchdrop_enabled">
							<input type="checkbox" id="wp_pinch_pinchdrop_enabled" name="wp_pinch_pinchdrop_enabled" value="1"
								<?php checked( (bool) get_option( 'wp_pinch_pinchdrop_enabled', false ) ); ?> />
							<?php esc_html_e( 'Accept capture requests on /wp-pinch/v1/pinchdrop/capture', 'wp-pinch' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default output types', 'wp-pinch' ); ?></th>
					<td>
						<?php $pd_outputs = (array) get_option( 'wp_pinch_pinchdrop_default_outputs', array( 'post', 'product_update', 'changelog', 'social' ) ); ?>
						<label><input type="checkbox" name="wp_pinch_pinchdrop_default_outputs[]" value="post" <?php checked( in_array( 'post', $pd_outputs, true ) ); ?> /> <?php esc_html_e( 'Blog post', 'wp-pinch' ); ?></label><br />
						<label><input type="checkbox" name="wp_pinch_pinchdrop_default_outputs[]" value="product_update" <?php checked( in_array( 'product_update', $pd_outputs, true ) ); ?> /> <?php esc_html_e( 'Product update', 'wp-pinch' ); ?></label><br />
						<label><input type="checkbox" name="wp_pinch_pinchdrop_default_outputs[]" value="changelog" <?php checked( in_array( 'changelog', $pd_outputs, true ) ); ?> /> <?php esc_html_e( 'Changelog', 'wp-pinch' ); ?></label><br />
						<label><input type="checkbox" name="wp_pinch_pinchdrop_default_outputs[]" value="social" <?php checked( in_array( 'social', $pd_outputs, true ) ); ?> /> <?php esc_html_e( 'Social snippets', 'wp-pinch' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-save generated drafts', 'wp-pinch' ); ?></th>
					<td>
						<label for="wp_pinch_pinchdrop_auto_save_drafts">
							<input type="checkbox" id="wp_pinch_pinchdrop_auto_save_drafts" name="wp_pinch_pinchdrop_auto_save_drafts" value="1"
								<?php checked( (bool) get_option( 'wp_pinch_pinchdrop_auto_save_drafts', true ) ); ?> />
							<?php esc_html_e( 'Create draft posts automatically from generated output', 'wp-pinch' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_pinchdrop_allowed_sources"><?php esc_html_e( 'Allowed capture sources', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input type="text" id="wp_pinch_pinchdrop_allowed_sources" name="wp_pinch_pinchdrop_allowed_sources"
							value="<?php echo esc_attr( get_option( 'wp_pinch_pinchdrop_allowed_sources', '' ) ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'slack,telegram,whatsapp', 'wp-pinch' ); ?>" />
						<p class="description"><?php esc_html_e( 'Optional comma-separated source allowlist. Leave empty to allow all sources.', 'wp-pinch' ); ?></p>
					</td>
				</tr>
			</table>
			</div>

			<p>
				<button type="button" id="wp-pinch-test-connection" class="button button-secondary">
					<?php esc_html_e( 'Test Connection', 'wp-pinch' ); ?>
				</button>
				<span id="wp-pinch-connection-result"></span>
			</p>

			<?php submit_button(); ?>
		</form>

		<div class="wp-pinch-mcp-info">
			<h3><?php esc_html_e( 'MCP Server Endpoint', 'wp-pinch' ); ?></h3>
			<p><?php esc_html_e( 'Your WP Pinch MCP server is available at:', 'wp-pinch' ); ?></p>
			<code><?php echo esc_html( rest_url( 'wp-pinch/mcp' ) ); ?></code>
			<p class="description">
				<?php esc_html_e( 'Use this URL to connect OpenClaw (or any MCP client) via mcp-wordpress-remote.', 'wp-pinch' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Webhooks tab.
	 */
	private static function render_tab_webhooks(): void {
		$events  = Webhook_Dispatcher::get_available_events();
		$enabled = get_option( 'wp_pinch_webhook_events', array() );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wp_pinch_webhooks' ); ?>

			<p><?php esc_html_e( 'Select which WordPress events trigger webhooks to OpenClaw. Leave all unchecked to enable everything.', 'wp-pinch' ); ?></p>

			<table class="form-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Event', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Enabled', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Endpoint', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Wake Mode', 'wp-pinch' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$wake_modes        = get_option( 'wp_pinch_webhook_wake_modes', array() );
				$endpoint_types    = get_option( 'wp_pinch_webhook_endpoint_types', array() );
				$wake_defaults     = array(
					'post_status_change' => 'next-heartbeat',
					'new_comment'        => 'next-heartbeat',
					'user_register'      => 'next-heartbeat',
					'woo_order_change'   => 'now',
					'post_delete'        => 'now',
					'governance_finding' => 'next-heartbeat',
				);
				$endpoint_defaults = array(
					'post_status_change' => 'wake',
					'new_comment'        => 'wake',
					'user_register'      => 'wake',
					'woo_order_change'   => 'agent',
					'post_delete'        => 'agent',
					'governance_finding' => 'wake',
				);
				foreach ( $events as $key => $label ) :
					$event_wake     = $wake_modes[ $key ] ?? ( $wake_defaults[ $key ] ?? 'now' );
					$event_endpoint = $endpoint_types[ $key ] ?? ( $endpoint_defaults[ $key ] ?? 'agent' );
					?>
					<tr>
						<td><strong><?php echo esc_html( $label ); ?></strong></td>
						<td>
							<input type="checkbox" name="wp_pinch_webhook_events[]"
									value="<?php echo esc_attr( $key ); ?>"
									<?php checked( in_array( $key, $enabled, true ) ); ?> />
						</td>
						<td>
							<select name="wp_pinch_webhook_endpoint_types[<?php echo esc_attr( $key ); ?>]">
								<option value="agent" <?php selected( $event_endpoint, 'agent' ); ?>><?php esc_html_e( '/hooks/agent (full turn)', 'wp-pinch' ); ?></option>
								<option value="wake" <?php selected( $event_endpoint, 'wake' ); ?>><?php esc_html_e( '/hooks/wake (lightweight)', 'wp-pinch' ); ?></option>
							</select>
						</td>
						<td>
							<select name="wp_pinch_webhook_wake_modes[<?php echo esc_attr( $key ); ?>]">
								<option value="now" <?php selected( $event_wake, 'now' ); ?>><?php esc_html_e( 'Immediate', 'wp-pinch' ); ?></option>
								<option value="next-heartbeat" <?php selected( $event_wake, 'next-heartbeat' ); ?>><?php esc_html_e( 'Next heartbeat', 'wp-pinch' ); ?></option>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Governance tab.
	 */
	private static function render_tab_governance(): void {
		$tasks   = Governance::get_available_tasks();
		$enabled = Governance::get_enabled_tasks();
		$mode    = get_option( 'wp_pinch_governance_mode', 'webhook' );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wp_pinch_governance' ); ?>

			<h3><?php esc_html_e( 'Delivery Mode', 'wp-pinch' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'How to process findings', 'wp-pinch' ); ?></th>
					<td>
						<label>
							<input type="radio" name="wp_pinch_governance_mode" value="webhook" <?php checked( $mode, 'webhook' ); ?> />
							<?php esc_html_e( 'Webhook to OpenClaw (recommended)', 'wp-pinch' ); ?>
						</label>
						<br />
						<label>
							<input type="radio" name="wp_pinch_governance_mode" value="server" <?php checked( $mode, 'server' ); ?> />
							<?php esc_html_e( 'Server-side via WP AI Client (requires WP 7.0+)', 'wp-pinch' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Governance Tasks', 'wp-pinch' ); ?></h3>
			<p><?php esc_html_e( 'Select which autonomous tasks to run. Leave all unchecked to enable everything.', 'wp-pinch' ); ?></p>

			<table class="form-table">
				<?php foreach ( $tasks as $key => $label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wp_pinch_governance_tasks[]"
										value="<?php echo esc_attr( $key ); ?>"
										<?php checked( in_array( $key, $enabled, true ) ); ?> />
								<?php esc_html_e( 'Enabled', 'wp-pinch' ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	// =========================================================================
	// Abilities Tab
	// =========================================================================

	/**
	 * Abilities toggle tab — lets admins disable individual abilities.
	 */
	private static function render_tab_abilities(): void {
		$all_abilities = Abilities::get_ability_names();
		$disabled      = get_option( 'wp_pinch_disabled_abilities', array() );

		if ( ! is_array( $disabled ) ) {
			$disabled = array();
		}
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wp_pinch_abilities' ); ?>

			<p><?php esc_html_e( 'Uncheck abilities you want to disable. Disabled abilities will not be registered or available via MCP.', 'wp-pinch' ); ?></p>

			<table class="form-table wp-pinch-abilities-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'On', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Ability', 'wp-pinch' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_abilities as $name ) : ?>
						<tr>
							<td>
								<input type="checkbox" name="wp_pinch_disabled_abilities[]"
									value="<?php echo esc_attr( $name ); ?>"
									<?php checked( in_array( $name, $disabled, true ) ); ?> />
							</td>
							<td><code><?php echo esc_html( $name ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="description"><?php esc_html_e( 'Check the box to DISABLE the ability. Leave unchecked to keep it enabled.', 'wp-pinch' ); ?></p>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	// =========================================================================
	// Features Tab
	// =========================================================================

	/**
	 * Feature flags tab.
	 */
	private static function render_tab_features(): void {
		$flags  = Feature_Flags::get_all();
		$labels = array(
			'streaming_chat'     => __( 'Streaming Chat (SSE)', 'wp-pinch' ),
			'webhook_signatures' => __( 'HMAC-SHA256 Webhook Signatures', 'wp-pinch' ),
			'circuit_breaker'    => __( 'Circuit Breaker (fail-fast on gateway outage)', 'wp-pinch' ),
			'ability_toggle'     => __( 'Ability Toggle (disable individual abilities)', 'wp-pinch' ),
			'webhook_dashboard'  => __( 'Webhook Dashboard in Audit Log', 'wp-pinch' ),
			'audit_search'       => __( 'Audit Log Search & Filters', 'wp-pinch' ),
			'health_endpoint'    => __( 'Public Health Check Endpoint', 'wp-pinch' ),
			'slash_commands'     => __( 'Slash commands (/new, /status) in chat', 'wp-pinch' ),
			'token_display'      => __( 'Show token usage in chat footer', 'wp-pinch' ),
			'pinchdrop_engine'   => __( 'PinchDrop engine (capture anywhere draft packs)', 'wp-pinch' ),
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wp_pinch_features' ); ?>

			<h3><?php esc_html_e( 'Feature Flags', 'wp-pinch' ); ?></h3>
			<p><?php esc_html_e( 'Enable or disable features. Changes take effect immediately.', 'wp-pinch' ); ?></p>

			<table class="form-table">
				<?php foreach ( $labels as $flag => $label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wp_pinch_feature_flags[<?php echo esc_attr( $flag ); ?>]"
									value="1"
									<?php checked( $flags[ $flag ] ?? false ); ?> />
								<?php esc_html_e( 'Enabled', 'wp-pinch' ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<p class="description">
				<?php esc_html_e( 'Feature flags can also be overridden via the wp_pinch_feature_flag filter in code.', 'wp-pinch' ); ?>
			</p>

			<?php submit_button(); ?>
		</form>

		<div class="wp-pinch-circuit-status">
			<h3><?php esc_html_e( 'Circuit Breaker Status', 'wp-pinch' ); ?></h3>
			<?php
			$state       = Circuit_Breaker::get_state();
			$retry_after = Circuit_Breaker::get_retry_after();
			$state_label = array(
				'closed'    => __( 'Closed (normal)', 'wp-pinch' ),
				'open'      => __( 'Open (failing fast)', 'wp-pinch' ),
				'half_open' => __( 'Half-Open (probing)', 'wp-pinch' ),
			);
			?>
			<p>
				<?php
				printf(
					/* translators: %s: circuit state label */
					esc_html__( 'State: %s', 'wp-pinch' ),
					'<strong>' . esc_html( $state_label[ $state ] ?? $state ) . '</strong>'
				);
				?>
			</p>
			<?php if ( $retry_after > 0 ) : ?>
				<p>
					<?php
					printf(
						/* translators: %d: seconds until retry */
						esc_html__( 'Retry in %d seconds.', 'wp-pinch' ),
						absint( $retry_after )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// =========================================================================
	// Audit Log Tab (Enhanced)
	// =========================================================================

	/**
	 * Audit log tab with search, date filters, and CSV export.
	 */
	private static function render_tab_audit(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page      = max( 1, absint( $_GET['audit_page'] ?? 1 ) );
		$filter    = sanitize_key( $_GET['event_type'] ?? '' );
		$source    = sanitize_key( $_GET['source'] ?? '' );
		$search    = sanitize_text_field( wp_unslash( $_GET['audit_search'] ?? '' ) );
		$date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
		$date_to   = sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) );
		// phpcs:enable

		$query_args = array(
			'event_type' => $filter,
			'source'     => $source,
			'search'     => $search,
			'date_from'  => $date_from,
			'date_to'    => $date_to,
			'per_page'   => 30,
			'page'       => $page,
		);

		$result    = Audit_Table::query( $query_args );
		$items     = $result['items'];
		$total     = $result['total'];
		$max_pages = (int) ceil( $total / 30 );
		?>
		<h3><?php esc_html_e( 'Audit Log', 'wp-pinch' ); ?></h3>

		<!-- Search & Filter Bar -->
		<div class="wp-pinch-audit-filters">
			<form method="get" action="">
				<input type="hidden" name="page" value="wp-pinch" />
				<input type="hidden" name="tab" value="audit" />

				<label for="audit_search"><?php esc_html_e( 'Search:', 'wp-pinch' ); ?></label>
				<input type="text" id="audit_search" name="audit_search"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php esc_attr_e( 'Search messages...', 'wp-pinch' ); ?>"
					class="regular-text" />

				<label for="event_type"><?php esc_html_e( 'Event:', 'wp-pinch' ); ?></label>
				<input type="text" id="event_type" name="event_type"
					value="<?php echo esc_attr( $filter ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. webhook_sent', 'wp-pinch' ); ?>"
					class="regular-text wp-pinch-audit-input-event" />

				<label for="source"><?php esc_html_e( 'Source:', 'wp-pinch' ); ?></label>
				<input type="text" id="source" name="source"
					value="<?php echo esc_attr( $source ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. webhook', 'wp-pinch' ); ?>"
					class="regular-text wp-pinch-audit-input-source" />

				<br class="wp-pinch-audit-filters-br" />

				<label for="date_from"><?php esc_html_e( 'From:', 'wp-pinch' ); ?></label>
				<input type="date" id="date_from" name="date_from"
					value="<?php echo esc_attr( $date_from ); ?>" />

				<label for="date_to"><?php esc_html_e( 'To:', 'wp-pinch' ); ?></label>
				<input type="date" id="date_to" name="date_to"
					value="<?php echo esc_attr( $date_to ); ?>" />

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-pinch' ); ?></button>

				<?php if ( $search || $filter || $source || $date_from || $date_to ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-pinch&tab=audit' ) ); ?>" class="button">
						<?php esc_html_e( 'Reset', 'wp-pinch' ); ?>
					</a>
				<?php endif; ?>
			</form>
		</div>

		<p>
			<?php
			printf(
				/* translators: %d: total number of log entries */
				esc_html__( '%d entries found. Entries older than 90 days are automatically removed.', 'wp-pinch' ),
				(int) $total
			);
			?>

			<?php if ( $total > 0 ) : ?>
				&mdash;
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_pinch_export_audit', '1' ), 'wp_pinch_export_audit' ) ); ?>">
					<?php esc_html_e( 'Export CSV', 'wp-pinch' ); ?>
				</a>
			<?php endif; ?>
		</p>

		<?php if ( ! empty( $items ) ) : ?>
			<div class="wp-pinch-audit-table-wrap">
			<table class="widefat striped wp-pinch-audit-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Event', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Source', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Message', 'wp-pinch' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td class="wp-pinch-audit-date"><?php echo esc_html( $item['created_at'] ); ?></td>
							<td><code><?php echo esc_html( $item['event_type'] ); ?></code></td>
							<td><?php echo esc_html( $item['source'] ); ?></td>
							<td><?php echo esc_html( $item['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>

			<?php if ( $max_pages > 1 ) : ?>
				<div class="tablenav wp-pinch-audit-nav">
					<div class="tablenav-pages">
						<?php
						$base_url      = remove_query_arg( 'audit_page' );
						$max_page_link = min( $max_pages, 50 );
						for ( $i = 1; $i <= $max_page_link; $i++ ) :
							if ( $i === $page ) :
								?>
								<strong><?php echo esc_html( (string) $i ); ?></strong>
							<?php else : ?>
								<a href="<?php echo esc_url( add_query_arg( 'audit_page', $i, $base_url ) ); ?>"><?php echo esc_html( (string) $i ); ?></a>
							<?php endif; ?>
						<?php endfor; ?>
						<?php if ( $max_pages > 50 ) : ?>
							<span>&hellip; (<?php echo esc_html( (string) $max_pages ); ?> pages)</span>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="wp-pinch-audit-empty">
				<div class="wp-pinch-audit-empty-icon" aria-hidden="true">📋</div>
				<p><?php esc_html_e( 'No audit log entries match your filters.', 'wp-pinch' ); ?></p>
				<p class="description"><?php esc_html_e( 'Events will appear here once webhooks run or the chat block is used. Try adjusting filters or check back later.', 'wp-pinch' ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	// =========================================================================
	// CSV Export
	// =========================================================================

	/**
	 * Handle audit log CSV export.
	 */
	public static function handle_audit_export(): void {
		if ( ! isset( $_GET['wp_pinch_export_audit'] ) || '1' !== $_GET['wp_pinch_export_audit'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'wp_pinch_export_audit' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'wp-pinch' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$args = array(
			'event_type' => sanitize_key( $_GET['event_type'] ?? '' ),
			'source'     => sanitize_key( $_GET['source'] ?? '' ),
			'search'     => sanitize_text_field( wp_unslash( $_GET['audit_search'] ?? '' ) ),
			'date_from'  => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
			'date_to'    => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
		);
		// phpcs:enable

		$csv      = Audit_Table::export_csv( $args );
		$filename = 'wp-pinch-audit-' . gmdate( 'Y-m-d' ) . '.csv';
		// Strip any control chars to prevent CRLF injection in header.
		$filename = str_replace( array( "\r", "\n", '"' ), '', $filename );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, private' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'" );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV export to file download.
		exit;
	}
}
