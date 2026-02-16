<?php
/**
 * REST authentication and permission callbacks.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Feature_Flags;
use WP_Pinch\Settings;

/**
 * Auth helpers for REST endpoints.
 */
class Auth {

	/**
	 * Permission callback — require edit_posts.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'capability_denied',
				__( 'You do not have permission to use WP Pinch.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Permission callback for the incoming webhook endpoint.
	 *
	 * Validates Bearer token, X-OpenClaw-Token, or HMAC-SHA256 signature.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return true|\WP_Error
	 */
	public static function check_hook_token( \WP_REST_Request $request ) {
		$api_token = Settings::get_api_token();

		if ( empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WP Pinch API token is not configured.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		$auth_header = $request->get_header( 'authorization' );
		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			if ( hash_equals( $api_token, $matches[1] ) ) {
				return true;
			}
		}

		$openclaw_token = $request->get_header( 'x_openclaw_token' );
		if ( $openclaw_token && hash_equals( $api_token, $openclaw_token ) ) {
			return true;
		}

		if ( Feature_Flags::is_enabled( 'webhook_signatures' ) ) {
			$signature = $request->get_header( 'x_wp_pinch_signature' );
			$timestamp = $request->get_header( 'x_wp_pinch_timestamp' );
			if ( $signature && $timestamp ) {
				if ( ! ctype_digit( $timestamp ) ) {
					return new \WP_Error(
						'invalid_timestamp',
						__( 'Invalid signature timestamp.', 'wp-pinch' ),
						array( 'status' => 400 )
					);
				}
				$body     = $request->get_body();
				$expected = 'v1=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $api_token );
				if ( hash_equals( $expected, $signature ) ) {
					if ( abs( time() - (int) $timestamp ) <= 300 ) {
						return true;
					}
				}
			}
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Invalid or missing authentication token.', 'wp-pinch' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Permission callback for Web Clipper capture endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return true|\WP_Error
	 */
	public static function check_capture_token( \WP_REST_Request $request ) {
		$stored = get_option( 'wp_pinch_capture_token', '' );
		if ( empty( $stored ) ) {
			return new \WP_Error(
				'capture_not_configured',
				__( 'Web Clipper capture token is not configured.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		$token = $request->get_param( 'token' );
		if ( is_string( $token ) && hash_equals( $stored, $token ) ) {
			return true;
		}

		$header = $request->get_header( 'x_wp_pinch_capture_token' );
		if ( '' !== $header && hash_equals( $stored, $header ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Invalid or missing capture token.', 'wp-pinch' ),
			array( 'status' => 401 )
		);
	}
}
