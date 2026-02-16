<?php
/**
 * Abilities tab — enable/disable individual abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings\Tabs;

use WP_Pinch\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Abilities tab.
 */
class Abilities_Tab {

	/**
	 * Render the tab content.
	 */
	public static function render(): void {
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
}
