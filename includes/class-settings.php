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
		add_action( 'wp_ajax_wp_pinch_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_filter(
			'plugin_action_links_' . plugin_basename( WP_PINCH_FILE ),
			array( __CLASS__, 'add_action_links' )
		);
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

		$response = wp_remote_get(
			trailingslashit( $url ) . 'api/v1/status',
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
	 * Render the settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = sanitize_key( $_GET['tab'] ?? 'connection' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs       = array(
			'connection' => __( 'Connection', 'wp-pinch' ),
			'webhooks'   => __( 'Webhooks', 'wp-pinch' ),
			'governance' => __( 'Governance', 'wp-pinch' ),
			'audit'      => __( 'Audit Log', 'wp-pinch' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Pinch Settings', 'wp-pinch' ); ?></h1>

			<?php settings_errors(); ?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings tabs', 'wp-pinch' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>"
						class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="wp-pinch-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'webhooks':
						self::render_tab_webhooks();
						break;
					case 'governance':
						self::render_tab_governance();
						break;
					case 'audit':
						self::render_tab_audit();
						break;
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
	 * Connection tab.
	 */
	private static function render_tab_connection(): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wp_pinch_connection' ); ?>

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
			</table>

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
				<?php foreach ( $events as $key => $label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wp_pinch_webhook_events[]"
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

	/**
	 * Audit log tab.
	 */
	private static function render_tab_audit(): void {
		$page      = absint( $_GET['audit_page'] ?? 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter    = sanitize_key( $_GET['event_type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result    = Audit_Table::query(
			array(
				'event_type' => $filter,
				'per_page'   => 30,
				'page'       => $page,
			)
		);
		$items     = $result['items'];
		$total     = $result['total'];
		$max_pages = ceil( $total / 30 );
		?>
		<h3><?php esc_html_e( 'Audit Log', 'wp-pinch' ); ?></h3>

		<p>
			<?php
			printf(
				/* translators: %d: total number of log entries */
				esc_html__( 'Showing %d total entries. Entries older than 90 days are automatically removed.', 'wp-pinch' ),
				(int) $total
			);
			?>
		</p>

		<?php if ( ! empty( $items ) ) : ?>
			<table class="widefat striped">
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
							<td><?php echo esc_html( $item['created_at'] ); ?></td>
							<td><code><?php echo esc_html( $item['event_type'] ); ?></code></td>
							<td><?php echo esc_html( $item['source'] ); ?></td>
							<td><?php echo esc_html( $item['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $max_pages > 1 ) : ?>
				<div class="tablenav wp-pinch-audit-nav">
					<div class="tablenav-pages">
						<?php for ( $i = 1; $i <= $max_pages; $i++ ) : ?>
							<?php if ( $i === $page ) : ?>
							<strong><?php echo esc_html( (string) $i ); ?></strong>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( 'audit_page', $i ) ); ?>"><?php echo esc_html( (string) $i ); ?></a>
							<?php endif; ?>
						<?php endfor; ?>
					</div>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No audit log entries yet.', 'wp-pinch' ); ?></p>
		<?php endif; ?>
		<?php
	}
}
