<?php
/**
 * Settings admin pages and handlers.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page handlers, rendering, and AJAX actions.
 */
trait Settings_Admin_Pages_Trait {

	/**
	 * Register settings.
	 *
	 * Each tab has its own settings group so that saving one tab
	 * does not overwrite options managed by a different tab.
	 */
	public static function register_settings(): void {
		foreach ( self::get_option_definitions() as $def ) {
			$args = array(
				'type'         => $def['type'],
				'default'      => $def['default'],
				'show_in_rest' => false,
			);
			if ( isset( $def['sanitize'] ) ) {
				$args['sanitize_callback'] = $def['sanitize'];
			}
			register_setting( $def['group'], $def['option'], $args );
		}
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
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'wp_pinch_test_connection' ),
				'openclawSkill' => self::get_openclaw_skill_content(),
				'wizard'        => array(
					/* translators: 1: current step number, 2: total steps */
					'stepOf'        => __( 'Step %1$d of %2$d', 'wp-pinch' ),
					'pleaseGateway' => __( 'Please enter a gateway URL first.', 'wp-pinch' ),
					'testing'       => __( 'Testing…', 'wp-pinch' ),
					'connected'     => __( 'Claws at the ready!', 'wp-pinch' ),
					/* translators: %s: HTTP status code */
					'failedHttp'    => __( 'Connection failed (HTTP %s).', 'wp-pinch' ),
					'unableReach'   => __( 'Unable to reach gateway. Check the URL.', 'wp-pinch' ),
					'copied'        => __( 'Snatched!', 'wp-pinch' ),
				),
			)
		);

		wp_enqueue_style(
			'wp-pinch-admin',
			WP_PINCH_URL . 'build/admin.css',
			array(),
			$asset['version']
		);

		wp_add_inline_script(
			'wp-pinch-admin',
			'document.addEventListener("DOMContentLoaded",function(){var btn=document.getElementById("wp-pinch-create-openclaw-agent");if(!btn)return;btn.addEventListener("click",function(){var res=document.getElementById("wp-pinch-create-agent-result");var nonce=btn.getAttribute("data-nonce");var ajaxUrl=btn.getAttribute("data-ajax-url");btn.disabled=true;res.textContent="";var url=ajaxUrl+"?action=wp_pinch_create_openclaw_agent";fetch(url,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded","X-Requested-With":"XMLHttpRequest"},body:"nonce="+encodeURIComponent(nonce),credentials:"same-origin"}).then(function(r){return r.json();}).then(function(data){if(data.success){res.textContent=data.data.message||"Created.";location.reload();}else{res.textContent=data.data||"Error";btn.disabled=false;}}).catch(function(){res.textContent="Request failed";btn.disabled=false;});});});'
		);
	}

	/**
	 * Get OpenClaw skill content for copy-to-clipboard.
	 *
	 * @return string Skill markdown content or empty string if file not found.
	 */
	public static function get_openclaw_skill_content(): string {
		$path = WP_PINCH_DIR . 'wiki/OpenClaw-Skill.md';
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		$content = file_get_contents( $path );
		return is_string( $content ) ? $content : '';
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
		$token = self::get_api_token();

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
					'message' => __( 'Connection secured. Claws at the ready!', 'wp-pinch' ),
					'status'  => $code,
				)
			);
		} else {
			/* translators: %d: HTTP status code returned by the gateway. */
			wp_send_json_error( sprintf( __( 'Connection failed (HTTP %d).', 'wp-pinch' ), $code ) );
		}
	}

	/**
	 * AJAX: create OpenClaw agent user.
	 */
	public static function ajax_create_openclaw_agent(): void {
		check_ajax_referer( 'wp_pinch_create_openclaw_agent', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-pinch' ) );
		}

		$result = OpenClaw_Role::create_agent_user();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'OpenClaw agent user created. Go to Users → Profile to generate an application password for this user.', 'wp-pinch' ),
				'user_id'  => $result['user_id'],
				'username' => 'openclaw-agent',
			)
		);
	}

	/**
	 * Render the OpenClaw role / agent identity section. Public so Connection_Tab can call it.
	 */
	public static function render_openclaw_role_section(): void {
		$current_user_id = (int) get_option( 'wp_pinch_openclaw_user_id', 0 );
		$cap_groups      = get_option( 'wp_pinch_openclaw_capability_groups', OpenClaw_Role::DEFAULT_GROUPS );
		if ( ! is_array( $cap_groups ) ) {
			$cap_groups = OpenClaw_Role::DEFAULT_GROUPS;
		}
		$agent_users  = get_users(
			array(
				'role'    => OpenClaw_Role::ROLE_SLUG,
				'orderby' => 'login',
				'fields'  => array( 'ID', 'user_login' ),
			)
		);
		$labels       = OpenClaw_Role::get_capability_group_labels();
		$create_nonce = wp_create_nonce( 'wp_pinch_create_openclaw_agent' );
		?>
		<div class="wp-pinch-card">
			<h3 class="wp-pinch-card__title"><?php esc_html_e( 'Agent identity (least privilege)', 'wp-pinch' ); ?></h3>
			<p class="description" style="margin-bottom: 1em;">
				<?php esc_html_e( 'Abilities executed via the incoming webhook run as a WordPress user. Use a dedicated OpenClaw agent with limited capabilities for better security.', 'wp-pinch' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wp_pinch_openclaw_user_id"><?php esc_html_e( 'Webhook execution user', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<select id="wp_pinch_openclaw_user_id" name="wp_pinch_openclaw_user_id">
							<option value="0" <?php selected( $current_user_id, 0 ); ?>>
								<?php esc_html_e( 'Use first administrator (default)', 'wp-pinch' ); ?>
							</option>
							<?php foreach ( $agent_users as $u ) : ?>
								<option value="<?php echo (int) $u->ID; ?>" <?php selected( $current_user_id, (int) $u->ID ); ?>>
									<?php echo esc_html( $u->user_login ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( empty( $agent_users ) ) : ?>
							<button type="button" id="wp-pinch-create-openclaw-agent" class="button button-secondary" style="margin-left: 8px;"
								data-nonce="<?php echo esc_attr( $create_nonce ); ?>"
								data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
								<?php esc_html_e( 'Create OpenClaw agent user', 'wp-pinch' ); ?>
							</button>
							<span id="wp-pinch-create-agent-result" class="description" style="margin-left: 8px;"></span>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Create an application password for the agent user in Users → Profile. Use it for MCP/REST connections.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Capability groups', 'wp-pinch' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( OpenClaw_Role::get_capability_group_slugs() as $slug ) : ?>
								<label style="display: block; margin-bottom: 4px;">
									<input type="checkbox" name="wp_pinch_openclaw_capability_groups[]" value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, $cap_groups, true ) ); ?> />
									<?php echo esc_html( $labels[ $slug ] ?? $slug ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Controls what the OpenClaw agent role can do. Only affects users with the OpenClaw Agent role.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
			</table>
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
			$token       = self::get_api_token();
			$wizard_step = ( '' !== $gateway && '' !== $token ) ? 3 : 1;
			\WP_Pinch\Settings\Wizard::render( $wizard_step );
			return;
		}

		// Tab is UI state only; sanitized and allowlisted below. No sensitive action.
		$active_tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );
		$tabs       = array(
			'dashboard'     => __( 'Dashboard', 'wp-pinch' ),
			'what_can_i_do' => __( 'What can I pinch?', 'wp-pinch' ),
			'connection'    => __( 'Connection', 'wp-pinch' ),
			'webhooks'      => __( 'Webhooks', 'wp-pinch' ),
			'governance'    => __( 'Governance', 'wp-pinch' ),
			'abilities'     => __( 'Abilities', 'wp-pinch' ),
			'usage'         => __( 'Usage', 'wp-pinch' ),
			'features'      => __( 'Features', 'wp-pinch' ),
			'audit'         => __( 'Audit Log', 'wp-pinch' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Pinch Settings', 'wp-pinch' ); ?></h1>

			<?php
			// Display-only; value compared to literal 'true'. No sensitive action.
			if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
				add_settings_error(
					'wp_pinch_messages',
					'settings_updated',
					__( 'Settings saved. Territory secured.', 'wp-pinch' ),
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
					case 'dashboard':
						\WP_Pinch\Settings\Tabs\Dashboard_Tab::render();
						break;
					case 'what_can_i_do':
						\WP_Pinch\Settings\Tabs\What_Can_I_Do_Tab::render();
						break;
					case 'webhooks':
						\WP_Pinch\Settings\Tabs\Webhooks_Tab::render();
						break;
					case 'governance':
						\WP_Pinch\Settings\Tabs\Governance_Tab::render();
						break;
					case 'abilities':
						\WP_Pinch\Settings\Tabs\Abilities_Tab::render();
						break;
					case 'features':
						\WP_Pinch\Settings\Tabs\Features_Tab::render();
						break;
					case 'usage':
						\WP_Pinch\Settings\Tabs\Usage_Tab::render();
						break;
					case 'audit':
						\WP_Pinch\Settings\Tabs\Audit_Tab::render();
						break;
					case 'connection':
					default:
						\WP_Pinch\Settings\Tabs\Connection_Tab::render();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

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

		// Nonce verified above; params below are sanitized and used only for export filtering.
		$args = array(
			'event_type' => sanitize_key( $_GET['event_type'] ?? '' ),
			'source'     => sanitize_key( $_GET['source'] ?? '' ),
			'search'     => sanitize_text_field( wp_unslash( $_GET['audit_search'] ?? '' ) ),
			'date_from'  => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
			'date_to'    => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
		);

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

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download; raw export data.
		echo $csv;
		exit;
	}
}

