<?php
/**
 * Auto-generate alt text for images on upload.
 *
 * When the auto_alt_text feature flag is enabled, new image uploads
 * without alt text get a suggestion from the AI gateway and save to
 * _wp_attachment_image_alt.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Alt text generator on attachment upload.
 */
class Alt_Text_Generator {

	/**
	 * Action hook for async alt generation (Action Scheduler).
	 */
	const ASYNC_ACTION = 'wp_pinch_generate_alt_text';

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		if ( ! Feature_Flags::is_enabled( 'auto_alt_text' ) ) {
			return;
		}

		add_action( 'add_attachment', array( __CLASS__, 'on_add_attachment' ), 10, 1 );
		add_action( self::ASYNC_ACTION, array( __CLASS__, 'generate_alt_for_attachment' ), 10, 1 );
	}

	/**
	 * When a new attachment is added, queue or run alt text generation for images.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function on_add_attachment( int $attachment_id ): void {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return;
		}

		$mime = $post->post_mime_type;
		if ( ! str_starts_with( $mime, 'image/' ) ) {
			return;
		}

		$existing = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( '' !== $existing && is_string( $existing ) ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				self::ASYNC_ACTION,
				array( 'attachment_id' => $attachment_id ),
				'wp-pinch'
			);
			return;
		}

		self::generate_alt_for_attachment( $attachment_id );
	}

	/**
	 * Generate alt text for an attachment and save to meta.
	 *
	 * @param int|array $attachment_id_or_args Attachment ID, or args array from Action Scheduler (with 'attachment_id' key).
	 */
	public static function generate_alt_for_attachment( $attachment_id_or_args ): void {
		$attachment_id = is_array( $attachment_id_or_args ) ? (int) ( $attachment_id_or_args['attachment_id'] ?? 0 ) : (int) $attachment_id_or_args;
		if ( $attachment_id < 1 ) {
			return;
		}

		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return;
		}

		$existing = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( '' !== $existing && is_string( $existing ) ) {
			return;
		}

		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return;
		}

		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			return;
		}

		$image_url = wp_get_attachment_url( $attachment_id );
		$title     = $post->post_title;
		$caption   = $post->post_excerpt;

		$prompt = sprintf(
			'Generate a short, accessible alt text (under 125 characters) for this image. ' .
			'Describe what is shown for screen readers. Be concise.' . "\n\n" .
			'Image URL: %s' . "\n" .
			'Title: %s' . "\n" .
			'Caption: %s' . "\n\n" .
			'Return ONLY the alt text, no quotes or explanation.',
			$image_url,
			$title,
			$caption
		);

		$payload = array(
			'message'    => $prompt,
			'name'       => 'WordPress Alt Text',
			'sessionKey' => 'wp-pinch-alt-' . $attachment_id,
			'wakeMode'   => 'now',
		);

		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}

		$hooks_url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $hooks_url ) ) {
			return;
		}

		$response = wp_safe_remote_post(
			$hooks_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Circuit_Breaker::record_failure();
			return;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			Circuit_Breaker::record_failure();
			return;
		}

		Circuit_Breaker::record_success();

		$body  = wp_remote_retrieve_body( $response );
		$data  = json_decode( $body, true );
		$reply = $data['response'] ?? $data['message'] ?? '';
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return;
		}

		$alt = sanitize_text_field( trim( $reply ) );
		if ( '' === $alt ) {
			return;
		}

		// Cap length for alt text.
		if ( mb_strlen( $alt ) > 125 ) {
			$alt = mb_substr( $alt, 0, 122 ) . '...';
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );

		Audit_Table::insert(
			'alt_text_generated',
			'ability',
			sprintf(
				/* translators: %d: attachment ID */
				__( 'Alt text generated for attachment #%d.', 'wp-pinch' ),
				$attachment_id
			),
			array( 'attachment_id' => $attachment_id )
		);
	}
}
