<?php
/**
 * Webhooks tab — event selection and endpoint/wake mode.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings\Tabs;

use WP_Pinch\Webhook_Dispatcher;

defined( 'ABSPATH' ) || exit;

/**
 * Webhooks tab.
 */
class Webhooks_Tab {

	/**
	 * Render the tab content.
	 */
	public static function render(): void {
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
}
