<?php
/**
 * Quick-win abilities — TL;DR, suggest links/terms, quote bank, what-do-i-know, project assembly, knowledge graph, find similar, spaced resurfacing.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;
use WP_Pinch\Circuit_Breaker;
use WP_Pinch\Feature_Flags;
use WP_Pinch\Molt;
use WP_Pinch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Quick-win abilities (TL;DR, suggestions, retrieval, assembly, graph, resurfacing).
 */
class QuickWin_Abilities {
	use QuickWin_Execute_Trait;

	/**
	 * Register quick-win abilities and the TL;DR-on-publish hook.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'transition_post_status', array( __CLASS__, 'maybe_generate_tldr_on_publish' ), 10, 3 );

		Abilities::register_ability(
			'wp-pinch/generate-tldr',
			__( 'Generate TL;DR', 'wp-pinch' ),
			__( 'Generate a 1–2 sentence summary for a post and store it in post meta (wp_pinch_tldr). Used automatically on publish when Molt is enabled.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post ID to generate TL;DR for.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_generate_tldr' )
		);

		Abilities::register_ability(
			'wp-pinch/suggest-links',
			__( 'Link Suggester', 'wp-pinch' ),
			__( 'Given a post ID or text snippet, return existing posts that are good link candidates (by search and related-posts).', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post ID to suggest links for (uses its title/excerpt as query).',
					),
					'query'   => array(
						'type'        => 'string',
						'description' => 'Search query when post_id is not provided.',
					),
					'limit'   => array(
						'type'    => 'integer',
						'default' => 15,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_suggest_links' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/suggest-terms',
			__( 'Suggest Categories & Tags', 'wp-pinch' ),
			__( 'Given a draft post ID or content, return suggested categories and tags (by content similarity and term match).', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Draft or published post ID to suggest terms for.',
					),
					'content' => array(
						'type'        => 'string',
						'description' => 'Draft body text when post_id is not provided.',
					),
					'limit'   => array(
						'type'    => 'integer',
						'default' => 15,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_suggest_terms' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/quote-bank',
			__( 'Quote Bank', 'wp-pinch' ),
			__( 'Extract notable sentences from a post (heuristic: medium-length sentences). Returns a list of strings; optional save step can be added later.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post ID to extract quotes from.',
					),
					'max'     => array(
						'type'    => 'integer',
						'default' => 15,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_quote_bank' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/what-do-i-know',
			__( 'What do I know about X?', 'wp-pinch' ),
			__( 'Natural-language query: search content, synthesize with the gateway, and return a coherent answer plus source post IDs. Flagship retrieval experience.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'query' ),
				'properties' => array(
					'query'    => array(
						'type'        => 'string',
						'description' => 'Natural-language question (e.g. "What have I written about pricing?").',
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 10,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_what_do_i_know' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/project-assembly',
			__( 'Project Assembly', 'wp-pinch' ),
			__( 'Given a list of post IDs and optional prompt, produce one drafted post (or long text) weaving those posts with citations. Returns draft text; optionally save as draft.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_ids' ),
				'properties' => array(
					'post_ids'      => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'List of post IDs to weave together.',
					),
					'prompt'        => array(
						'type'        => 'string',
						'description' => 'Optional instruction (e.g. "Focus on the launch timeline").',
					),
					'save_as_draft' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_project_assembly' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/spaced-resurfacing',
			__( 'Spaced Resurfacing', 'wp-pinch' ),
			__( 'List posts not updated in N days (optionally filtered by category or tag). Surface notes worth revisiting.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'days'     => array(
						'type'    => 'integer',
						'default' => 30,
					),
					'category' => array(
						'type'    => 'string',
						'default' => '',
					),
					'tag'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'limit'    => array(
						'type'    => 'integer',
						'default' => 50,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_spaced_resurfacing' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/find-similar',
			__( 'Find Similar', 'wp-pinch' ),
			__( 'Given a post ID or text query, return related posts (by search and taxonomy). MVP: similar by title/excerpt; full semantic search with embeddings may follow.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post ID to find similar posts for.',
					),
					'query'   => array(
						'type'        => 'string',
						'description' => 'Text query when post_id is not provided.',
					),
					'limit'   => array(
						'type'    => 'integer',
						'default' => 15,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_find_similar' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/knowledge-graph',
			__( 'Knowledge Graph', 'wp-pinch' ),
			__( 'Return a graph of nodes (posts, optionally terms) and edges (content links, shared tags). Payload suitable for external visualization.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_type'     => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'limit'         => array(
						'type'    => 'integer',
						'default' => 200,
					),
					'include_terms' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_knowledge_graph' ),
			true
		);
	}

	/**
	 * When a post is published, generate TL;DR via Molt and store in post meta (if Molt is enabled).
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public static function maybe_generate_tldr_on_publish( string $new_status, string $old_status, $post ): void {
		if ( 'publish' !== $new_status || ! $post || 'post' !== $post->post_type ) {
			return;
		}
		if ( ! Feature_Flags::is_enabled( 'molt' ) ) {
			return;
		}
		$post_id = (int) $post->ID;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$summary = self::generate_tldr_for_post( $post_id );
		if ( is_string( $summary ) && '' !== trim( $summary ) ) {
			update_post_meta( $post_id, 'wp_pinch_tldr', $summary );
		}
	}

	/**
	 * Generate a 1–2 sentence summary for a post using Molt (summary output).
	 *
	 * @param int $post_id Post ID.
	 * @return string|\WP_Error Summary string or error.
	 */
	public static function generate_tldr_for_post( int $post_id ) {
		if ( ! Feature_Flags::is_enabled( 'molt' ) ) {
			return new \WP_Error( 'molt_disabled', __( 'Molt is not enabled.', 'wp-pinch' ), array( 'status' => 503 ) );
		}
		$result = Molt::molt( $post_id, array( 'summary' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$summary = isset( $result['summary'] ) && is_string( $result['summary'] ) ? trim( $result['summary'] ) : '';
		return '' !== $summary ? $summary : new \WP_Error( 'empty_summary', __( 'Molt returned an empty summary.', 'wp-pinch' ), array( 'status' => 502 ) );
	}

	/**
	 * Call the OpenClaw gateway with a single message and return the reply text.
	 *
	 * @param string $message     User or system message.
	 * @param string $session_key Optional session key for the request.
	 * @return string|\WP_Error Reply text or error.
	 */
	private static function request_gateway_message( string $message, string $session_key = 'wp-pinch-ability' ) {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		if ( '' === $gateway_url || '' === $api_token ) {
			return new \WP_Error( 'not_configured', __( 'Gateway is not configured.', 'wp-pinch' ), array( 'status' => 503 ) );
		}
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			return new \WP_Error( 'gateway_unavailable', __( 'Gateway is temporarily unavailable.', 'wp-pinch' ), array( 'status' => 503 ) );
		}
		$url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'invalid_gateway', __( 'Gateway URL failed validation.', 'wp-pinch' ), array( 'status' => 502 ) );
		}
		$payload  = array(
			'message'    => $message,
			'name'       => 'WordPress Abilities',
			'sessionKey' => $session_key,
			'wakeMode'   => 'now',
		);
		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}
		$response = wp_safe_remote_post(
			$url,
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
			if ( Feature_Flags::is_enabled( 'circuit_breaker' ) ) {
				Circuit_Breaker::record_failure();
			}
			return new \WP_Error( 'gateway_error', $response->get_error_message(), array( 'status' => 502 ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			if ( Feature_Flags::is_enabled( 'circuit_breaker' ) ) {
				Circuit_Breaker::record_failure();
			}
			return new \WP_Error(
				'gateway_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Gateway returned HTTP %d.', 'wp-pinch' ),
					$status
				),
				array( 'status' => 502 )
			);
		}
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) ) {
			Circuit_Breaker::record_success();
		}
		$body  = wp_remote_retrieve_body( $response );
		$data  = json_decode( $body, true );
		$reply = isset( $data['response'] ) && is_string( $data['response'] ) ? $data['response'] : ( isset( $data['message'] ) && is_string( $data['message'] ) ? $data['message'] : '' );
		if ( '' === trim( $reply ) ) {
			return new \WP_Error( 'empty_response', __( 'Gateway returned an empty response.', 'wp-pinch' ), array( 'status' => 502 ) );
		}
		return $reply;
	}
}
