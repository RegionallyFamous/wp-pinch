<?php
/**
 * Spaced resurfacing governance task.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Governance\Tasks;

use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Spaced resurfacing — notes not updated in N days.
 */
class Spaced_Resurfacing {

	/**
	 * Run the task.
	 */
	public static function run(): void {
		$days     = (int) apply_filters( 'wp_pinch_spaced_resurfacing_days', 30 );
		$findings = Governance::get_spaced_resurfacing_findings( $days, '', '', 100 );
		if ( empty( $findings ) ) {
			return;
		}
		Governance::deliver_findings(
			'spaced_resurfacing',
			$findings,
			sprintf(
				/* translators: %1$d: number of posts, %2$d: days */
				__( '%1$d posts have not been updated in over %2$d days.', 'wp-pinch' ),
				count( $findings ),
				$days
			)
		);
	}
}
