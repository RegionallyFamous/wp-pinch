<?php
/**
 * REST handler for Ghost Writer endpoint.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

use WP_Pinch\Ghost_Writer;
use WP_Pinch\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Ghost Writer REST handler.
 */
class Ghostwrite {

	/**
	 * Handle Ghost Writer requests (list drafts or trigger ghostwriting).
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_ghostwrite( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}
		if ( ! Helpers::check_rate_limit() ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
				),
				429
			);
			$response->header( 'Retry-After', '60' );
			return $response;
		}
		$action  = $request->get_param( 'action' );
		$post_id = (int) $request->get_param( 'post_id' );
		if ( 'list' === $action ) {
			$user_id = get_current_user_id();
			$scope   = current_user_can( 'edit_others_posts' ) ? 0 : $user_id;
			$drafts  = Ghost_Writer::assess_drafts( $scope );
			if ( empty( $drafts ) ) {
				return new \WP_REST_Response(
					array(
						'reply' => __( 'No abandoned drafts found. Your draft graveyard is empty — either you finish what you start, or you never start at all.', 'wp-pinch' ),
					),
					200
				);
			}
			$lines = array();
			foreach ( array_slice( $drafts, 0, 10 ) as $draft ) {
				$lines[] = sprintf(
					'#%d — "%s" (%d words, %d%% done, %d days old, score: %d)',
					$draft['post_id'],
					$draft['title'],
					$draft['word_count'],
					$draft['estimated_completion'],
					$draft['days_abandoned'],
					$draft['resurrection_score']
				);
			}
			$reply = sprintf(
				/* translators: %d: number of drafts */
				__( "Found %d abandoned drafts:\n\n", 'wp-pinch' ),
				count( $drafts )
			) . implode( "\n", $lines );
			if ( count( $drafts ) > 10 ) {
				$reply .= "\n\n" . sprintf(
					/* translators: %d: number of additional drafts not shown in the list */
					__( '...and %d more. Use /ghostwrite [post_id] to resurrect one.', 'wp-pinch' ),
					count( $drafts ) - 10
				);
			} else {
				$reply .= "\n\n" . __( 'Use /ghostwrite [post_id] to resurrect one.', 'wp-pinch' );
			}
			return new \WP_REST_Response( array( 'reply' => $reply ), 200 );
		}
		if ( 'write' === $action ) {
			if ( $post_id < 1 ) {
				return new \WP_Error(
					'missing_post_id',
					__( 'Please specify a post ID. Usage: /ghostwrite 123', 'wp-pinch' ),
					array( 'status' => 400 )
				);
			}
			$result = Ghost_Writer::ghostwrite( $post_id, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$reply = sprintf(
				/* translators: 1: post title, 2: edit URL */
				__( "Draft \"%1\$s\" has been resurrected. The ghost writer has spoken in your voice.\n\nEdit it here: %2\$s", 'wp-pinch' ),
				$result['title'],
				$result['edit_url']
			);
			return new \WP_REST_Response( array( 'reply' => $reply ), 200 );
		}
		return new \WP_Error(
			'invalid_action',
			__( 'Invalid action. Use "list" or "write".', 'wp-pinch' ),
			array( 'status' => 400 )
		);
	}
}
