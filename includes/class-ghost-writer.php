<?php
/**
 * Ghost Writer — AI voice profile engine and draft completion.
 *
 * Learns each author's writing style from their published posts,
 * surfaces abandoned drafts worth resurrecting, and completes
 * drafts in the author's voice via OpenClaw.
 *
 * "You started this post 8 months ago. You were going somewhere good.
 * Want me to finish it?"
 *
 * @package WP_Pinch
 * @since   2.3.0
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Ghost Writer engine.
 */
class Ghost_Writer {

	/**
	 * User meta key for the voice profile.
	 */
	const VOICE_META_KEY = 'wp_pinch_voice_profile';

	/**
	 * Maximum number of posts to sample for voice analysis.
	 */
	const MAX_SAMPLE_POSTS = 10;

	/**
	 * Maximum word count per post sample sent to the gateway.
	 */
	const MAX_SAMPLE_WORDS = 1000;

	/**
	 * Default abandoned draft threshold in days.
	 */
	const DEFAULT_THRESHOLD_DAYS = 30;

	// =========================================================================
	// Voice Profile
	// =========================================================================

	/**
	 * Retrieve the cached voice profile for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|false Profile array or false if none exists.
	 */
	public static function get_voice_profile( int $user_id ) {
		$profile = get_user_meta( $user_id, self::VOICE_META_KEY, true );

		if ( ! is_array( $profile ) || empty( $profile ) ) {
			return false;
		}

		return $profile;
	}

	/**
	 * Analyze an author's published posts and build a voice profile.
	 *
	 * Computes local style metrics in PHP and sends post samples to
	 * the OpenClaw gateway for AI-powered voice analysis.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|\WP_Error The voice profile or error.
	 */
	public static function analyze_voice( int $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error(
				'invalid_user',
				__( 'User not found.', 'wp-pinch' ),
				array( 'status' => 404 )
			);
		}

		// Fetch published posts by this author.
		$posts = get_posts(
			array(
				'author'         => $user_id,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_SAMPLE_POSTS,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $posts ) ) {
			return new \WP_Error(
				'no_posts',
				__( 'This author has no published posts to analyze.', 'wp-pinch' ),
				array( 'status' => 400 )
			);
		}

		// Compute local style metrics.
		$metrics = self::compute_metrics( $posts );

		// Prepare post samples for AI analysis.
		$samples = self::prepare_samples( $posts );

		// Send to OpenClaw for voice profile generation.
		$voice = self::request_voice_analysis( $user, $samples, $metrics );

		if ( is_wp_error( $voice ) ) {
			return $voice;
		}

		$profile = array(
			'generated_at'        => gmdate( 'c' ),
			'post_count_analyzed' => count( $posts ),
			'metrics'             => $metrics,
			'voice'               => $voice,
		);

		// Cache in user meta.
		update_user_meta( $user_id, self::VOICE_META_KEY, $profile );

		Audit_Table::insert(
			'voice_analyzed',
			'ghost_writer',
			sprintf(
				'Voice profile generated for user #%d (%s) from %d posts.',
				$user_id,
				$user->display_name,
				count( $posts )
			),
			array( 'user_id' => $user_id )
		);

		return $profile;
	}

	/**
	 * Compute local style metrics from a set of posts.
	 *
	 * @param \WP_Post[] $posts Array of post objects.
	 * @return array Metrics array.
	 */
	private static function compute_metrics( array $posts ): array {
		$total_sentences  = 0;
		$total_words      = 0;
		$total_paragraphs = 0;
		$total_headings   = 0;
		$total_lists      = 0;
		$total_post_words = 0;
		$post_count       = count( $posts );

		foreach ( $posts as $post ) {
			$content    = wp_strip_all_tags( $post->post_content );
			$word_count = str_word_count( $content );

			$total_post_words += $word_count;
			$total_words      += $word_count;

			// Count sentences (split on . ! ?).
			$sentences        = preg_split( '/[.!?]+\s/', $content, -1, PREG_SPLIT_NO_EMPTY );
			$total_sentences += is_array( $sentences ) ? count( $sentences ) : 0;

			// Count paragraphs (double newlines or <p> tags).
			$paragraphs        = preg_split( '/\n\s*\n/', $post->post_content, -1, PREG_SPLIT_NO_EMPTY );
			$total_paragraphs += is_array( $paragraphs ) ? count( $paragraphs ) : 0;

			// Count headings (h1-h6 tags).
			$heading_count   = preg_match_all( '/<h[1-6][^>]*>/i', $post->post_content );
			$total_headings += $heading_count ? $heading_count : 0;

			// Count list items.
			$list_count   = preg_match_all( '/<li[^>]*>/i', $post->post_content );
			$total_lists += $list_count ? $list_count : 0;
		}

		$avg_sentence_length  = $total_sentences > 0 ? round( $total_words / $total_sentences, 1 ) : 0;
		$avg_paragraph_length = $total_paragraphs > 0 ? round( $total_sentences / $total_paragraphs, 1 ) : 0;
		$avg_post_word_count  = $post_count > 0 ? round( $total_post_words / $post_count ) : 0;
		$heading_frequency    = $total_words > 0 ? round( $total_headings / ( $total_words / 100 ), 2 ) : 0;
		$list_to_prose_ratio  = $total_sentences > 0 ? round( $total_lists / $total_sentences, 2 ) : 0;

		return array(
			'avg_sentence_length'  => $avg_sentence_length,
			'avg_paragraph_length' => $avg_paragraph_length,
			'heading_frequency'    => $heading_frequency,
			'list_to_prose_ratio'  => $list_to_prose_ratio,
			'avg_post_word_count'  => $avg_post_word_count,
		);
	}

	/**
	 * Prepare truncated post samples for AI analysis.
	 *
	 * @param \WP_Post[] $posts Array of post objects.
	 * @return array Array of sample arrays with title and content.
	 */
	private static function prepare_samples( array $posts ): array {
		$samples = array();

		foreach ( $posts as $post ) {
			$content = wp_strip_all_tags( $post->post_content );
			$words   = explode( ' ', $content );

			if ( count( $words ) > self::MAX_SAMPLE_WORDS ) {
				$content = implode( ' ', array_slice( $words, 0, self::MAX_SAMPLE_WORDS ) ) . '...';
			}

			$samples[] = array(
				'title'   => $post->post_title,
				'content' => $content,
			);
		}

		return $samples;
	}

	/**
	 * Send post samples to OpenClaw and request a voice profile.
	 *
	 * @param \WP_User $user    The author.
	 * @param array    $samples Post samples.
	 * @param array    $metrics Local metrics.
	 * @return array|\WP_Error Voice profile data or error.
	 */
	private static function request_voice_analysis( \WP_User $user, array $samples, array $metrics ) {
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

		$prompt = sprintf(
			"Analyze the following %d blog posts by \"%s\" and produce a structured voice profile.\n\n" .
			"Local metrics (pre-computed):\n%s\n\n" .
			"Posts:\n%s\n\n" .
			"Return a JSON object with these exact keys:\n" .
			"- \"tone\": string (e.g. \"conversational but authoritative\")\n" .
			"- \"vocabulary_level\": string (e.g. \"accessible technical\")\n" .
			"- \"quirks\": array of strings (distinctive writing habits)\n" .
			"- \"structure\": string (how they typically organize posts)\n" .
			"- \"avoid\": array of strings (things this author never does)\n\n" .
			'Return ONLY the JSON object, no markdown fences or explanation.',
			count( $samples ),
			$user->display_name,
			wp_json_encode( $metrics, JSON_PRETTY_PRINT ),
			self::format_samples_for_prompt( $samples )
		);

		$payload = array(
			'message'    => $prompt,
			'name'       => 'WordPress Ghost Writer',
			'sessionKey' => 'wp-pinch-ghostwriter-' . $user->ID,
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
				'timeout' => 30,
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
				__( 'Unable to reach the AI gateway for voice analysis.', 'wp-pinch' ),
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
					__( 'Voice analysis request returned HTTP %d.', 'wp-pinch' ),
					$status
				),
				array( 'status' => 502 )
			);
		}

		Circuit_Breaker::record_success();

		$reply = $data['response'] ?? $data['message'] ?? '';

		if ( ! is_string( $reply ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Unexpected response from gateway during voice analysis.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}

		// Parse the JSON voice profile from the AI response.
		$voice = json_decode( $reply, true );

		if ( ! is_array( $voice ) || ! isset( $voice['tone'] ) ) {
			// Try extracting JSON from markdown code fences.
			if ( preg_match( '/```(?:json)?\s*(\{.*?\})\s*```/s', $reply, $matches ) ) {
				$voice = json_decode( $matches[1], true );
			}
		}

		if ( ! is_array( $voice ) || ! isset( $voice['tone'] ) ) {
			return new \WP_Error(
				'parse_error',
				__( 'Could not parse voice profile from AI response.', 'wp-pinch' ),
				array( 'status' => 500 )
			);
		}

		// Sanitize the profile values.
		return array(
			'tone'             => sanitize_text_field( $voice['tone'] ?? '' ),
			'vocabulary_level' => sanitize_text_field( $voice['vocabulary_level'] ?? '' ),
			'quirks'           => array_map( 'sanitize_text_field', (array) ( $voice['quirks'] ?? array() ) ),
			'structure'        => sanitize_text_field( $voice['structure'] ?? '' ),
			'avoid'            => array_map( 'sanitize_text_field', (array) ( $voice['avoid'] ?? array() ) ),
		);
	}

	/**
	 * Format post samples into a readable string for the prompt.
	 *
	 * @param array $samples Post samples.
	 * @return string Formatted text.
	 */
	private static function format_samples_for_prompt( array $samples ): string {
		$parts = array();

		foreach ( $samples as $i => $sample ) {
			$content = $sample['content'] ?? '';
			if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
				$content = Prompt_Sanitizer::sanitize( $content );
			}
			$parts[] = sprintf(
				"--- Post %d: \"%s\" ---\n%s",
				$i + 1,
				$sample['title'] ?? '',
				$content
			);
		}

		return implode( "\n\n", $parts );
	}

	// =========================================================================
	// Draft Assessment
	// =========================================================================

	/**
	 * Assess abandoned drafts and rank by resurrection potential.
	 *
	 * @param int $user_id Optional. Scope to a single author (0 = all authors).
	 * @return array Array of draft assessments sorted by score.
	 */
	public static function assess_drafts( int $user_id = 0 ): array {
		/**
		 * Filter the abandoned draft threshold in days.
		 *
		 * @since 2.3.0
		 *
		 * @param int $days Days since last modification. Default 30.
		 */
		$threshold_days = (int) apply_filters(
			'wp_pinch_ghost_writer_threshold',
			(int) get_option( 'wp_pinch_ghost_writer_threshold', self::DEFAULT_THRESHOLD_DAYS )
		);

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $threshold_days * DAY_IN_SECONDS ) );

		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'draft',
			'posts_per_page' => 50,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => $cutoff,
				),
			),
		);

		if ( $user_id > 0 ) {
			$args['author'] = $user_id;
		}

		$drafts  = get_posts( $args );
		$results = array();

		foreach ( $drafts as $draft ) {
			$content    = wp_strip_all_tags( $draft->post_content );
			$word_count = str_word_count( $content );
			$days_old   = (int) floor( ( time() - strtotime( $draft->post_modified_gmt ) ) / DAY_IN_SECONDS );

			// Estimate completion: a "typical" post is ~800 words.
			$estimated_completion = min( 95, round( ( $word_count / 800 ) * 100 ) );

			// Resurrection score: higher for posts that are more complete and not ancient.
			// Sweet spot: 30-70% done and less than a year old.
			$freshness_factor  = max( 0, 1 - ( $days_old / 365 ) );
			$completion_factor = $estimated_completion > 10 ? min( 1, $estimated_completion / 50 ) : 0.1;
			$score             = round( ( $freshness_factor * 0.4 + $completion_factor * 0.6 ) * 100 );

			$categories = wp_get_post_categories( $draft->ID, array( 'fields' => 'names' ) );

			$results[] = array(
				'post_id'              => $draft->ID,
				'title'                => $draft->post_title ? $draft->post_title : __( '(Untitled)', 'wp-pinch' ),
				'author'               => get_the_author_meta( 'display_name', (int) $draft->post_author ),
				'author_id'            => (int) $draft->post_author,
				'last_modified'        => $draft->post_modified,
				'days_abandoned'       => $days_old,
				'word_count'           => $word_count,
				'estimated_completion' => $estimated_completion,
				'resurrection_score'   => $score,
				'categories'           => is_array( $categories ) ? $categories : array(),
				'edit_url'             => get_edit_post_link( $draft->ID, 'raw' ),
			);
		}

		// Sort by resurrection score descending.
		usort(
			$results,
			function ( $a, $b ) {
				return $b['resurrection_score'] <=> $a['resurrection_score'];
			}
		);

		return $results;
	}

	// =========================================================================
	// Ghostwrite
	// =========================================================================

	/**
	 * Complete an abandoned draft in the author's voice.
	 *
	 * Loads the draft, retrieves (or generates) the author's voice profile,
	 * sends both to OpenClaw, and returns the AI-completed content.
	 * Does NOT auto-publish — the draft stays a draft.
	 *
	 * @param int  $post_id Post ID of the draft.
	 * @param bool $apply   Whether to update the draft content directly.
	 * @return array|\WP_Error Result with generated content or error.
	 */
	public static function ghostwrite( int $post_id, bool $apply = false ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'not_found',
				__( 'Post not found.', 'wp-pinch' ),
				array( 'status' => 404 )
			);
		}

		if ( 'draft' !== $post->post_status ) {
			return new \WP_Error(
				'not_a_draft',
				__( 'Ghost Writer can only complete drafts.', 'wp-pinch' ),
				array( 'status' => 400 )
			);
		}

		// Per-post capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'forbidden',
				__( 'You do not have permission to edit this post.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}

		$author_id = (int) $post->post_author;

		// Get or generate voice profile.
		$profile = self::get_voice_profile( $author_id );

		if ( false === $profile ) {
			$profile = self::analyze_voice( $author_id );
			if ( is_wp_error( $profile ) ) {
				return $profile;
			}
		}

		// Build the ghostwrite prompt.
		$completed = self::request_ghostwrite( $post, $profile );

		if ( is_wp_error( $completed ) ) {
			return $completed;
		}

		// Sanitize the AI-generated content.
		$completed_content = wp_kses_post( $completed );

		// Optionally update the draft.
		if ( $apply ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $completed_content,
				)
			);
		}

		Audit_Table::insert(
			'ghostwrite',
			'ghost_writer',
			sprintf(
				'Ghost Writer completed draft #%d ("%s") for user #%d.%s',
				$post_id,
				$post->post_title,
				$author_id,
				$apply ? ' Content applied to draft.' : ''
			),
			array(
				'post_id' => $post_id,
				'applied' => $apply,
			)
		);

		return array(
			'post_id'  => $post_id,
			'title'    => $post->post_title,
			'content'  => $completed_content,
			'applied'  => $apply,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * Send the draft and voice profile to OpenClaw for completion.
	 *
	 * @param \WP_Post $post    The draft post.
	 * @param array    $profile The author's voice profile.
	 * @return string|\WP_Error The completed content or error.
	 */
	private static function request_ghostwrite( \WP_Post $post, array $profile ) {
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

		$voice_description = self::format_voice_for_prompt( $profile );
		$draft_content     = $post->post_content;
		if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
			$draft_content = Prompt_Sanitizer::sanitize( $draft_content );
		}

		$prompt = sprintf(
			"You are a ghost writer. Complete the following WordPress draft post.\n\n" .
			"AUTHOR VOICE PROFILE:\n%s\n\n" .
			"DRAFT TITLE: %s\n\n" .
			"EXISTING CONTENT:\n%s\n\n" .
			"INSTRUCTIONS:\n" .
			"- Continue and complete this draft in the author's voice.\n" .
			"- Match their tone, vocabulary, sentence structure, and quirks exactly.\n" .
			"- Keep the existing content intact — only add to it.\n" .
			"- Use proper HTML for paragraphs, headings, lists, etc.\n" .
			"- Aim for a natural, complete blog post.\n" .
			"- Do NOT include the title in your response — only the body content.\n" .
			"- Return the FULL post content (existing + new), not just the new part.\n\n" .
			'Return ONLY the HTML content, no markdown fences or explanation.',
			$voice_description,
			$post->post_title,
			$draft_content
		);

		$payload = array(
			'message'    => $prompt,
			'name'       => 'WordPress Ghost Writer',
			'sessionKey' => 'wp-pinch-ghostwriter-' . $post->post_author . '-' . $post->ID,
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
				'timeout' => 60,
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
				__( 'Unable to reach the AI gateway for ghostwriting.', 'wp-pinch' ),
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
					__( 'Ghostwrite request returned HTTP %d.', 'wp-pinch' ),
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
	 * Format a voice profile into human-readable text for the prompt.
	 *
	 * @param array $profile The voice profile.
	 * @return string Formatted description.
	 */
	private static function format_voice_for_prompt( array $profile ): string {
		$voice   = $profile['voice'] ?? array();
		$metrics = $profile['metrics'] ?? array();

		$parts = array();

		if ( ! empty( $voice['tone'] ) ) {
			$parts[] = 'Tone: ' . $voice['tone'];
		}
		if ( ! empty( $voice['vocabulary_level'] ) ) {
			$parts[] = 'Vocabulary: ' . $voice['vocabulary_level'];
		}
		if ( ! empty( $voice['structure'] ) ) {
			$parts[] = 'Structure: ' . $voice['structure'];
		}
		if ( ! empty( $voice['quirks'] ) ) {
			$parts[] = 'Quirks: ' . implode( ', ', $voice['quirks'] );
		}
		if ( ! empty( $voice['avoid'] ) ) {
			$parts[] = 'Avoid: ' . implode( ', ', $voice['avoid'] );
		}

		if ( ! empty( $metrics['avg_sentence_length'] ) ) {
			$parts[] = sprintf( 'Average sentence length: %s words', $metrics['avg_sentence_length'] );
		}
		if ( ! empty( $metrics['avg_post_word_count'] ) ) {
			$parts[] = sprintf( 'Typical post length: ~%d words', $metrics['avg_post_word_count'] );
		}

		return implode( "\n", $parts );
	}
}
