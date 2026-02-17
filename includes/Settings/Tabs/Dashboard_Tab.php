<?php
/**
 * AI Dashboard tab — feature summary, circuit breaker, audit count, ability count.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings\Tabs;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;
use WP_Pinch\Circuit_Breaker;
use WP_Pinch\Feature_Flags;
use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard tab.
 */
class Dashboard_Tab {

	/**
	 * Render the tab content.
	 */
	public static function render(): void {
		$flags       = Feature_Flags::get_all();
		$enabled     = array_filter( $flags );
		$state       = Circuit_Breaker::get_state();
		$retry_after = Circuit_Breaker::get_retry_after();
		$state_label = array(
			'closed'    => __( 'Closed (normal)', 'wp-pinch' ),
			'open'      => __( 'Claws up (failing fast)', 'wp-pinch' ),
			'half_open' => __( 'Half-Open (probing)', 'wp-pinch' ),
		);

		$recent_result = Audit_Table::query(
			array(
				'date_from' => gmdate( 'Y-m-d', time() - 7 * DAY_IN_SECONDS ),
				'per_page'  => 1,
				'page'      => 1,
			)
		);
		$audit_recent  = (int) $recent_result['total'];

		$ability_names = Abilities::get_ability_names();
		$ability_count = count( $ability_names );

		$governance_tasks = Governance::get_available_tasks();
		$governance_count = count( $governance_tasks );
		?>
		<h3><?php esc_html_e( 'AI Dashboard', 'wp-pinch' ); ?></h3>
		<p><?php esc_html_e( 'At-a-glance status for your WP Pinch setup.', 'wp-pinch' ); ?></p>

		<table class="widefat striped wp-pinch-dashboard-table" style="max-width: 32rem;">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Circuit breaker', 'wp-pinch' ); ?></th>
					<td>
						<strong><?php echo esc_html( $state_label[ $state ] ?? $state ); ?></strong>
						<?php if ( $retry_after > 0 ) : ?>
							<span class="description">
								<?php
								printf(
									/* translators: %d: seconds until retry */
									esc_html__( 'Retry in %d seconds.', 'wp-pinch' ),
									absint( $retry_after )
								);
								?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Feature flags enabled', 'wp-pinch' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: 1: number of enabled flags, 2: total number of flags */
							esc_html__( '%1$d of %2$d', 'wp-pinch' ),
							count( $enabled ),
							count( $flags )
						);
						?>
						<span class="description"> — <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-pinch&tab=features' ) ); ?>"><?php esc_html_e( 'Manage', 'wp-pinch' ); ?></a></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Audit events (last 7 days)', 'wp-pinch' ); ?></th>
					<td>
						<?php echo absint( $audit_recent ); ?>
						<span class="description"> — <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-pinch&tab=audit' ) ); ?>"><?php esc_html_e( 'View log', 'wp-pinch' ); ?></a></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Abilities available', 'wp-pinch' ); ?></th>
					<td>
						<?php echo absint( $ability_count ); ?>
						<span class="description"> — <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-pinch&tab=abilities' ) ); ?>"><?php esc_html_e( 'Configure', 'wp-pinch' ); ?></a></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Governance tasks', 'wp-pinch' ); ?></th>
					<td>
						<?php echo absint( $governance_count ); ?>
						<span class="description"> — <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-pinch&tab=governance' ) ); ?>"><?php esc_html_e( 'Schedule', 'wp-pinch' ); ?></a></span>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}
