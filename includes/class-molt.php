<?php
/**
 * Molt — Content repackager.
 *
 * Turns a single blog post into multiple formats: social posts,
 * email snippet, FAQ block, thread, summary, meta description,
 * pull quote, key takeaways, CTA variants. Lobsters molt to grow;
 * your post sheds one form and emerges in many.
 *
 * @package WP_Pinch
 * @since   2.4.0
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Molt content repackager engine.
 */
class Molt {

	/**
	 * Maximum post content length to send to the gateway (chars).
	 */
	const MAX_CONTENT_CHARS = 8000;

	/**
	 * Twitter character limit.
	 */
	const TWITTER_MAX_CHARS = 280;

	/**
	 * Meta description character limit.
	 */
	const META_MAX_CHARS = 155;

	/**
	 * Get all available output format keys.
	 *
	 * @return string[]
	 */
	public static function get_default_output_types(): array {
		$types = array(
			'social',
			'email_snippet',
			'faq_block',
			'faq_blocks',
			'thread',
			'summary',
			'meta_description',
			'pull_quote',
			'key_takeaways',
			'cta_variants',
		);

		return (array) apply_filters( 'wp_pinch_molt_output_types', $types );
	}

	/**
	 * Repackage a post into multiple output formats.
	 *
	 * @param int   $post_id     Post ID.
	 * @param array $output_types Optional. Format keys to generate. Empty = all.
	 * @return array|\WP_Error Structured output or error.
	 */
	public static function molt( int $post_id, array $output_types = array() ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'not_found',
				__( 'Post not found.', 'wp-pinch' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new \WP_Error(
				'forbidden',
				__( 'You do not have permission to read this post.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}

		$all_types = self::get_default_output_types();
		$requested = empty( $output_types )
			? $all_types
			: array_intersect( $output_types, $all_types );

		if ( empty( $requested ) ) {
			return new \WP_Error(
				'invalid_formats',
				__( 'No valid output formats specified.', 'wp-pinch' ),
				array( 'status' => 400 )
			);
		}

		$raw = self::request_molt( $post, array_values( $requested ) );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$parsed = self::parse_molt_response( $raw, $requested );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		Audit_Table::insert(
			'molt',
			'molt',
			sprintf( 'Molt ran on post #%d ("%s").', $post_id, $post->post_title ),
			array( 'post_id' => $post_id )
		);

		return array_merge(
			array(
				'post_id' => $post_id,
				'title'   => $post->post_title,
			),
			$parsed
		);
	}

	/**
	 * Send post to OpenClaw and request repackaged content.
	 *
	 * @param \WP_Post $post         The post object.
	 * @param array    $output_types Format keys to request.
	 * @return string|\WP_Error Raw AI reply or error.
	 */
	private static function request_molt( \WP_Post $post, array $output_types ) {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = \WP_Pinch\Settings::get_api_token();

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WP Pinch gateway is not configured.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			return new \WP_Error(
				'gateway_unavailable',
				__( 'The AI gateway is temporarily unavailable.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		$content = wp_strip_all_tags( $post->post_content );
		if ( mb_strlen( $content ) > self::MAX_CONTENT_CHARS ) {
			$content = mb_substr( $content, 0, self::MAX_CONTENT_CHARS ) . '...';
		}
		if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
			$content = Prompt_Sanitizer::sanitize( $content );
		}

		$format_spec = self::build_format_spec( $output_types );
		$prompt      = sprintf(
			"Repackage the following blog post into multiple formats.\n\n" .
			"TITLE: %s\n\n" .
			"CONTENT:\n%s\n\n" .
			"OUTPUT FORMATS (return a JSON object with these exact keys):\n%s\n\n" .
			'Return ONLY the JSON object. No markdown fences, no explanation, no other text.',
			$post->post_title,
			$content,
			$format_spec
		);

		$payload = array(
			'message'    => $prompt,
			'name'       => 'WordPress Molt',
			'sessionKey' => 'wp-pinch-molt-' . $post->ID,
			'wakeMode'   => 'now',
		);

		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}

		$hooks_url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $hooks_url ) ) {
			return new \WP_Error(
				'invalid_gateway',
				__( 'Gateway URL failed security validation.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}

		$response = wp_safe_remote_post(
			$hooks_url,
			array(
				'timeout' => 90,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Circuit_Breaker::record_failure();
			return new \WP_Error(
				'gateway_error',
				__( 'Unable to reach the AI gateway for Molt.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 ) {
			Circuit_Breaker::record_failure();
			return new \WP_Error(
				'gateway_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Molt request returned HTTP %d.', 'wp-pinch' ),
					$status
				),
				array( 'status' => 502 )
			);
		}

		Circuit_Breaker::record_success();

		$reply = $data['response'] ?? $data['message'] ?? '';

		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return new \WP_Error(
				'empty_response',
				__( 'The AI returned an empty response.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}

		return $reply;
	}

	/**
	 * Build the format specification for the prompt.
	 *
	 * @param array $output_types Format keys.
	 * @return string
	 */
	private static function build_format_spec( array $output_types ): string {
		$specs = array();

		$preferred       = Utils::get_preferred_content_format();
		$faq_blocks_spec = 'blocks' === $preferred
			? 'String - Gutenberg block markup for FAQs. Use <!-- wp:heading {"level":4} --><h4>Q</h4><!-- /wp:heading --> and <!-- wp:paragraph --><p>A</p><!-- /wp:paragraph --> for each Q&A. Output valid block markup only.'
			: 'String - HTML for FAQs. Use <h4>Q</h4> and <p>A</p> for each Q&A. Output valid HTML only (no block comments).';

		$definitions = array(
			'social'           => 'Object with "twitter" (max ' . self::TWITTER_MAX_CHARS . ' chars) and "linkedin" (up to ~3000 chars) - platform-optimized social posts.',
			'email_snippet'    => 'String - 2-3 paragraph email-friendly excerpt.',
			'faq_block'        => 'Array of objects with "question" and "answer" - extract FAQs from the content.',
			'faq_blocks'       => $faq_blocks_spec,
			'thread'           => 'Array of strings - Twitter thread (each tweet max ' . self::TWITTER_MAX_CHARS . ' chars).',
			'summary'          => 'String - 2-3 sentence summary.',
			'meta_description' => 'String - SEO meta description, max ' . self::META_MAX_CHARS . ' chars.',
			'pull_quote'       => 'String - single compelling quote from the content.',
			'key_takeaways'    => 'Array of strings - 3-5 bullet points.',
			'cta_variants'     => 'Array of strings - 2-3 call-to-action options.',
		);

		foreach ( $output_types as $key ) {
			if ( isset( $definitions[ $key ] ) ) {
				$specs[] = '- "' . $key . '": ' . $definitions[ $key ];
			}
		}

		return implode( "\n", $specs );
	}

	/**
	 * Parse AI reply into structured output and sanitize.
	 *
	 * @param string $reply    Raw AI response.
	 * @param array  $requested Requested format keys.
	 * @return array|\WP_Error Sanitized output or error.
	 */
	private static function parse_molt_response( string $reply, array $requested ) {
		$reply = trim( $reply );

		// Try to extract JSON from markdown code block.
		if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $reply, $matches ) ) {
			$reply = $matches[1];
		}

		$data = json_decode( $reply, true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'parse_error',
				__( 'Could not parse Molt response as JSON.', 'wp-pinch' ),
				array( 'status' => 500 )
			);
		}

		$output = array();

		foreach ( $requested as $key ) {
			$value = $data[ $key ] ?? null;

			if ( null === $value ) {
				continue;
			}

			$output[ $key ] = self::sanitize_molt_value( $key, $value );
		}

		return $output;
	}

	/**
	 * Sanitize Gutenberg block markup for safe storage.
	 *
	 * Parses blocks and re-serializes to validate structure; falls back to
	 * wp_kses_post if parsing fails.
	 *
	 * @param string $markup Raw block markup.
	 * @return string Sanitized markup.
	 */
	private static function sanitize_block_markup( string $markup ): string {
		$markup = trim( $markup );
		if ( '' === $markup ) {
			return '';
		}
		if ( function_exists( 'parse_blocks' ) ) {
			$blocks = parse_blocks( $markup );
			if ( ! empty( $blocks ) ) {
				return serialize_blocks( $blocks );
			}
		}
		return wp_kses_post( $markup );
	}

	/**
	 * Sanitize a single Molt output value by format type.
	 *
	 * @param string $key   Format key.
	 * @param mixed  $value Raw value.
	 * @return mixed Sanitized value (array, string, or scalar).
	 */
	private static function sanitize_molt_value( string $key, mixed $value ): mixed {
		switch ( $key ) {
			case 'social':
				if ( ! is_array( $value ) ) {
					return array(
						'twitter'  => '',
						'linkedin' => '',
					);
				}
				return array(
					'twitter'  => sanitize_text_field( $value['twitter'] ?? '' ),
					'linkedin' => wp_kses_post( $value['linkedin'] ?? '' ),
				);

			case 'email_snippet':
			case 'summary':
			case 'pull_quote':
				return wp_kses_post( is_string( $value ) ? $value : '' );

			case 'meta_description':
				$s = sanitize_text_field( is_string( $value ) ? $value : '' );
				return mb_substr( $s, 0, self::META_MAX_CHARS );

			case 'faq_block':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$faq = array();
				foreach ( $value as $item ) {
					if ( is_array( $item ) && isset( $item['question'], $item['answer'] ) ) {
						$faq[] = array(
							'question' => sanitize_text_field( $item['question'] ),
							'answer'   => wp_kses_post( $item['answer'] ),
						);
					}
				}
				return $faq;

			case 'faq_blocks':
				if ( ! is_string( $value ) ) {
					return '';
				}
				return self::sanitize_block_markup( $value );

			case 'thread':
			case 'key_takeaways':
			case 'cta_variants':
				if ( ! is_array( $value ) ) {
					return array();
				}
				return array_map(
					function ( $v ) use ( $key ) {
						$s = sanitize_text_field( is_string( $v ) ? $v : '' );
						return 'thread' === $key ? mb_substr( $s, 0, self::TWITTER_MAX_CHARS ) : $s;
					},
					array_values( $value )
				);

			default:
				return is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}
	}

	/**
	 * Format Molt output as readable text for chat display.
	 *
	 * @param array $output Molt output array.
	 * @return string
	 */
	public static function format_for_chat( array $output ): string {
		$parts = array();

		if ( ! empty( $output['summary'] ) ) {
			$parts[] = '**Summary:** ' . $output['summary'];
		}

		if ( ! empty( $output['meta_description'] ) ) {
			$parts[] = '**Meta description:** ' . $output['meta_description'];
		}

		if ( ! empty( $output['pull_quote'] ) ) {
			$parts[] = '**Pull quote:** "' . $output['pull_quote'] . '"';
		}

		if ( ! empty( $output['social'] ) && is_array( $output['social'] ) ) {
			if ( ! empty( $output['social']['twitter'] ) ) {
				$parts[] = '**Twitter:** ' . $output['social']['twitter'];
			}
			if ( ! empty( $output['social']['linkedin'] ) ) {
				$parts[] = '**LinkedIn:** ' . $output['social']['linkedin'];
			}
		}

		if ( ! empty( $output['email_snippet'] ) ) {
			$parts[] = '**Email snippet:**' . "\n" . $output['email_snippet'];
		}

		if ( ! empty( $output['key_takeaways'] ) && is_array( $output['key_takeaways'] ) ) {
			$bullets = array_map(
				function ( $t ) {
					return '- ' . $t;
				},
				$output['key_takeaways']
			);
			$parts[] = '**Key takeaways:**' . "\n" . implode( "\n", $bullets );
		}

		if ( ! empty( $output['cta_variants'] ) && is_array( $output['cta_variants'] ) ) {
			$cta_lines = array_map(
				function ( $c ) {
					return '- ' . $c;
				},
				$output['cta_variants']
			);
			$parts[]   = '**CTA variants:**' . "\n" . implode( "\n", $cta_lines );
		}

		if ( ! empty( $output['faq_block'] ) && is_array( $output['faq_block'] ) ) {
			$faq_lines = array( '**FAQ:**' );
			foreach ( $output['faq_block'] as $i => $faq ) {
				$faq_lines[] = ( $i + 1 ) . '. **Q:** ' . ( $faq['question'] ?? '' );
				$faq_lines[] = '   **A:** ' . ( $faq['answer'] ?? '' );
			}
			$parts[] = implode( "\n", $faq_lines );
		}

		if ( ! empty( $output['faq_blocks'] ) && is_string( $output['faq_blocks'] ) ) {
			$parts[] = '**FAQ (Gutenberg blocks):** ' . wp_strip_all_tags( $output['faq_blocks'] );
		}

		if ( ! empty( $output['thread'] ) && is_array( $output['thread'] ) ) {
			$thread_lines = array( '**Thread:**' );
			foreach ( $output['thread'] as $i => $tweet ) {
				$thread_lines[] = ( $i + 1 ) . '. ' . $tweet;
			}
			$parts[] = implode( "\n", $thread_lines );
		}

		return implode( "\n\n", $parts );
	}
}
