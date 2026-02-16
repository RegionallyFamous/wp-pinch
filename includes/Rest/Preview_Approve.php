<?php
/**
 * REST handler for preview/approve endpoint (draft-first workflow).
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

use WP_Pinch\Audit_Table;
use WP_Pinch\OpenClaw_Role;
use WP_Pinch\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Preview approve REST handler.
 */
class Preview_Approve {

	/**
	 * Approve and publish a draft post from preview.
	 *
	 * @param \WP_REST_Request $request REST request with post_id.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_preview_approve( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}
		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || wp_is_post_revision( $post ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'not_found',
					'message' => __( 'Post not found.', 'wp-pinch' ),
				),
				404
			);
		}
		$allowed_statuses = array( 'draft', 'pending', 'future', 'private' );
		if ( ! in_array( $post->post_status, $allowed_statuses, true ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'invalid_status',
					'message' => __( 'Post is already published or cannot be approved.', 'wp-pinch' ),
				),
				400
			);
		}
		$execution_user = OpenClaw_Role::get_execution_user_id();
		if ( 0 === $execution_user ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'no_execution_user',
					'message' => __( 'No user found to perform the action.', 'wp-pinch' ),
				),
				503
			);
		}
		$previous_user = get_current_user_id();
		wp_set_current_user( $execution_user );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_set_current_user( $previous_user );
			return new \WP_REST_Response(
				array(
					'code'    => 'forbidden',
					'message' => __( 'You do not have permission to publish this post.', 'wp-pinch' ),
				),
				403
			);
		}
		$updated = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);
		wp_set_current_user( $previous_user );
		if ( is_wp_error( $updated ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'update_failed',
					'message' => $updated->get_error_message(),
				),
				500
			);
		}
		Audit_Table::insert(
			'preview_approved',
			'rest',
			sprintf( 'Post #%d approved and published from preview.', $post_id ),
			array( 'post_id' => $post_id )
		);
		return new \WP_REST_Response(
			array(
				'status'    => 'ok',
				'post_id'   => $post_id,
				'url'       => get_permalink( $post_id ),
				'published' => true,
			),
			200
		);
	}
}
