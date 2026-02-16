<?php
/**
 * REST handler for Molt endpoint.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

use WP_Pinch\Molt as Molt_Service;
use WP_Pinch\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Molt REST handler.
 */
class Molt {

	/**
	 * Handle Molt requests — repackage post into multiple output formats.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_molt( \WP_REST_Request $request ) {
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
		$post_id      = (int) $request->get_param( 'post_id' );
		$output_types = $request->get_param( 'output_types' );
		$output_types = is_array( $output_types ) ? $output_types : array();
		if ( $post_id < 1 ) {
			return new \WP_Error(
				'missing_post_id',
				__( 'Please specify a post ID. Usage: /molt 123', 'wp-pinch' ),
				array( 'status' => 400 )
			);
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new \WP_Error(
				'forbidden',
				__( 'You do not have permission to read this post.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}
		$result = Molt_Service::molt( $post_id, $output_types );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$reply = Molt_Service::format_for_chat( $result );
		return new \WP_REST_Response(
			array(
				'output' => $result,
				'reply'  => $reply,
			),
			200
		);
	}
}
