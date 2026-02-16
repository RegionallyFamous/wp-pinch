<?php
/**
 * Draft necromancer governance task.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Governance\Tasks;

use WP_Pinch\Feature_Flags;
use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Draft necromancer — surface abandoned drafts worth resurrecting.
 */
class Draft_Necromancer {

	/**
	 * Run the task.
	 */
	public static function run(): void {
		if ( ! Feature_Flags::is_enabled( 'ghost_writer' ) ) {
			return;
		}
		$findings = Governance::get_draft_necromancer_findings();
		if ( empty( $findings ) ) {
			return;
		}
		Governance::deliver_findings(
			'draft_necromancer',
			$findings,
			sprintf(
				/* translators: %d: number of abandoned drafts */
				__( '%d abandoned drafts found worth resurrecting. The draft graveyard has company.', 'wp-pinch' ),
				count( $findings )
			)
		);
	}
}
