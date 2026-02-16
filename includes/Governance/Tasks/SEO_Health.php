<?php
/**
 * SEO health governance task.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Governance\Tasks;

use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * SEO health — check titles, alt text, content length.
 */
class SEO_Health {

	/**
	 * Run the task.
	 */
	public static function run(): void {
		$findings = Governance::get_seo_health_findings();
		if ( empty( $findings ) ) {
			return;
		}
		Governance::deliver_findings(
			'seo_health',
			$findings,
			sprintf(
				'%d posts/pages have SEO issues.',
				count( $findings )
			)
		);
	}
}
