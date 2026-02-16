<?php
/**
 * REST handlers for Web Clipper and PinchDrop capture endpoints.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;
use WP_Pinch\Feature_Flags;
use WP_Pinch\OpenClaw_Role;
use WP_Pinch\Plugin;
use WP_Pinch\Rest_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Web Clipper and PinchDrop capture REST handlers.
 */
class Capture {

	/**
	 * Handle Web Clipper one-shot capture (token-protected).
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_web_clipper_capture( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}
		$rate_key = 'wp_pinch_clipper_rate_' . substr( hash_hmac( 'sha256', Helpers::get_client_ip(), wp_salt() ), 0, 16 );
		$rate     = (int) get_transient( $rate_key );
		if ( $rate >= 30 ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many capture requests. Please retry shortly.', 'wp-pinch' ),
				),
				429
			);
			$response->header( 'Retry-After', '60' );
			return $response;
		}
		set_transient( $rate_key, $rate + 1, 60 );
		$text  = trim( (string) $request->get_param( 'text' ) );
		$url   = is_string( $request->get_param( 'url' ) ) ? $request->get_param( 'url' ) : '';
		$title = is_string( $request->get_param( 'title' ) ) ? trim( $request->get_param( 'title' ) ) : '';
		if ( '' === $title && '' !== $url ) {
			$host  = wp_parse_url( $url, PHP_URL_HOST );
			$title = ( is_string( $host ) && '' !== $host ) ? $host : __( 'Captured link', 'wp-pinch' );
		}
		if ( '' === $title ) {
			$title = _x( 'Captured note', 'Web Clipper default post title', 'wp-pinch' );
		}
		$content = $text;
		if ( '' !== $url ) {
			$content = '<p><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . "</a></p>\n\n" . $content;
		}
		$admins    = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$author_id = ! empty( $admins ) ? (int) $admins[0] : 0;
		if ( 0 === $author_id ) {
			return new \WP_Error(
				'no_author',
				__( 'No administrator found to create the post.', 'wp-pinch' ),
				array( 'status' => 500 )
			);
		}
		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_author'  => $author_id,
				'post_type'    => 'post',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error(
				'create_failed',
				$post_id->get_error_message(),
				array( 'status' => 500 )
			);
		}
		$trace_id = Rest_Controller::get_trace_id();
		Audit_Table::insert(
			'web_clipper_capture',
			'rest',
			sprintf( 'Web Clipper capture created post %d.', $post_id ),
			array_merge(
				array(
					'post_id' => $post_id,
					'url'     => $url,
				),
				array_filter( array( 'trace_id' => $trace_id ) )
			)
		);
		return new \WP_REST_Response(
			array(
				'status'   => 'ok',
				'post_id'  => $post_id,
				'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			),
			201
		);
	}

	/**
	 * Handle PinchDrop capture requests from OpenClaw channels.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_pinchdrop_capture( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}
		$feature_enabled = Feature_Flags::is_enabled( 'pinchdrop_engine' );
		$setting_enabled = (bool) get_option( 'wp_pinch_pinchdrop_enabled', false );
		if ( ! $feature_enabled || ! $setting_enabled ) {
			return new \WP_Error(
				'pinchdrop_disabled',
				__( 'PinchDrop capture is currently disabled.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}
		$allowed_sources_raw = (string) get_option( 'wp_pinch_pinchdrop_allowed_sources', '' );
		$allowed_sources     = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $allowed_sources_raw ) ) ) );
		$source              = sanitize_key( (string) $request->get_param( 'source' ) );
		if ( ! empty( $allowed_sources ) && ! in_array( $source, $allowed_sources, true ) ) {
			return new \WP_Error(
				'invalid_source',
				__( 'Source is not allowlisted for PinchDrop.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}
		$rate_key = 'wp_pinch_pdrop_rate_' . substr( hash_hmac( 'sha256', $source . '|' . Helpers::get_client_ip(), wp_salt() ), 0, 16 );
		$rate     = (int) get_transient( $rate_key );
		if ( $rate >= 20 ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many capture requests. Please retry shortly.', 'wp-pinch' ),
				),
				429
			);
			$response->header( 'Retry-After', '60' );
			return $response;
		}
		set_transient( $rate_key, $rate + 1, 60 );
		$request_id = sanitize_text_field( (string) $request->get_param( 'request_id' ) );
		$idem_key   = '';
		if ( '' !== $request_id ) {
			$idem_key = 'wp_pinch_pdrop_idem_' . substr( hash_hmac( 'sha256', $request_id, wp_salt() ), 0, 32 );
			$cached   = get_transient( $idem_key );
			if ( is_array( $cached ) ) {
				$cached['deduplicated'] = true;
				return new \WP_REST_Response( $cached, 200 );
			}
		}
		$options         = $request->get_param( 'options' );
		$options         = is_array( $options ) ? $options : array();
		$default_outputs = get_option( 'wp_pinch_pinchdrop_default_outputs', array( 'post', 'product_update', 'changelog', 'social' ) );
		if ( ! is_array( $default_outputs ) || empty( $default_outputs ) ) {
			$default_outputs = array( 'post', 'product_update', 'changelog', 'social' );
		}
		$payload = array(
			'source_text'   => sanitize_textarea_field( (string) $request->get_param( 'text' ) ),
			'source'        => $source,
			'author'        => sanitize_text_field( (string) $request->get_param( 'author' ) ),
			'request_id'    => $request_id,
			'tone'          => sanitize_text_field( (string) ( $options['tone'] ?? '' ) ),
			'audience'      => sanitize_text_field( (string) ( $options['audience'] ?? '' ) ),
			'output_types'  => array_map( 'sanitize_key', (array) ( $options['output_types'] ?? $default_outputs ) ),
			'save_as_draft' => isset( $options['save_as_draft'] ) ? (bool) $options['save_as_draft'] : (bool) get_option( 'wp_pinch_pinchdrop_auto_save_drafts', true ),
			'save_as_note'  => ! empty( $options['save_as_note'] ),
		);
		$result  = self::execute_ability_as_admin( 'wp-pinch/pinchdrop-generate', $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response_data = array(
			'status'       => 'ok',
			'request_id'   => $request_id,
			'source'       => $source,
			'deduplicated' => false,
			'result'       => $result,
		);
		if ( '' !== $idem_key ) {
			set_transient( $idem_key, $response_data, 15 * MINUTE_IN_SECONDS );
		}
		$trace_id = Rest_Controller::get_trace_id();
		Audit_Table::insert(
			'pinchdrop_capture',
			'webhook',
			sprintf( 'PinchDrop capture accepted from source "%s".', $source ),
			array_merge(
				array(
					'source'     => $source,
					'request_id' => $request_id,
				),
				array_filter( array( 'trace_id' => $trace_id ) )
			)
		);
		return new \WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Execute an ability in an administrator context for trusted system hooks.
	 *
	 * @param string               $ability_name Ability name.
	 * @param array<string, mixed> $params       Ability params.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function execute_ability_as_admin( string $ability_name, array $params ) {
		if ( ! function_exists( 'wp_execute_ability' ) ) {
			return new \WP_Error(
				'abilities_unavailable',
				__( 'WordPress Abilities API is not available.', 'wp-pinch' ),
				array( 'status' => 500 )
			);
		}
		$ability_names = Abilities::get_ability_names();
		if ( ! in_array( $ability_name, $ability_names, true ) ) {
			return new \WP_Error(
				'unknown_ability',
				__( 'Requested ability is not registered.', 'wp-pinch' ),
				array( 'status' => 404 )
			);
		}
		$disabled = get_option( 'wp_pinch_disabled_abilities', array() );
		if ( is_array( $disabled ) && in_array( $ability_name, $disabled, true ) ) {
			return new \WP_Error(
				'ability_disabled',
				__( 'Requested ability is currently disabled.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}
		$previous_user  = get_current_user_id();
		$execution_user = OpenClaw_Role::get_execution_user_id();
		if ( 0 === $execution_user ) {
			return new \WP_Error(
				'no_execution_user',
				__( 'No user found to execute the ability. Create an OpenClaw agent user or ensure an administrator exists.', 'wp-pinch' ),
				array( 'status' => 500 )
			);
		}
		wp_set_current_user( $execution_user );
		$result = wp_execute_ability( $ability_name, $params );
		wp_set_current_user( $previous_user );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'ability_error',
				$result->get_error_message(),
				array( 'status' => 422 )
			);
		}
		return $result;
	}
}
