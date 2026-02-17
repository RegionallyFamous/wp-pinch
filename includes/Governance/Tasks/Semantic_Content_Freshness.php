<?php
/**
 * Semantic content freshness governance task.
 *
 * Uses the AI gateway to assess whether sampled posts are factually stale
 * (outdated dates, superseded statistics, obsolete advice).
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Governance\Tasks;

use WP_Pinch\Circuit_Breaker;
use WP_Pinch\Feature_Flags;
use WP_Pinch\Governance;
use WP_Pinch\Prompt_Sanitizer;
use WP_Pinch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Semantic content freshness — AI-powered staleness check.
 */
class Semantic_Content_Freshness {

	/**
	 * Max posts to sample per run.
	 */
	const SAMPLE_SIZE = 10;

	/**
	 * Max content length sent to gateway per post (chars).
	 */
	const CONTENT_MAX_CHARS = 1500;

	/**
	 * Run the task.
	 */
	public static function run(): void {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return;
		}
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => self::SAMPLE_SIZE,
				'orderby'        => 'rand',
				'no_found_rows'  => true,
			)
		);

		$findings  = array();
		$hooks_url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $hooks_url ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			$content = wp_strip_all_tags( $post->post_content );
			$content = mb_strlen( $content ) > self::CONTENT_MAX_CHARS
				? mb_substr( $content, 0, self::CONTENT_MAX_CHARS ) . '...'
				: $content;
			if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
				$content = Prompt_Sanitizer::sanitize( $content );
			}

			$prompt = sprintf(
				'Is this content factually stale? Consider: outdated dates, superseded statistics, obsolete advice, or time-sensitive claims that may no longer hold.' . "\n\n" .
				'TITLE: %s' . "\n\n" .
				'CONTENT EXCERPT:' . "\n%s\n\n" .
				'Return ONLY a JSON object with two keys: "stale" (boolean) and "reason" (string, brief explanation if stale). No markdown.',
				$post->post_title,
				$content
			);

			$payload  = array(
				'message'    => $prompt,
				'name'       => 'WP Pinch Semantic Freshness',
				'sessionKey' => 'wp-pinch-semantic-freshness-' . $post->ID,
				'wakeMode'   => 'now',
			);
			$agent_id = get_option( 'wp_pinch_agent_id', '' );
			if ( '' !== $agent_id ) {
				$payload['agentId'] = sanitize_text_field( $agent_id );
			}

			$response = wp_safe_remote_post(
				$hooks_url,
				array(
					'timeout' => 25,
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $api_token,
					),
					'body'    => wp_json_encode( $payload ),
				)
			);

			if ( is_wp_error( $response ) ) {
				Circuit_Breaker::record_failure();
				continue;
			}

			$status = wp_remote_retrieve_response_code( $response );
			if ( $status < 200 || $status >= 300 ) {
				Circuit_Breaker::record_failure();
				continue;
			}

			Circuit_Breaker::record_success();
			$body  = wp_remote_retrieve_body( $response );
			$data  = json_decode( $body, true );
			$reply = $data['response'] ?? $data['message'] ?? '';
			if ( ! is_string( $reply ) ) {
				continue;
			}
			$reply = trim( $reply );
			if ( preg_match( '/```(?:\w*)\s*\n?(.*)\n?```/s', $reply, $m ) ) {
				$reply = trim( $m[1] );
			}
			$parsed = json_decode( $reply, true );
			if ( ! is_array( $parsed ) || empty( $parsed['stale'] ) ) {
				continue;
			}

			$reason     = isset( $parsed['reason'] ) ? sanitize_text_field( (string) $parsed['reason'] ) : '';
			$findings[] = array(
				'post_id' => $post->ID,
				'title'   => $post->post_title,
				'url'     => get_permalink( $post->ID ),
				'reason'  => $reason,
			);
		}

		if ( empty( $findings ) ) {
			return;
		}

		Governance::deliver_findings(
			'semantic_content_freshness',
			$findings,
			sprintf(
				/* translators: %d: number of posts */
				_n(
					'%d post may be factually stale (AI assessment).',
					'%d posts may be factually stale (AI assessment).',
					count( $findings ),
					'wp-pinch'
				),
				count( $findings )
			)
		);
	}
}
