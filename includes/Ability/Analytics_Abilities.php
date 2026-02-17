<?php
/**
 * Analytics and maintenance abilities — site health, content health, search, export, digest, related posts, synthesize.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;
use WP_Pinch\Circuit_Breaker;
use WP_Pinch\Feature_Flags;
use WP_Pinch\Governance;
use WP_Pinch\Plugin;
use WP_Pinch\Prompt_Sanitizer;
use WP_Pinch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics abilities.
 */
class Analytics_Abilities {

	/**
	 * Register analytics abilities.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Abilities::register_ability(
			'wp-pinch/site-health',
			__( 'Site Health', 'wp-pinch' ),
			__( 'Get site health summary: PHP, WordPress, database, disk usage.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => new \stdClass(),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_site_health' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/content-health-report',
			__( 'Content Health Report', 'wp-pinch' ),
			__( 'Get a content health report: missing alt text, broken internal links, thin content, orphaned media.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'limit'     => array(
						'type'        => 'integer',
						'default'     => 50,
						'description' => 'Max items per category (1–100).',
					),
					'min_words' => array(
						'type'        => 'integer',
						'default'     => 300,
						'description' => 'Minimum word count to not flag as thin content.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_content_health_report' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/recent-activity',
			__( 'Recent Activity', 'wp-pinch' ),
			__( 'Get recent posts, comments, and user registrations.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 10,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_recent_activity' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/search-content',
			__( 'Search Content', 'wp-pinch' ),
			__( 'Full-text search across posts, pages, and custom post types.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'query' ),
				'properties' => array(
					'query'     => array( 'type' => 'string' ),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'any',
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_search_content' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/export-data',
			__( 'Export Data', 'wp-pinch' ),
			__( 'Export post, user, or comment data as JSON.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'type' ),
				'properties' => array(
					'type'     => array(
						'type' => 'string',
						'enum' => array( 'posts', 'users', 'comments' ),
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 100,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			array( 'type' => 'object' ),
			'export',
			array( __CLASS__, 'execute_export_data' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/site-digest',
			__( 'Memory Bait (Site Digest)', 'wp-pinch' ),
			__( 'Compact export of recent posts: title, excerpt, and key taxonomy terms. For agent memory-core or system prompt so the agent knows your site.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 10,
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_site_digest' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/related-posts',
			__( 'Echo Net (Related Posts)', 'wp-pinch' ),
			__( 'Given a post ID, return posts that link to it (backlinks) or share taxonomy terms. Enables "you wrote about X before" and graph-like discovery.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post ID to find related posts for.',
					),
					'limit'   => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_related_posts' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/synthesize',
			__( 'Weave (Synthesize)', 'wp-pinch' ),
			__( 'Given a query, search posts, fetch matching content, and return a payload for LLM synthesis. First-draft synthesis; human refines.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'query' ),
				'properties' => array(
					'query'     => array( 'type' => 'string' ),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 10,
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_synthesize' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/analytics-narratives',
			__( 'Analytics Narratives', 'wp-pinch' ),
			__( 'Turn site digest or recent activity data into a brief narrative: what happened this week, what is new.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'source' => array(
						'type'        => 'string',
						'default'     => 'site_digest',
						'description' => __( 'Data source: site_digest or recent_activity.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_analytics_narratives' )
		);

		Abilities::register_ability(
			'wp-pinch/submit-conversational-form',
			__( 'Submit Conversational Form', 'wp-pinch' ),
			__( 'Submit collected form data from a conversation. Provide fields (name/value pairs) and optionally a webhook URL to POST the payload to.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'fields' ),
				'properties' => array(
					'fields'      => array(
						'type'        => 'array',
						'description' => __( 'Form fields: array of objects with "name" and "value".', 'wp-pinch' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'  => array( 'type' => 'string' ),
								'value' => array( 'type' => 'string' ),
							),
						),
					),
					'webhook_url' => array(
						'type'        => 'string',
						'description' => __( 'Optional URL to POST the form payload to (JSON).', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_submit_conversational_form' )
		);
	}

	/**
	 * Get site health info.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_site_health( array $input ): array {
		global $wpdb;

		$table_count_cache_key = 'site_health_tables_' . $wpdb->prefix;
		$table_count           = wp_cache_get( $table_count_cache_key, 'wp_pinch_abilities' );
		if ( false === $table_count ) {
			$table_count = count( $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) ) );
			wp_cache_set( $table_count_cache_key, $table_count, 'wp_pinch_abilities', 300 );
		}

		return array(
			'wordpress' => array(
				'version'    => get_bloginfo( 'version' ),
				'multisite'  => is_multisite(),
				'debug_mode' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			),
			'php'       => array(
				'version'      => PHP_VERSION,
				'memory_limit' => ini_get( 'memory_limit' ),
				'max_upload'   => wp_max_upload_size(),
			),
			'database'  => array(
				'server' => $wpdb->db_server_info(),
				'prefix' => $wpdb->prefix,
				'tables' => $table_count,
			),
			'content'   => array(
				'posts'    => (int) wp_count_posts()->publish,
				'pages'    => (int) wp_count_posts( 'page' )->publish,
				'comments' => (int) wp_count_comments()->total_comments,
				'users'    => (int) count_users()['total_users'],
				'media'    => (int) wp_count_posts( 'attachment' )->inherit,
			),
			'plugins'   => array(
				'active' => count( get_option( 'active_plugins', array() ) ),
			),
			'theme'     => get_stylesheet(),
			'timezone'  => wp_timezone_string(),
		);
	}

	/**
	 * Content health report.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_content_health_report( array $input ): array {
		$limit     = max( 1, min( absint( $input['limit'] ?? 50 ), 100 ) );
		$min_words = max( 1, min( absint( $input['min_words'] ?? 300 ), 5000 ) );

		return array(
			'missing_alt'           => Governance::get_missing_alt_findings( $limit ),
			'broken_internal_links' => Governance::get_broken_internal_links_findings( $limit ),
			'thin_content'          => Governance::get_thin_content_findings( $min_words, $limit ),
			'orphaned_media'        => Governance::get_orphaned_media_findings( $limit ),
		);
	}

	/**
	 * Get recent site activity.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_recent_activity( array $input ): array {
		$limit = min( absint( $input['limit'] ?? 10 ), 50 );

		$recent_posts = get_posts(
			array(
				'numberposts' => $limit,
				'post_status' => array( 'publish', 'draft', 'pending' ),
				'orderby'     => 'modified',
				'order'       => 'DESC',
			)
		);

		$recent_comments = get_comments(
			array(
				'number'  => $limit,
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			)
		);

		$author_ids = array_unique(
			array_map(
				function ( $p ) {
					return (int) $p->post_author;
				},
				$recent_posts
			)
		);

		$author_map = array();
		if ( ! empty( $author_ids ) ) {
			$authors = get_users(
				array(
					'include' => $author_ids,
					'fields'  => array( 'ID', 'display_name' ),
				)
			);
			foreach ( $authors as $a ) {
				$author_map[ (int) $a->ID ] = $a->display_name;
			}
		}

		return array(
			'recent_posts'    => array_map(
				function ( $p ) use ( $author_map ) {
					return array(
						'id'       => $p->ID,
						'title'    => $p->post_title,
						'status'   => $p->post_status,
						'modified' => $p->post_modified,
						'author'   => $author_map[ (int) $p->post_author ] ?? __( 'Unknown', 'wp-pinch' ),
					);
				},
				$recent_posts
			),
			'recent_comments' => array_map(
				function ( $c ) {
					return array(
						'id'      => (int) $c->comment_ID,
						'author'  => $c->comment_author,
						'post_id' => (int) $c->comment_post_ID,
						'status'  => wp_get_comment_status( $c ),
						'date'    => $c->comment_date,
					);
				},
				$recent_comments
			),
		);
	}

	/**
	 * Search content.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_search_content( array $input ): array {
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? 'any' ) );

		if ( 'any' !== $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
				return array( 'error' => __( 'Insufficient permissions for this post type.', 'wp-pinch' ) );
			}
		}

		$statuses = current_user_can( 'read_private_posts' )
			? array( 'publish', 'draft', 'pending', 'private' )
			: array( 'publish', 'draft', 'pending' );

		$query = new \WP_Query(
			array(
				's'              => sanitize_text_field( (string) ( $input['query'] ?? '' ) ),
				'post_type'      => $post_type,
				'posts_per_page' => max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) ),
				'post_status'    => $statuses,
			)
		);

		$results = array();
		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'type'     => $post->post_type,
				'status'   => $post->post_status,
				'excerpt'  => wp_trim_words( $post->post_content, 30 ),
				'url'      => get_permalink( $post->ID ),
				'modified' => $post->post_modified,
			);
		}

		return array(
			'results' => $results,
			'total'   => $query->found_posts,
		);
	}

	/**
	 * Export data as JSON.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_export_data( array $input ): array {
		$type     = sanitize_key( (string) ( $input['type'] ?? '' ) );
		$per_page = max( 1, min( absint( $input['per_page'] ?? 100 ), 500 ) );
		$page     = max( 1, absint( $input['page'] ?? 1 ) );

		switch ( $type ) {
			case 'posts':
				$query = new \WP_Query(
					array(
						'post_type'      => 'post',
						'posts_per_page' => $per_page,
						'paged'          => $page,
						'post_status'    => 'any',
					)
				);
				return array(
					'type'  => 'posts',
					'data'  => array_map( array( Abilities::class, 'format_post' ), $query->posts ),
					'total' => $query->found_posts,
					'page'  => $page,
				);

			case 'users':
				$user_query = new \WP_User_Query(
					array(
						'number' => $per_page,
						'paged'  => $page,
					)
				);
				return array(
					'type'  => 'users',
					'data'  => array_map(
						function ( $u ) {
							return array(
								'id'           => $u->ID,
								'login'        => $u->user_login,
								'display_name' => $u->display_name,
								'roles'        => $u->roles,
								'registered'   => $u->user_registered,
							);
						},
						$user_query->get_results()
					),
					'total' => $user_query->get_total(),
					'page'  => $page,
				);

			case 'comments':
				$comments = get_comments(
					array(
						'number' => $per_page,
						'paged'  => $page,
					)
				);
				$total    = (int) get_comments( array( 'count' => true ) );
				return array(
					'type'  => 'comments',
					'data'  => array_map(
						function ( $c ) {
							return array(
								'id'      => (int) $c->comment_ID,
								'post_id' => (int) $c->comment_post_ID,
								'author'  => $c->comment_author,
								'content' => $c->comment_content,
								'status'  => wp_get_comment_status( $c ),
								'date'    => $c->comment_date,
							);
						},
						$comments
					),
					'total' => (int) $total,
					'page'  => $page,
				);

			default:
				return array( 'error' => __( 'Invalid export type.', 'wp-pinch' ) );
		}
	}

	/**
	 * Memory Bait: compact site digest.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_site_digest( array $input ): array {
		$per_page  = max( 1, min( absint( $input['per_page'] ?? 10 ), 50 ) );
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? 'post' ) );
		$pt_obj    = get_post_type_object( $post_type );
		if ( ! $pt_obj || ! current_user_can( $pt_obj->cap->edit_posts ) ) {
			return array( 'error' => __( 'Invalid post type or insufficient permissions.', 'wp-pinch' ) );
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'orderby'        => 'date',
			)
		);

		$public_taxonomies = array();
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $tax ) {
			if ( $tax->public ) {
				$public_taxonomies[] = $tax->name;
			}
		}

		$items = array();
		foreach ( $query->posts as $post ) {
			$terms = array();
			foreach ( $public_taxonomies as $tax_name ) {
				$term_list = wp_get_post_terms( $post->ID, $tax_name );
				if ( is_array( $term_list ) && ! empty( $term_list ) ) {
					$names = wp_list_pluck( $term_list, 'name' );
					if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
						$names = array_map( array( Prompt_Sanitizer::class, 'sanitize_string' ), $names );
					}
					$terms[ $tax_name ] = $names;
				}
			}
			$tldr    = get_post_meta( $post->ID, 'wp_pinch_tldr', true );
			$title   = $post->post_title;
			$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
			if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
				$title   = Prompt_Sanitizer::sanitize_string( $title );
				$excerpt = Prompt_Sanitizer::sanitize( $excerpt );
				$tldr    = is_string( $tldr ) && '' !== trim( $tldr ) ? Prompt_Sanitizer::sanitize_string( $tldr ) : null;
			} else {
				$tldr = is_string( $tldr ) && '' !== trim( $tldr ) ? $tldr : null;
			}
			$items[] = array(
				'id'         => $post->ID,
				'title'      => $title,
				'excerpt'    => $excerpt,
				'tldr'       => $tldr,
				'url'        => get_permalink( $post->ID ),
				'date'       => $post->post_date,
				'taxonomies' => $terms,
			);
		}

		return array(
			'items'    => $items,
			'total'    => $query->found_posts,
			'site_url' => home_url( '/' ),
		);
	}

	/**
	 * Echo Net: related posts (backlinks and by taxonomy).
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_related_posts( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$limit   = max( 1, min( absint( $input['limit'] ?? 20 ), 50 ) );
		$post    = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'Post not found or insufficient permissions.', 'wp-pinch' ) );
		}

		$permalink    = get_permalink( $post_id );
		$url_variants = array(
			$permalink,
			home_url( '/?p=' . $post_id ),
			wp_get_shortlink( $post_id ),
		);
		$url_variants = array_filter( array_unique( $url_variants ) );

		global $wpdb;
		$backlink_ids = array();
		foreach ( $url_variants as $url ) {
			if ( '' === $url ) {
				continue;
			}
			$escaped      = $wpdb->esc_like( $url );
			$ids          = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT ID FROM ' . $wpdb->posts . " WHERE post_type IN ('post','page') AND post_status = 'publish' AND ID != %d AND post_content LIKE %s LIMIT %d",
					$post_id,
					'%' . $escaped . '%',
					$limit
				)
			);
			$backlink_ids = array_merge( $backlink_ids, array_map( 'absint', (array) $ids ) );
		}
		$backlink_ids = array_unique( array_slice( $backlink_ids, 0, $limit ) );

		$term_taxonomy_ids = array();
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) {
			if ( ! $tax->public ) {
				continue;
			}
			$terms = wp_get_post_terms( $post_id, $tax->name );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $t ) {
					$term_taxonomy_ids[] = (int) $t->term_taxonomy_id;
				}
			}
		}
		$term_taxonomy_ids = array_unique( $term_taxonomy_ids );
		$by_taxonomy_ids   = array();
		if ( ! empty( $term_taxonomy_ids ) ) {
			$in_placeholders = implode( ',', array_fill( 0, count( $term_taxonomy_ids ), '%d' ) );
			$by_taxonomy_ids = $wpdb->get_col(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in_placeholders is placeholder list only (e.g. '%d,%d'); values in array_merge.
					'SELECT object_id FROM ' . $wpdb->term_relationships . ' WHERE object_id != %d AND term_taxonomy_id IN (' . $in_placeholders . ') LIMIT %d',
					array_merge( array( $post_id ), $term_taxonomy_ids, array( $limit ) )
				)
			);
			$by_taxonomy_ids = array_map( 'absint', (array) $by_taxonomy_ids );
		}

		$all_ids  = array_unique( array_merge( $backlink_ids, array_diff( $by_taxonomy_ids, $backlink_ids ) ) );
		$post_map = array();
		if ( ! empty( $all_ids ) ) {
			$fetched = get_posts(
				array(
					'post__in'            => $all_ids,
					'post_type'           => array( 'post', 'page' ),
					'post_status'         => 'publish',
					'posts_per_page'      => -1,
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
				)
			);
			foreach ( $fetched as $p ) {
				$title = $p->post_title;
				if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
					$title = Prompt_Sanitizer::sanitize_string( $title );
				}
				$post_map[ $p->ID ] = array(
					'id'    => (int) $p->ID,
					'title' => $title,
					'url'   => get_permalink( $p->ID ),
					'type'  => $p->post_type,
				);
			}
		}

		$format_from_map = function ( $id ) use ( $post_map ) {
			return $post_map[ $id ] ?? null;
		};

		return array(
			'post_id'     => $post_id,
			'backlinks'   => array_values( array_filter( array_map( $format_from_map, $backlink_ids ) ) ),
			'by_taxonomy' => array_values( array_filter( array_map( $format_from_map, array_diff( $by_taxonomy_ids, $backlink_ids ) ) ) ),
		);
	}

	/**
	 * Weave: search posts and return payload for LLM synthesis.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_synthesize( array $input ): array {
		$query     = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		$per_max   = (int) apply_filters( 'wp_pinch_synthesize_per_page_max', 25 );
		$per_page  = max( 1, min( absint( $input['per_page'] ?? 10 ), $per_max ) );
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? 'post' ) );
		if ( '' === trim( $query ) ) {
			return array( 'error' => __( 'Query is required.', 'wp-pinch' ) );
		}
		$pt_obj = get_post_type_object( $post_type );
		if ( ! $pt_obj || ! current_user_can( $pt_obj->cap->edit_posts ) ) {
			return array( 'error' => __( 'Invalid post type or insufficient permissions.', 'wp-pinch' ) );
		}

		$q = new \WP_Query(
			array(
				's'              => $query,
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => $per_page,
			)
		);

		$excerpt_words = (int) apply_filters( 'wp_pinch_synthesize_excerpt_words', 40 );
		$snippet_words = (int) apply_filters( 'wp_pinch_synthesize_content_snippet_words', 75 );

		$posts = array();
		foreach ( $q->posts as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}
			$content = wp_strip_all_tags( $post->post_content );
			if ( Feature_Flags::is_enabled( 'prompt_sanitizer' ) && Prompt_Sanitizer::is_enabled() ) {
				$content = Prompt_Sanitizer::sanitize( $content );
				$title   = Prompt_Sanitizer::sanitize_string( $post->post_title );
			} else {
				$title = $post->post_title;
			}
			$posts[] = array(
				'id'              => $post->ID,
				'title'           => $title,
				'excerpt'         => wp_trim_words( $content, max( 1, $excerpt_words ) ),
				'content_snippet' => wp_trim_words( $content, max( 1, $snippet_words ) ),
				'url'             => get_permalink( $post->ID ),
				'date'            => $post->post_date,
			);
		}

		return array(
			'query' => $query,
			'posts' => $posts,
			'total' => $q->found_posts,
		);
	}

	/**
	 * Analytics narratives: turn site digest or recent activity into a brief narrative.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with narrative; or error key.
	 */
	public static function execute_analytics_narratives( array $input ): array {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return array( 'error' => __( 'WP Pinch gateway is not configured.', 'wp-pinch' ) );
		}
		if ( Plugin::is_read_only_mode() ) {
			return array( 'error' => __( 'API is in read-only mode.', 'wp-pinch' ) );
		}
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			return array( 'error' => __( 'The AI gateway is temporarily unavailable.', 'wp-pinch' ) );
		}

		$source = sanitize_key( (string) ( $input['source'] ?? 'site_digest' ) );
		if ( 'recent_activity' === $source ) {
			$data = self::execute_recent_activity( array( 'limit' => 15 ) );
		} else {
			$data = self::execute_site_digest(
				array(
					'per_page'  => 15,
					'post_type' => 'post',
				)
			);
		}
		if ( isset( $data['error'] ) ) {
			return array( 'error' => $data['error'] );
		}

		$prompt = 'Turn the following site data into a brief narrative (2–4 paragraphs): what happened recently, what is new, what matters. Be concise and editorial.' . "\n\n" .
			'SITE DATA (JSON):' . "\n" . wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$payload  = array(
			'message'    => $prompt,
			'name'       => 'WordPress Analytics Narratives',
			'sessionKey' => 'wp-pinch-analytics-narratives',
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
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );
		$narrative = $data['response'] ?? $data['message'] ?? '';
		$narrative = is_string( $narrative ) ? trim( $narrative ) : '';

		return array(
			'narrative' => $narrative,
			'source'    => $source,
		);
	}

	/**
	 * Submit conversational form data; optionally POST to webhook.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with sent, summary; or error key.
	 */
	public static function execute_submit_conversational_form( array $input ): array {
		$fields = $input['fields'] ?? array();
		if ( ! is_array( $fields ) ) {
			return array( 'error' => __( 'fields must be an array of { name, value } objects.', 'wp-pinch' ) );
		}

		$payload = array();
		$summary = array();
		foreach ( $fields as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['name'] ) ) {
				continue;
			}
			$name  = sanitize_text_field( (string) $item['name'] );
			$value = isset( $item['value'] ) ? sanitize_text_field( (string) $item['value'] ) : '';
			if ( '' !== $name ) {
				$payload[ $name ] = $value;
				$summary[]        = $name . ': ' . ( '' !== $value ? $value : __( '(empty)', 'wp-pinch' ) );
			}
		}

		if ( empty( $payload ) ) {
			return array(
				'error' => __( 'No valid fields provided.', 'wp-pinch' ),
				'sent'  => false,
			);
		}

		$webhook_url = isset( $input['webhook_url'] ) ? esc_url_raw( (string) $input['webhook_url'] ) : '';
		$sent        = false;

		if ( '' !== $webhook_url && wp_http_validate_url( $webhook_url ) ) {
			$response = wp_safe_remote_post(
				$webhook_url,
				array(
					'timeout' => 15,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( $payload ),
				)
			);
			$sent     = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 200 && wp_remote_retrieve_response_code( $response ) < 300;
		}

		return array(
			'sent'    => $sent,
			'summary' => $summary,
			'fields'  => array_keys( $payload ),
		);
	}
}
