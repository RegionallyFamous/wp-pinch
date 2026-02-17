<?php
/**
 * GEO and SEO abilities: llms.txt generation, bulk metadata.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;
use WP_Pinch\Circuit_Breaker;
use WP_Pinch\Feature_Flags;
use WP_Pinch\Plugin;
use WP_Pinch\Prompt_Sanitizer;
use WP_Pinch\RAG_Index;
use WP_Pinch\Settings;

/**
 * GEO (Generative Engine Optimization) and bulk SEO abilities.
 */
class GEO_SEO_Abilities {

	/**
	 * Default path for llms.txt relative to ABSPATH.
	 */
	const LLMS_TXT_FILENAME = 'llms.txt';

	/**
	 * Maximum posts to process in one bulk-seo-meta call.
	 */
	const BULK_SEO_MAX = 50;

	/**
	 * Register GEO and SEO abilities.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Abilities::register_ability(
			'wp-pinch/generate-llms-txt',
			__( 'Generate llms.txt', 'wp-pinch' ),
			__( 'Generate an llms.txt file for AI crawlers (GEO). Uses site name, description, and structure. Optionally write to the site root.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'write' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'If true, write the generated content to llms.txt at the site root.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_generate_llms_txt' )
		);

		Abilities::register_ability(
			'wp-pinch/get-llms-txt',
			__( 'Get llms.txt', 'wp-pinch' ),
			__( 'Read the current llms.txt file content from the site root, if it exists.', 'wp-pinch' ),
			array( 'type' => 'object' ),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_get_llms_txt' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/bulk-seo-meta',
			__( 'Bulk SEO Metadata', 'wp-pinch' ),
			__( 'Generate SEO titles and meta descriptions for multiple posts. Provide post_ids or a query (post_type, limit). Optionally apply updates.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_ids'  => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => __( 'Specific post IDs to process.', 'wp-pinch' ),
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'limit'     => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'apply'     => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'If true, update posts with generated title and meta description.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_bulk_seo_meta' )
		);

		Abilities::register_ability(
			'wp-pinch/suggest-internal-links',
			__( 'Suggest Internal Links', 'wp-pinch' ),
			__( 'Given a post ID or search query, return topically related posts to link to (uses RAG index when enabled).', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'Post ID to suggest links for (uses its title/excerpt as query).', 'wp-pinch' ),
					),
					'query'   => array(
						'type'        => 'string',
						'description' => __( 'Search query when post_id is not provided.', 'wp-pinch' ),
					),
					'limit'   => array(
						'type'    => 'integer',
						'default' => 10,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_suggest_internal_links' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/generate-schema-markup',
			__( 'Generate Schema Markup', 'wp-pinch' ),
			__( 'Analyze post content and return JSON-LD schema (Article, Product, FAQ, HowTo, Recipe, etc.).', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'Post ID to generate schema for.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_generate_schema_markup' )
		);

		Abilities::register_ability(
			'wp-pinch/suggest-seo-improvements',
			__( 'Suggest SEO Improvements', 'wp-pinch' ),
			__( 'Analyze a post for inline SEO: keyword density, heading structure, and meta title/description suggestions.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'Post ID to analyze.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_suggest_seo_improvements' )
		);
	}

	/**
	 * Generate llms.txt content via gateway and optionally write to file.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with path, content; or error key.
	 */
	public static function execute_generate_llms_txt( array $input ): array {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return array( 'error' => __( 'WP Pinch gateway is not configured.', 'wp-pinch' ) );
		}

		if ( Plugin::is_read_only_mode() ) {
			return array( 'error' => __( 'API is in read-only mode. Write operations are disabled.', 'wp-pinch' ) );
		}

		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			return array( 'error' => __( 'The AI gateway is temporarily unavailable.', 'wp-pinch' ) );
		}

		$site_name        = get_bloginfo( 'name' );
		$site_description = get_bloginfo( 'description' );
		$site_url         = home_url( '/', 'https' );

		$prompt = sprintf(
			'Generate an llms.txt file for this WordPress site so AI crawlers and LLMs can understand it.' . "\n\n" .
			'SITE NAME: %s' . "\n" .
			'DESCRIPTION: %s' . "\n" .
			'SITE URL: %s' . "\n\n" .
			'Return ONLY the raw llms.txt content. No markdown fences, no explanation. ' .
			'Include a brief description of the site, main topics or content types, and any guidance for AI (e.g. how to cite, preferred tone). ' .
			'Keep it concise (under 2KB). Use plain text line breaks.',
			$site_name,
			$site_description,
			$site_url
		);

		$payload = array(
			'message'    => $prompt,
			'name'       => 'WordPress GEO',
			'sessionKey' => 'wp-pinch-geo-llms',
			'wakeMode'   => 'now',
		);

		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}

		$hooks_url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $hooks_url ) ) {
			return array( 'error' => __( 'Gateway URL failed security validation.', 'wp-pinch' ) );
		}

		$response = wp_safe_remote_post(
			$hooks_url,
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Circuit_Breaker::record_failure();
			return array( 'error' => $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 ) {
			Circuit_Breaker::record_failure();
			return array(
				'error' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Gateway returned HTTP %d.', 'wp-pinch' ),
					$status
				),
			);
		}

		Circuit_Breaker::record_success();

		$content = $data['response'] ?? $data['message'] ?? '';
		if ( ! is_string( $content ) ) {
			$content = '';
		}
		$content = trim( $content );

		// Strip markdown code fences if the model wrapped the output.
		if ( preg_match( '/^```(?:\w*)\s*\n?(.*)\n?```/s', $content, $m ) ) {
			$content = trim( $m[1] );
		}

		$path = apply_filters( 'wp_pinch_llms_txt_path', ABSPATH . self::LLMS_TXT_FILENAME );

		if ( ! empty( $input['write'] ) && '' !== $content ) {
			$wrote = self::write_llms_txt( $path, $content );
			if ( is_wp_error( $wrote ) ) {
				return array(
					'content' => $content,
					'path'    => $path,
					'written' => false,
					'error'   => $wrote->get_error_message(),
				);
			}
			Audit_Table::insert(
				'llms_txt_generated',
				'ability',
				__( 'llms.txt generated and written to site root.', 'wp-pinch' ),
				array( 'path' => $path )
			);
		}

		return array(
			'path'    => $path,
			'content' => $content,
			'written' => ! empty( $input['write'] ) && '' !== $content,
		);
	}

	/**
	 * Write content to llms.txt file.
	 *
	 * @param string $path    Full file path.
	 * @param string $content Content to write.
	 * @return true|\WP_Error
	 */
	private static function write_llms_txt( string $path, string $content ) {
		// Ensure we only write under ABSPATH.
		$real_path = realpath( dirname( $path ) );
		$real_abs  = realpath( ABSPATH );
		if ( false === $real_path || false === $real_abs || ! str_starts_with( $real_path, $real_abs ) ) {
			return new \WP_Error( 'invalid_path', __( 'llms.txt path must be under the site root.', 'wp-pinch' ) );
		}

		$result = file_put_contents( $path, $content );
		if ( false === $result ) {
			return new \WP_Error( 'write_failed', __( 'Could not write llms.txt file.', 'wp-pinch' ) );
		}
		return true;
	}

	/**
	 * Get current llms.txt content.
	 *
	 * @param array<string, mixed> $input Ability input (unused).
	 * @return array<string, mixed>
	 */
	public static function execute_get_llms_txt( array $input ): array {
		$path = apply_filters( 'wp_pinch_llms_txt_path', ABSPATH . self::LLMS_TXT_FILENAME );

		if ( ! is_readable( $path ) ) {
			return array(
				'path'    => $path,
				'content' => '',
				'exists'  => false,
			);
		}

		$content = file_get_contents( $path );
		return array(
			'path'    => $path,
			'content' => is_string( $content ) ? $content : '',
			'exists'  => true,
		);
	}

	/**
	 * Bulk generate SEO title and meta description for posts, optionally apply.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with items and optionally updated count; or error key.
	 */
	public static function execute_bulk_seo_meta( array $input ): array {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return array( 'error' => __( 'WP Pinch gateway is not configured.', 'wp-pinch' ) );
		}

		if ( ! empty( $input['apply'] ) && Plugin::is_read_only_mode() ) {
			return array( 'error' => __( 'API is in read-only mode. Set apply to false or disable read-only mode.', 'wp-pinch' ) );
		}

		$post_ids = isset( $input['post_ids'] ) && is_array( $input['post_ids'] ) ? array_map( 'absint', $input['post_ids'] ) : array();
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) ) {
			$post_type = sanitize_key( $input['post_type'] ?? 'post' );
			$limit     = min( self::BULK_SEO_MAX, max( 1, absint( $input['limit'] ?? 20 ) ) );
			$posts     = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
				)
			);
			$post_ids  = array_map( 'intval', $posts );
		} else {
			$post_ids = array_slice( array_unique( $post_ids ), 0, self::BULK_SEO_MAX );
		}

		if ( empty( $post_ids ) ) {
			return array(
				'items'   => array(),
				'updated' => 0,
			);
		}

		$items   = array();
		$apply   = ! empty( $input['apply'] );
		$updated = 0;

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$suggestion = self::request_seo_suggestion( $post, $gateway_url, $api_token );
			if ( is_wp_error( $suggestion ) ) {
				$items[] = array(
					'post_id' => $post_id,
					'error'   => $suggestion->get_error_message(),
				);
				continue;
			}

			$items[] = array(
				'post_id'          => $post_id,
				'title'            => $post->post_title,
				'title_suggestion' => $suggestion['title'] ?? '',
				'meta_suggestion'  => $suggestion['meta_description'] ?? '',
			);

			if ( $apply && ! empty( $suggestion['title'] ) ) {
				wp_update_post(
					array(
						'ID'         => $post_id,
						'post_title' => sanitize_text_field( $suggestion['title'] ),
					)
				);
				++$updated;
			}
			if ( $apply && isset( $suggestion['meta_description'] ) && '' !== $suggestion['meta_description'] ) {
				update_post_meta( $post_id, '_wp_pinch_meta_description', sanitize_text_field( $suggestion['meta_description'] ) );
			}
		}

		if ( $updated > 0 ) {
			Audit_Table::insert(
				'bulk_seo_applied',
				'ability',
				sprintf(
					/* translators: %d: number of posts */
					__( 'Bulk SEO metadata applied to %d posts.', 'wp-pinch' ),
					$updated
				),
				array(
					'updated'  => $updated,
					'post_ids' => $post_ids,
				)
			);
		}

		return array(
			'items'   => $items,
			'updated' => $apply ? $updated : 0,
		);
	}

	/**
	 * Request a single post's SEO title and meta description from the gateway.
	 *
	 * @param \WP_Post $post        Post object.
	 * @param string   $gateway_url Gateway base URL.
	 * @param string   $api_token   API token.
	 * @return array{title: string, meta_description: string}|\WP_Error
	 */
	private static function request_seo_suggestion( \WP_Post $post, string $gateway_url, string $api_token ) {
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			return new \WP_Error( 'gateway_unavailable', __( 'The AI gateway is temporarily unavailable.', 'wp-pinch' ) );
		}

		$content = wp_strip_all_tags( $post->post_content );
		$content = mb_strlen( $content ) > 2000 ? mb_substr( $content, 0, 2000 ) . '...' : $content;
		if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
			$content = Prompt_Sanitizer::sanitize( $content );
		}

		$prompt = sprintf(
			'Generate an SEO title (under 60 characters) and a meta description (under 160 characters) for this post.' . "\n\n" .
			'CURRENT TITLE: %s' . "\n\n" .
			'CONTENT EXCERPT:' . "\n%s\n\n" .
			'Return a JSON object with exactly these keys: "title", "meta_description". No other text, no markdown.',
			$post->post_title,
			$content
		);

		$payload = array(
			'message'    => $prompt,
			'name'       => 'WordPress Bulk SEO',
			'sessionKey' => 'wp-pinch-bulk-seo-' . $post->ID,
			'wakeMode'   => 'now',
		);

		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}

		$hooks_url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $hooks_url ) ) {
			return new \WP_Error( 'invalid_gateway', __( 'Gateway URL failed security validation.', 'wp-pinch' ) );
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
			return $response;
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
					__( 'Gateway returned HTTP %d.', 'wp-pinch' ),
					$status
				)
			);
		}

		Circuit_Breaker::record_success();

		$reply = $data['response'] ?? $data['message'] ?? '';
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return new \WP_Error( 'empty_response', __( 'The AI returned an empty response.', 'wp-pinch' ) );
		}

		// Strip markdown code block if present.
		if ( preg_match( '/```(?:\w*)\s*\n?(.*)\n?```/s', $reply, $m ) ) {
			$reply = trim( $m[1] );
		}
		$parsed = json_decode( $reply, true );
		if ( ! is_array( $parsed ) ) {
			return new \WP_Error( 'invalid_json', __( 'The AI response was not valid JSON.', 'wp-pinch' ) );
		}

		return array(
			'title'            => isset( $parsed['title'] ) ? sanitize_text_field( (string) $parsed['title'] ) : $post->post_title,
			'meta_description' => isset( $parsed['meta_description'] ) ? sanitize_text_field( (string) $parsed['meta_description'] ) : '',
		);
	}

	/**
	 * Suggest internal links (related posts) for a post or query.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with suggestions array; or error key.
	 */
	public static function execute_suggest_internal_links( array $input ): array {
		$limit = min( 20, max( 1, absint( $input['limit'] ?? 10 ) ) );
		$query = '';

		if ( ! empty( $input['post_id'] ) ) {
			$post = get_post( (int) $input['post_id'] );
			if ( ! $post || ! current_user_can( 'read_post', $post->ID ) ) {
				return array(
					'error'       => __( 'Post not found or access denied.', 'wp-pinch' ),
					'suggestions' => array(),
				);
			}
			$query = $post->post_title . ' ' . wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		} elseif ( ! empty( $input['query'] ) ) {
			$query = sanitize_text_field( (string) $input['query'] );
		}

		if ( '' === trim( $query ) ) {
			return array(
				'suggestions' => array(),
				'message'     => __( 'Provide post_id or query.', 'wp-pinch' ),
			);
		}

		if ( ! RAG_Index::is_available() ) {
			return array(
				'suggestions' => array(),
				'message'     => __( 'Enable RAG indexing in Features to use internal link suggestions.', 'wp-pinch' ),
			);
		}

		$chunks = RAG_Index::get_relevant_chunks( $query, $limit );
		$seen   = array();
		$out    = array();
		foreach ( $chunks as $c ) {
			$id = (int) $c['post_id'];
			if ( isset( $input['post_id'] ) && $id === (int) $input['post_id'] ) {
				continue;
			}
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$out[]       = array(
				'post_id' => $id,
				'title'   => $c['title'],
				'url'     => get_permalink( $id ),
			);
		}

		return array( 'suggestions' => $out );
	}

	/**
	 * Generate JSON-LD schema markup for a post.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with schema_type and json_ld; or error key.
	 */
	public static function execute_generate_schema_markup( array $input ): array {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( $post_id < 1 ) {
			return array( 'error' => __( 'post_id is required.', 'wp-pinch' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'read_post', $post_id ) ) {
			return array( 'error' => __( 'Post not found or access denied.', 'wp-pinch' ) );
		}

		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return array( 'error' => __( 'WP Pinch gateway is not configured.', 'wp-pinch' ) );
		}
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			return array( 'error' => __( 'The AI gateway is temporarily unavailable.', 'wp-pinch' ) );
		}

		$content = wp_strip_all_tags( $post->post_content );
		$content = mb_strlen( $content ) > 3000 ? mb_substr( $content, 0, 3000 ) . '...' : $content;
		if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
			$content = Prompt_Sanitizer::sanitize( $content );
		}

		$prompt = sprintf(
			'Analyze this post and output JSON-LD schema. Choose the most appropriate type: Article, NewsArticle, BlogPosting, Product, FAQPage, HowTo, Recipe, or Organization.' . "\n\n" .
			'TITLE: %s' . "\n\n" .
			'CONTENT:' . "\n%s\n\n" .
			'Return a JSON object with two keys: "schema_type" (string, the type chosen) and "json_ld" (object, valid JSON-LD @context and type). No markdown fences.',
			$post->post_title,
			$content
		);

		$payload  = array(
			'message'    => $prompt,
			'name'       => 'WordPress Schema',
			'sessionKey' => 'wp-pinch-schema-' . $post_id,
			'wakeMode'   => 'now',
		);
		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}

		$hooks_url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $hooks_url ) ) {
			return array( 'error' => __( 'Gateway URL failed security validation.', 'wp-pinch' ) );
		}

		$response = wp_safe_remote_post(
			$hooks_url,
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Circuit_Breaker::record_failure();
			return array( 'error' => $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			Circuit_Breaker::record_failure();
			return array(
				'error' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Gateway returned HTTP %d.', 'wp-pinch' ),
					$status
				),
			);
		}

		Circuit_Breaker::record_success();
		$body  = wp_remote_retrieve_body( $response );
		$data  = json_decode( $body, true );
		$reply = $data['response'] ?? $data['message'] ?? '';
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return array( 'error' => __( 'Empty gateway response.', 'wp-pinch' ) );
		}
		if ( preg_match( '/```(?:\w*)\s*\n?(.*)\n?```/s', $reply, $m ) ) {
			$reply = trim( $m[1] );
		}
		$parsed = json_decode( $reply, true );
		if ( ! is_array( $parsed ) ) {
			return array( 'error' => __( 'Response was not valid JSON.', 'wp-pinch' ) );
		}

		$schema_type = isset( $parsed['schema_type'] ) ? sanitize_text_field( (string) $parsed['schema_type'] ) : 'Article';
		$json_ld     = isset( $parsed['json_ld'] ) && is_array( $parsed['json_ld'] ) ? $parsed['json_ld'] : array();

		return array(
			'schema_type' => $schema_type,
			'json_ld'     => $json_ld,
		);
	}

	/**
	 * Suggest SEO improvements for a post (keyword density, headings, meta).
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with keyword_density_notes, heading_suggestions, meta_suggestions; or error key.
	 */
	public static function execute_suggest_seo_improvements( array $input ): array {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( $post_id < 1 ) {
			return array( 'error' => __( 'post_id is required.', 'wp-pinch' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'read_post', $post_id ) ) {
			return array( 'error' => __( 'Post not found or access denied.', 'wp-pinch' ) );
		}

		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return array( 'error' => __( 'WP Pinch gateway is not configured.', 'wp-pinch' ) );
		}
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			return array( 'error' => __( 'The AI gateway is temporarily unavailable.', 'wp-pinch' ) );
		}

		$content = wp_strip_all_tags( $post->post_content );
		$content = mb_strlen( $content ) > 4000 ? mb_substr( $content, 0, 4000 ) . '...' : $content;
		if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
			$content = Prompt_Sanitizer::sanitize( $content );
		}

		$prompt = sprintf(
			'Analyze this post for SEO. Return a JSON object with these keys:' . "\n" .
			'- "keyword_density_notes": string, brief note on keyword usage and suggestions.' . "\n" .
			'- "heading_suggestions": array of strings, 2-5 concrete heading (H2/H3) suggestions to improve structure or SEO.' . "\n" .
			'- "meta_suggestions": object with "title" (under 60 chars) and "meta_description" (under 160 chars).' . "\n\n" .
			'TITLE: %s' . "\n\n" .
			'CONTENT:' . "\n%s\n\n" .
			'Return only the JSON object. No markdown fences.',
			$post->post_title,
			$content
		);

		$payload  = array(
			'message'    => $prompt,
			'name'       => 'WordPress SEO Suggestions',
			'sessionKey' => 'wp-pinch-seo-suggest-' . $post_id,
			'wakeMode'   => 'now',
		);
		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}

		$hooks_url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $hooks_url ) ) {
			return array( 'error' => __( 'Gateway URL failed security validation.', 'wp-pinch' ) );
		}

		$response = wp_safe_remote_post(
			$hooks_url,
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Circuit_Breaker::record_failure();
			return array( 'error' => $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			Circuit_Breaker::record_failure();
			return array(
				'error' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Gateway returned HTTP %d.', 'wp-pinch' ),
					$status
				),
			);
		}

		Circuit_Breaker::record_success();
		$body  = wp_remote_retrieve_body( $response );
		$data  = json_decode( $body, true );
		$reply = $data['response'] ?? $data['message'] ?? '';
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return array( 'error' => __( 'Empty gateway response.', 'wp-pinch' ) );
		}
		if ( preg_match( '/```(?:\w*)\s*\n?(.*)\n?```/s', $reply, $m ) ) {
			$reply = trim( $m[1] );
		}
		$parsed = json_decode( $reply, true );
		if ( ! is_array( $parsed ) ) {
			return array( 'error' => __( 'Response was not valid JSON.', 'wp-pinch' ) );
		}

		$keyword_density_notes = isset( $parsed['keyword_density_notes'] ) ? sanitize_textarea_field( (string) $parsed['keyword_density_notes'] ) : '';
		$heading_suggestions   = array();
		if ( ! empty( $parsed['heading_suggestions'] ) && is_array( $parsed['heading_suggestions'] ) ) {
			$heading_suggestions = array_map( 'sanitize_text_field', array_values( $parsed['heading_suggestions'] ) );
		}
		$meta_suggestions = array(
			'title'            => '',
			'meta_description' => '',
		);
		if ( ! empty( $parsed['meta_suggestions'] ) && is_array( $parsed['meta_suggestions'] ) ) {
			$meta_suggestions['title']            = sanitize_text_field( (string) ( $parsed['meta_suggestions']['title'] ?? '' ) );
			$meta_suggestions['meta_description'] = sanitize_text_field( (string) ( $parsed['meta_suggestions']['meta_description'] ?? '' ) );
		}

		return array(
			'keyword_density_notes' => $keyword_density_notes,
			'heading_suggestions'   => $heading_suggestions,
			'meta_suggestions'      => $meta_suggestions,
		);
	}
}
