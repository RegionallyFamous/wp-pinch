<?php
/**
 * Network admin settings for multisite — shared API config and cross-site audit.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Network-level WP Pinch settings and cross-site audit view.
 */
class Network_Settings {

	/**
	 * Wire hooks when in network admin.
	 */
	public static function init(): void {
		if ( ! is_multisite() || ! is_network_admin() ) {
			return;
		}

		add_action( 'network_admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'network_admin_actions_wp-pinch-network', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Add WP Pinch under Network → Settings.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'settings.php',
			__( 'WP Pinch', 'wp-pinch' ),
			__( 'WP Pinch', 'wp-pinch' ),
			'manage_network',
			'wp-pinch-network',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Handle save of network defaults (nonce verified).
	 */
	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		if ( empty( $_POST['wp_pinch_network_save'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'wp_pinch_network_settings' ) ) {
			return;
		}

		$gateway = isset( $_POST['wp_pinch_network_gateway_url'] ) ? esc_url_raw( wp_unslash( $_POST['wp_pinch_network_gateway_url'] ) ) : '';
		$token   = isset( $_POST['wp_pinch_network_api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_pinch_network_api_token'] ) ) : '';

		update_site_option( 'wp_pinch_network_gateway_url', $gateway );
		if ( '' !== $token ) {
			Settings::set_network_api_token( $token );
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', self::get_page_url() ) );
		exit;
	}

	/**
	 * URL of the network WP Pinch page.
	 *
	 * @return string
	 */
	public static function get_page_url(): string {
		return network_admin_url( 'settings.php?page=wp-pinch-network' );
	}

	/**
	 * Render the network settings page: network defaults form, sites list, cross-site audit.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-pinch' ) );
		}

		$gateway   = get_site_option( 'wp_pinch_network_gateway_url', '' );
		$token     = Settings::get_network_api_token();
		$has_token = '' !== $token;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Pinch (Network)', 'wp-pinch' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Network defaults saved.', 'wp-pinch' ); ?></p></div>
			<?php endif; ?>

			<div class="wp-pinch-card" style="max-width: 640px; margin-top: 1em;">
				<h2 class="wp-pinch-card__title"><?php esc_html_e( 'Network defaults', 'wp-pinch' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Sites can use these when "Use network default" is enabled in their Connection tab.', 'wp-pinch' ); ?></p>
				<form method="post" action="<?php echo esc_url( self::get_page_url() ); ?>">
					<?php wp_nonce_field( 'wp_pinch_network_settings' ); ?>
					<input type="hidden" name="wp_pinch_network_save" value="1" />
					<table class="form-table">
						<tr>
							<th scope="row"><label for="wp_pinch_network_gateway_url"><?php esc_html_e( 'Gateway URL', 'wp-pinch' ); ?></label></th>
							<td>
								<input type="url" id="wp_pinch_network_gateway_url" name="wp_pinch_network_gateway_url"
										value="<?php echo esc_attr( $gateway ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wp_pinch_network_api_token"><?php esc_html_e( 'API Token', 'wp-pinch' ); ?></label></th>
							<td>
								<input type="password" id="wp_pinch_network_api_token" name="wp_pinch_network_api_token"
										value="" class="regular-text" autocomplete="off" placeholder="<?php echo $has_token ? esc_attr__( '(leave blank to keep current)', 'wp-pinch' ) : ''; ?>" />
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save network defaults', 'wp-pinch' ); ?>" />
					</p>
				</form>
			</div>

			<div class="wp-pinch-card" style="max-width: 640px; margin-top: 1em;">
				<h2 class="wp-pinch-card__title"><?php esc_html_e( 'Sites', 'wp-pinch' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure WP Pinch per site from each site\'s admin.', 'wp-pinch' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Site', 'wp-pinch' ); ?></th>
							<th><?php esc_html_e( 'WP Pinch', 'wp-pinch' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$sites = get_sites(
							array(
								'number'  => 500,
								'orderby' => 'domain',
								'order'   => 'ASC',
							)
						);
						foreach ( $sites as $site ) :
							$site_id = (int) $site->blog_id;
							$blog    = get_blog_details( $site_id );
							$name    = $blog ? $blog->blogname : (string) $site_id;
							$url     = get_admin_url( $site_id, 'admin.php?page=wp-pinch' );
							?>
							<tr>
								<td><?php echo esc_html( $name ); ?> — <?php echo esc_html( $site->domain . $site->path ); ?></td>
								<td><a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open WP Pinch', 'wp-pinch' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="wp-pinch-card" style="max-width: 960px; margin-top: 1em;">
				<h2 class="wp-pinch-card__title"><?php esc_html_e( 'Cross-site audit (recent)', 'wp-pinch' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Last 50 ability executions across all sites.', 'wp-pinch' ); ?></p>
				<?php self::render_cross_site_audit( 50 ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Query audit log from each site and merge by date (most recent first).
	 *
	 * @param int $total_max Maximum total events to show.
	 */
	private static function render_cross_site_audit( int $total_max = 50 ): void {
		$sites    = get_sites(
			array(
				'number'  => 100,
				'orderby' => 'domain',
			)
		);
		$events   = array();
		$per_site = max( 10, (int) ceil( $total_max / max( 1, count( $sites ) ) ) );

		foreach ( $sites as $site ) {
			switch_to_blog( (int) $site->blog_id );
			$result = Audit_Table::query(
				array(
					'event_type' => 'ability_executed',
					'per_page'   => $per_site,
					'page'       => 1,
					'order'      => 'DESC',
				)
			);
			foreach ( $result['items'] as $item ) {
				$item['_blog_id']   = (int) $site->blog_id;
				$item['_blog_name'] = get_bloginfo( 'name' );
				$events[]           = $item;
			}
			restore_current_blog();
		}

		usort(
			$events,
			function ( $a, $b ) {
				$t1 = strtotime( $a['created_at'] ?? '0' );
				$t2 = strtotime( $b['created_at'] ?? '0' );
				return $t2 <=> $t1;
			}
		);
		$events = array_slice( $events, 0, $total_max );

		if ( empty( $events ) ) {
			echo '<p class="description">' . esc_html__( 'No ability executions in this period.', 'wp-pinch' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'wp-pinch' ); ?></th>
					<th><?php esc_html_e( 'Site', 'wp-pinch' ); ?></th>
					<th><?php esc_html_e( 'Ability', 'wp-pinch' ); ?></th>
					<th><?php esc_html_e( 'Source', 'wp-pinch' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $events as $item ) :
					$context = $item['context'] ?? array();
					$ability = isset( $context['ability'] ) && is_string( $context['ability'] ) ? $context['ability'] : '—';
					?>
					<tr>
						<td><?php echo esc_html( $item['created_at'] ?? '' ); ?></td>
						<td><?php echo esc_html( $item['_blog_name'] ?? (string) ( $item['_blog_id'] ?? '' ) ); ?></td>
						<td><code><?php echo esc_html( $ability ); ?></code></td>
						<td><?php echo esc_html( $item['source'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
