<?php
/**
 * QuickWin execute handlers.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Feature_Flags;
use WP_Pinch\Governance;
use WP_Pinch\Prompt_Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * QuickWin ability execution methods.
 */
trait QuickWin_Execute_Trait {

	/**
	 * Execute generate-tldr ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_generate_tldr( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'Post not found or insufficient permissions.', 'wp-pinch' ) );
		}
		$summary = self::generate_tldr_for_post( $post_id );
		if ( is_wp_error( $summary ) ) {
			return array( 'error' => $summary->get_error_message() );
		}
		update_post_meta( $post_id, 'wp_pinch_tldr', $summary );
		return array(
			'post_id' => $post_id,
			'tldr'    => $summary,
		);
	}

	/**
	 * Execute suggest-links ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_suggest_links( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$query   = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		$limit   = max( 1, min( absint( $input['limit'] ?? 15 ), 50 ) );

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
				return array( 'error' => __( 'Post not found or insufficient permissions.', 'wp-pinch' ) );
			}
			$search_query = $post->post_title . ' ' . wp_trim_words( wp_strip_all_tags( $post->post_content ), 20 );
		} elseif ( '' !== trim( $query ) ) {
			$search_query = $query;
		} else {
			return array( 'error' => __( 'Provide post_id or query.', 'wp-pinch' ) );
		}

		$search = Analytics_Abilities::execute_search_content(
			array(
				'query'     => $search_query,
				'per_page'  => $limit,
				'post_type' => 'post',
			)
		);
		if ( isset( $search['error'] ) ) {
			return $search;
		}

		$by_search = array();
		foreach ( $search['results'] ?? array() as $r ) {
			if ( isset( $r['id'] ) && ( ! $post_id || (int) $r['id'] !== $post_id ) ) {
				$by_search[ $r['id'] ] = array(
					'id'      => $r['id'],
					'title'   => $r['title'] ?? '',
					'url'     => $r['url'] ?? '',
					'excerpt' => $r['excerpt'] ?? '',
				);
			}
		}

		if ( $post_id ) {
			$related = Analytics_Abilities::execute_related_posts(
				array(
					'post_id' => $post_id,
					'limit'   => $limit,
				)
			);
			if ( ! isset( $related['error'] ) ) {
				foreach ( array_merge( $related['backlinks'] ?? array(), $related['by_taxonomy'] ?? array() ) as $r ) {
					if ( isset( $r['id'] ) && ! isset( $by_search[ $r['id'] ] ) ) {
						$by_search[ $r['id'] ] = array(
							'id'      => $r['id'],
							'title'   => $r['title'] ?? '',
							'url'     => $r['url'] ?? '',
							'excerpt' => '',
						);
					}
				}
			}
		}

		return array(
			'candidates' => array_values( array_slice( $by_search, 0, $limit ) ),
		);
	}

	/**
	 * Execute suggest-terms ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_suggest_terms( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$content = isset( $input['content'] ) && is_string( $input['content'] ) ? $input['content'] : '';
		$limit   = max( 1, min( absint( $input['limit'] ?? 15 ), 50 ) );

		$search_text = '';
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
				return array( 'error' => __( 'Post not found or insufficient permissions.', 'wp-pinch' ) );
			}
			$search_text = $post->post_title . ' ' . wp_trim_words( wp_strip_all_tags( $post->post_content ), 50 );
		} elseif ( '' !== trim( $content ) ) {
			$search_text = wp_trim_words( wp_strip_all_tags( $content ), 50 );
		} else {
			return array( 'error' => __( 'Provide post_id or content.', 'wp-pinch' ) );
		}

		$search       = Analytics_Abilities::execute_search_content(
			array(
				'query'     => $search_text,
				'per_page'  => $limit,
				'post_type' => 'post',
			)
		);
		$category_ids = array();
		$tag_ids      = array();
		foreach ( $search['results'] ?? array() as $r ) {
			$id = isset( $r['id'] ) ? (int) $r['id'] : 0;
			if ( ! $id ) {
				continue;
			}
			$terms = get_the_terms( $id, 'category' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $t ) {
					$category_ids[ $t->term_id ] = $t;
				}
			}
			$terms = get_the_terms( $id, 'post_tag' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $t ) {
					$tag_ids[ $t->term_id ] = $t;
				}
			}
		}

		$text_lower = mb_strtolower( $search_text );
		$all_cats   = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);
		$all_tags   = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $all_cats ) ? $all_cats : array() as $t ) {
			if ( mb_strlen( $t->name ) < 2 ) {
				continue;
			}
			if ( false !== mb_strpos( $text_lower, mb_strtolower( $t->name ) ) || false !== mb_strpos( $text_lower, mb_strtolower( $t->slug ) ) ) {
				$category_ids[ $t->term_id ] = $t;
			}
		}
		foreach ( is_array( $all_tags ) ? $all_tags : array() as $t ) {
			if ( mb_strlen( $t->name ) < 2 ) {
				continue;
			}
			if ( false !== mb_strpos( $text_lower, mb_strtolower( $t->name ) ) || false !== mb_strpos( $text_lower, mb_strtolower( $t->slug ) ) ) {
				$tag_ids[ $t->term_id ] = $t;
			}
		}

		$format_term = function ( $t ) {
			return array(
				'id'   => $t->term_id,
				'name' => $t->name,
				'slug' => $t->slug,
			);
		};

		return array(
			'suggested_categories' => array_values( array_slice( array_map( $format_term, array_values( $category_ids ) ), 0, $limit ) ),
			'suggested_tags'       => array_values( array_slice( array_map( $format_term, array_values( $tag_ids ) ), 0, $limit ) ),
		);
	}

	/**
	 * Execute quote-bank ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_quote_bank( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$max     = max( 1, min( absint( $input['max'] ?? 15 ), 50 ) );
		if ( ! $post_id ) {
			return array( 'error' => __( 'post_id is required.', 'wp-pinch' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'read_post', $post_id ) ) {
			return array( 'error' => __( 'Post not found or insufficient permissions.', 'wp-pinch' ) );
		}

		$text   = wp_strip_all_tags( $post->post_content );
		$text   = preg_replace( '/\s+/', ' ', $text );
		$pieces = preg_split( '/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$quotes = array();
		foreach ( $pieces as $s ) {
			$s = trim( $s );
			if ( '' === $s ) {
				continue;
			}
			$len = mb_strlen( $s );
			if ( $len >= 40 && $len <= 300 ) {
				if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
					$s = Prompt_Sanitizer::sanitize( $s );
				}
				$quotes[] = $s;
			}
		}
		$quotes = array_slice( array_unique( $quotes ), 0, $max );

		return array(
			'post_id' => $post_id,
			'quotes'  => $quotes,
		);
	}

	/**
	 * Execute what-do-i-know ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_what_do_i_know( array $input ): array {
		$query    = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		$per_page = max( 1, min( absint( $input['per_page'] ?? 10 ), 25 ) );
		if ( '' === trim( $query ) ) {
			return array( 'error' => __( 'query is required.', 'wp-pinch' ) );
		}
		$synthesize_result = Analytics_Abilities::execute_synthesize(
			array(
				'query'     => $query,
				'per_page'  => $per_page,
				'post_type' => 'post',
			)
		);
		if ( isset( $synthesize_result['error'] ) ) {
			return $synthesize_result;
		}
		$posts = $synthesize_result['posts'] ?? array();
		$ids   = array_column( $posts, 'id' );
		if ( empty( $posts ) ) {
			return array(
				'answer'          => __( 'No relevant posts found for that query.', 'wp-pinch' ),
				'source_post_ids' => array(),
			);
		}
		$context = '';
		foreach ( $posts as $p ) {
			$context .= sprintf( "[%d] %s\n%s\n\n", $p['id'], $p['title'], $p['content_snippet'] ?? $p['excerpt'] ?? '' );
		}
		$prompt = sprintf(
			"Based only on the following excerpts from this site's posts, answer the question in 1-3 short paragraphs. Cite post IDs in brackets when relevant (e.g. [123]).\n\nEXCERPTS:\n%s\nQUESTION: %s\n\nANSWER:",
			$context,
			$query
		);
		$reply  = self::request_gateway_message( $prompt, 'wp-pinch-what-do-i-know-' . wp_create_nonce( 'wdik' ) );
		if ( is_wp_error( $reply ) ) {
			return array(
				'error'           => $reply->get_error_message(),
				'source_post_ids' => $ids,
			);
		}
		return array(
			'answer'          => $reply,
			'source_post_ids' => $ids,
		);
	}

	/**
	 * Execute project-assembly ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_project_assembly( array $input ): array {
		$post_ids     = array_map( 'absint', (array) ( $input['post_ids'] ?? array() ) );
		$post_ids     = array_filter( array_unique( $post_ids ) );
		$prompt_extra = sanitize_text_field( (string) ( $input['prompt'] ?? '' ) );
		$save_draft   = ! empty( $input['save_as_draft'] );
		if ( empty( $post_ids ) ) {
			return array( 'error' => __( 'post_ids (non-empty array) is required.', 'wp-pinch' ) );
		}
		$sources = array();
		foreach ( $post_ids as $pid ) {
			if ( ! current_user_can( 'read_post', $pid ) ) {
				continue;
			}
			$post = get_post( $pid );
			if ( ! $post ) {
				continue;
			}
			$title   = $post->post_title;
			$content = wp_strip_all_tags( $post->post_content );
			if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
				$title   = Prompt_Sanitizer::sanitize_string( $title );
				$content = Prompt_Sanitizer::sanitize( $content );
			}
			$sources[] = array(
				'id'      => $post->ID,
				'title'   => $title,
				'content' => $content,
			);
		}
		if ( empty( $sources ) ) {
			return array( 'error' => __( 'No readable posts found for the given IDs.', 'wp-pinch' ) );
		}
		$context = '';
		foreach ( $sources as $s ) {
			$content  = mb_strlen( $s['content'] ) > 3000 ? mb_substr( $s['content'], 0, 3000 ) . '...' : $s['content'];
			$context .= sprintf( "--- Post [%d]: %s ---\n%s\n\n", $s['id'], $s['title'], $content );
		}
		$instruction = __( 'Weave the following posts into one coherent draft with inline citations (e.g. [123]). Use clear structure and preserve key points.', 'wp-pinch' );
		if ( '' !== trim( $prompt_extra ) ) {
			$instruction .= ' ' . trim( $prompt_extra );
		}
		$prompt = $instruction . "\n\n" . $context . "\n\nDRAFT:";
		$reply  = self::request_gateway_message( $prompt, 'wp-pinch-project-assembly-' . wp_create_nonce( 'pa' ) );
		if ( is_wp_error( $reply ) ) {
			return array( 'error' => $reply->get_error_message() );
		}
		$result = array( 'draft' => $reply );
		if ( $save_draft ) {
			$admins   = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			);
			$author   = ! empty( $admins ) ? (int) $admins[0] : get_current_user_id();
			$title    = __( 'Assembled draft', 'wp-pinch' ) . ' ' . gmdate( 'Y-m-d H:i' );
			$draft_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_content' => $reply,
					'post_status'  => 'draft',
					'post_author'  => $author,
					'post_type'    => 'post',
				),
				true
			);
			if ( ! is_wp_error( $draft_id ) ) {
				$result['saved_post_id'] = $draft_id;
				$result['edit_url']      = admin_url( 'post.php?post=' . $draft_id . '&action=edit' );
			}
		}
		return $result;
	}

	/**
	 * Execute knowledge-graph ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_knowledge_graph( array $input ): array {
		$post_type     = sanitize_key( (string) ( $input['post_type'] ?? 'post' ) );
		$limit         = max( 1, min( absint( $input['limit'] ?? 200 ), 500 ) );
		$include_terms = ! empty( $input['include_terms'] );
		$pt_obj        = get_post_type_object( $post_type );
		if ( ! $pt_obj || ! current_user_can( $pt_obj->cap->edit_posts ) ) {
			return array( 'error' => __( 'Invalid post type or insufficient permissions.', 'wp-pinch' ) );
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$node_ids   = array();
		$nodes      = array();
		$edges      = array();
		$link_edges = array();

		foreach ( $posts as $post ) {
			$node_ids[ $post->ID ] = true;
			$nodes[]               = array(
				'id'      => 'post-' . $post->ID,
				'type'    => 'post',
				'title'   => $post->post_title,
				'url'     => get_permalink( $post->ID ),
				'post_id' => $post->ID,
			);
			if ( preg_match_all( '/href=["\']([^"\']+)["\']/', $post->post_content, $matches ) ) {
				foreach ( $matches[1] as $href ) {
					$linked_id = url_to_postid( $href );
					if ( $linked_id && $linked_id !== $post->ID && isset( $node_ids[ $linked_id ] ) ) {
						$key                = $post->ID . '-' . $linked_id;
						$link_edges[ $key ] = array(
							'from' => $post->ID,
							'to'   => $linked_id,
							'type' => 'link',
						);
					}
				}
			}
		}

		foreach ( $link_edges as $edge ) {
			$edges[] = array(
				'source' => 'post-' . $edge['from'],
				'target' => 'post-' . $edge['to'],
				'type'   => 'link',
			);
		}

		$term_nodes = array();
		$post_terms = array();
		foreach ( $posts as $post ) {
			$terms = wp_get_post_terms( $post->ID, array( 'category', 'post_tag' ) );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			$post_terms[ $post->ID ] = array();
			foreach ( $terms as $t ) {
				$post_terms[ $post->ID ][] = $t->term_id;
				if ( $include_terms ) {
					$term_nodes[ 'term-' . $t->term_id ] = array(
						'id'       => 'term-' . $t->term_id,
						'type'     => 'term',
						'title'    => $t->name,
						'taxonomy' => $t->taxonomy,
					);
				}
			}
		}
		foreach ( $post_terms as $pid1 => $terms1 ) {
			foreach ( $post_terms as $pid2 => $terms2 ) {
				if ( $pid1 >= $pid2 ) {
					continue;
				}
				if ( ! empty( array_intersect( $terms1, $terms2 ) ) ) {
					$edges[] = array(
						'source' => 'post-' . $pid1,
						'target' => 'post-' . $pid2,
						'type'   => 'shared_term',
					);
				}
			}
		}

		if ( $include_terms ) {
			$nodes = array_merge( $nodes, array_values( $term_nodes ) );
		}

		return array(
			'nodes' => $nodes,
			'edges' => $edges,
		);
	}

	/**
	 * Execute find-similar ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_find_similar( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$query   = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		$limit   = max( 1, min( absint( $input['limit'] ?? 15 ), 50 ) );

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || ! current_user_can( 'read_post', $post_id ) ) {
				return array( 'error' => __( 'Post not found or insufficient permissions.', 'wp-pinch' ) );
			}
			$search_query = $post->post_title . ' ' . wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		} elseif ( '' !== trim( $query ) ) {
			$search_query = $query;
		} else {
			return array( 'error' => __( 'Provide post_id or query.', 'wp-pinch' ) );
		}

		$search = Analytics_Abilities::execute_search_content(
			array(
				'query'     => $search_query,
				'per_page'  => $limit,
				'post_type' => 'post',
			)
		);
		if ( isset( $search['error'] ) ) {
			return $search;
		}

		$seen   = array();
		$result = array();
		foreach ( $search['results'] ?? array() as $r ) {
			$id = (int) ( $r['id'] ?? 0 );
			if ( $id && $id !== $post_id && ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$result[]    = array(
					'id'      => $id,
					'title'   => $r['title'] ?? '',
					'url'     => $r['url'] ?? '',
					'excerpt' => $r['excerpt'] ?? '',
				);
			}
		}

		if ( $post_id ) {
			$related = Analytics_Abilities::execute_related_posts(
				array(
					'post_id' => $post_id,
					'limit'   => $limit,
				)
			);
			if ( ! isset( $related['error'] ) ) {
				foreach ( array_merge( $related['backlinks'] ?? array(), $related['by_taxonomy'] ?? array() ) as $r ) {
					$id = (int) ( $r['id'] ?? 0 );
					if ( $id && ! isset( $seen[ $id ] ) ) {
						$seen[ $id ] = true;
						$p           = get_post( $id );
						$result[]    = array(
							'id'      => $id,
							'title'   => $r['title'] ?? ( $p ? $p->post_title : '' ),
							'url'     => $r['url'] ?? '',
							'excerpt' => $p ? wp_trim_words( wp_strip_all_tags( $p->post_content ), 20 ) : '',
						);
					}
				}
			}
		}

		$result = array_slice( $result, 0, $limit );
		return array(
			'similar' => $result,
			'count'   => count( $result ),
		);
	}

	/**
	 * Execute spaced-resurfacing ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_spaced_resurfacing( array $input ): array {
		$days     = max( 1, min( absint( $input['days'] ?? 30 ), 365 ) );
		$category = sanitize_text_field( (string) ( $input['category'] ?? '' ) );
		$tag      = sanitize_text_field( (string) ( $input['tag'] ?? '' ) );
		$limit    = max( 1, min( absint( $input['limit'] ?? 50 ), 200 ) );
		$findings = Governance::get_spaced_resurfacing_findings( $days, $category, $tag, $limit );
		return array(
			'days'  => $days,
			'posts' => $findings,
			'count' => count( $findings ),
		);
	}
}
