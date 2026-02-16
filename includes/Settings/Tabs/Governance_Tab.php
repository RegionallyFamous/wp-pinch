<?php
/**
 * Governance tab — delivery mode and task toggles.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings\Tabs;

use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Governance tab.
 */
class Governance_Tab {

	/**
	 * Render the tab content.
	 */
	public static function render(): void {
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
}
