<?php
/**
 * Usage tab — ability execution stats from audit log.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings\Tabs;

use WP_Pinch\Audit_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Usage tab.
 */
class Usage_Tab {

	/**
	 * Render the tab content.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$days       = 30;
		$stats      = Audit_Table::get_ability_usage_stats( $days );
		$by_ability = $stats['by_ability'];
		$by_day     = $stats['by_day'];
		$total      = $stats['total'];

		krsort( $by_day, SORT_STRING );
		?>
		<h3><?php esc_html_e( 'Ability usage', 'wp-pinch' ); ?></h3>
		<p class="description">
		<?php
		/* translators: %d: number of days */
		echo esc_html( sprintf( __( 'Last %d days (from audit log).', 'wp-pinch' ), $days ) );
		?>
		</p>

		<div class="wp-pinch-card" style="margin-top:1em;">
			<h4 class="wp-pinch-card__title"><?php esc_html_e( 'Total executions', 'wp-pinch' ); ?></h4>
			<p style="font-size:1.5em; margin:0.5em 0;"><?php echo esc_html( (string) $total ); ?></p>
		</div>

		<div class="wp-pinch-card" style="margin-top:1em;">
			<h4 class="wp-pinch-card__title"><?php esc_html_e( 'Top abilities', 'wp-pinch' ); ?></h4>
			<?php if ( ! empty( $by_ability ) ) : ?>
				<table class="widefat striped" style="margin-top:0.5em;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ability', 'wp-pinch' ); ?></th>
							<th><?php esc_html_e( 'Count', 'wp-pinch' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $by_ability as $ability_name => $count ) : ?>
							<tr>
								<td><code><?php echo esc_html( $ability_name ); ?></code></td>
								<td><?php echo esc_html( (string) $count ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No ability executions in this period.', 'wp-pinch' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="wp-pinch-card" style="margin-top:1em;">
			<h4 class="wp-pinch-card__title"><?php esc_html_e( 'Executions by day', 'wp-pinch' ); ?></h4>
			<?php if ( ! empty( $by_day ) ) : ?>
				<table class="widefat striped" style="margin-top:0.5em;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'wp-pinch' ); ?></th>
							<th><?php esc_html_e( 'Count', 'wp-pinch' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $by_day as $date => $count ) : ?>
							<tr>
								<td><?php echo esc_html( $date ); ?></td>
								<td><?php echo esc_html( (string) $count ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No data for this period.', 'wp-pinch' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
