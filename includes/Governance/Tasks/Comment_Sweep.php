<?php
/**
 * Comment sweep governance task.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Governance\Tasks;

use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Comment sweep — pending moderation and spam count.
 */
class Comment_Sweep {

	/**
	 * Run the task.
	 */
	public static function run(): void {
		$findings = Governance::get_comment_sweep_findings();
		$pending  = $findings['pending_comments'] ?? array();
		$spam     = $findings['spam_count'] ?? 0;
		if ( empty( $pending ) && 0 === $spam ) {
			return;
		}
		Governance::deliver_findings(
			'comment_sweep',
			$findings,
			sprintf(
				'%d comments awaiting moderation, %d in spam.',
				count( $pending ),
				$spam
			)
		);
	}
}
