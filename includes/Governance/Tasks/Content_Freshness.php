<?php
/**
 * Content freshness governance task.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Governance\Tasks;

use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Content freshness — flag posts not updated in threshold days.
 */
class Content_Freshness {

	/**
	 * Run the task.
	 */
	public static function run(): void {
		$findings = Governance::get_content_freshness_findings();
		if ( empty( $findings ) ) {
			return;
		}
		$threshold_days = (int) apply_filters( 'wp_pinch_freshness_threshold', 180 );
		Governance::deliver_findings(
			'content_freshness',
			$findings,
			sprintf(
				'%d posts have not been updated in over %d days.',
				count( $findings ),
				$threshold_days
			)
		);
	}
}
