<?php
/**
 * REST handlers for status, health, and list-abilities endpoints.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

use WP_Pinch\Circuit_Breaker;
use WP_Pinch\Plugin;
use WP_Pinch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Status, health, and abilities list REST handlers.
 */
class Status {

	/**
	 * Handle status check — ping the OpenClaw gateway.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function handle_status( \WP_REST_Request $request ): \WP_REST_Response {
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
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		$result      = array(
			'plugin_version' => WP_PINCH_VERSION,
			'configured'     => ! empty( $gateway_url ) && ! empty( $api_token ),
			'mcp_endpoint'   => rest_url( 'wp-pinch/mcp' ),
			'rate_limit'     => array(
				'limit' => max( 1, (int) get_option( 'wp_pinch_rate_limit', Helpers::DEFAULT_RATE_LIMIT ) ),
			),
			'circuit'        => array(
				'state'           => Circuit_Breaker::get_state(),
				'retry_after'     => Circuit_Breaker::get_retry_after(),
				'last_failure_at' => Circuit_Breaker::get_last_failure_at(),
			),
			'gateway'        => array( 'connected' => false ),
		);
		if ( current_user_can( 'manage_options' ) ) {
			$result['gateway']['url'] = $gateway_url ? trailingslashit( $gateway_url ) : '';
			$result['diagnostics']    = Helpers::get_diagnostics();
		}
		if ( $result['configured'] ) {
			$status_url = trailingslashit( $gateway_url ) . 'api/v1/status';
			if ( wp_http_validate_url( $status_url ) ) {
				$response = wp_safe_remote_get(
					$status_url,
					array(
						'timeout' => 5,
						'headers' => array( 'Authorization' => 'Bearer ' . $api_token ),
					)
				);
			} else {
				$response = new \WP_Error( 'invalid_gateway', __( 'Gateway URL failed security validation.', 'wp-pinch' ) );
			}
			if ( ! is_wp_error( $response ) ) {
				$code                           = wp_remote_retrieve_response_code( $response );
				$result['gateway']['connected'] = ( $code >= 200 && $code < 300 );
				$result['gateway']['status']    = $code;
			}
		}
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Lightweight public health check — no authentication required.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_health(): \WP_REST_Response {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'disabled',
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
					'version' => WP_PINCH_VERSION,
				),
				503
			);
		}
		$configured    = ! empty( get_option( 'wp_pinch_gateway_url', '' ) ) && ! empty( Settings::get_api_token() );
		$circuit_state = Circuit_Breaker::get_state();
		$retry_after   = Circuit_Breaker::get_retry_after();
		$result        = array(
			'status'     => 'ok',
			'version'    => WP_PINCH_VERSION,
			'configured' => $configured,
			'rate_limit' => array(
				'limit' => max( 1, (int) get_option( 'wp_pinch_rate_limit', Helpers::DEFAULT_RATE_LIMIT ) ),
			),
			'circuit'    => array(
				'state'           => $circuit_state,
				'retry_after'     => $retry_after,
				'last_failure_at' => Circuit_Breaker::get_last_failure_at(),
			),
			'timestamp'  => gmdate( 'c' ),
		);
		$response      = new \WP_REST_Response( $result, 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		return $response;
	}

	/**
	 * List WP Pinch abilities for discovery.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_list_abilities(): \WP_REST_Response {
		$names = function_exists( 'wp_pinch_get_ability_names' ) ? wp_pinch_get_ability_names() : array();
		$list  = array();
		foreach ( $names as $name ) {
			$list[] = array( 'name' => $name );
		}
		$body = array(
			'abilities' => $list,
			'site'      => Helpers::get_site_manifest(),
		);
		return new \WP_REST_Response( $body, 200 );
	}
}
