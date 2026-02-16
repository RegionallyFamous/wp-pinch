<?php
/**
 * Tide report governance task — daily digest bundle.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Governance\Tasks;

use WP_Pinch\Feature_Flags;
use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Tide report — bundle findings into one webhook.
 */
class Tide_Report {

	/**
	 * Run the task.
	 */
	public static function run(): void {
		$bundle = array();

		$freshness = Governance::get_content_freshness_findings();
		if ( ! empty( $freshness ) ) {
			$bundle['content_freshness'] = $freshness;
		}

		$seo = Governance::get_seo_health_findings();
		if ( ! empty( $seo ) ) {
			$bundle['seo_health'] = $seo;
		}

		$comments = Governance::get_comment_sweep_findings();
		if ( ! empty( $comments['pending_comments'] ) || ( isset( $comments['spam_count'] ) && $comments['spam_count'] > 0 ) ) {
			$bundle['comment_sweep'] = $comments;
		}

		if ( Feature_Flags::is_enabled( 'ghost_writer' ) ) {
			$drafts = Governance::get_draft_necromancer_findings();
			if ( ! empty( $drafts ) ) {
				$bundle['draft_necromancer'] = $drafts;
			}
		}

		$spaced = Governance::get_spaced_resurfacing_findings( 30, '', '', 50 );
		if ( ! empty( $spaced ) ) {
			$bundle['spaced_resurfacing'] = $spaced;
		}

		if ( empty( $bundle ) ) {
			return;
		}

		$parts = array();
		if ( ! empty( $bundle['content_freshness'] ) ) {
			$parts[] = count( $bundle['content_freshness'] ) . ' stale posts';
		}
		if ( ! empty( $bundle['seo_health'] ) ) {
			$parts[] = count( $bundle['seo_health'] ) . ' SEO issues';
		}
		if ( ! empty( $bundle['comment_sweep'] ) ) {
			$pending = count( $bundle['comment_sweep']['pending_comments'] ?? array() );
			$spam    = $bundle['comment_sweep']['spam_count'] ?? 0;
			$parts[] = $pending . ' pending, ' . $spam . ' spam';
		}
		if ( ! empty( $bundle['draft_necromancer'] ) ) {
			$parts[] = count( $bundle['draft_necromancer'] ) . ' drafts worth resurrecting';
		}
		if ( ! empty( $bundle['spaced_resurfacing'] ) ) {
			$parts[] = count( $bundle['spaced_resurfacing'] ) . ' notes to resurface';
		}
		$summary = __( 'Tide Report: ', 'wp-pinch' ) . implode( '; ', $parts ) . '.';

		Governance::deliver_findings( 'tide_report', $bundle, $summary );
	}
}
