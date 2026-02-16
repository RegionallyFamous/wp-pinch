<?php
/**
 * Content abilities: posts and taxonomies.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

/**
 * List and manage posts and taxonomy terms.
 */
class Content_Abilities {

	/**
	 * Register content abilities with the main Abilities registry.
	 */
	public static function register(): void {
		Abilities::register_ability(
			'wp-pinch/list-posts',
			__( 'List Posts', 'wp-pinch' ),
			__( 'List WordPress posts with optional filters.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'status'    => array(
						'type'    => 'string',
						'default' => 'publish',
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'search'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'category'  => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_list_posts' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/get-post',
			__( 'Get Post', 'wp-pinch' ),
			__( 'Retrieve a single post by ID.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id' => array( 'type' => 'integer' ),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_get_post' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/create-post',
			__( 'Create Post', 'wp-pinch' ),
			__( 'Create a new post, page, or custom post type.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'title' ),
				'properties' => array(
					'title'                 => array( 'type' => 'string' ),
					'content'               => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Post body. Accepts HTML or Gutenberg block markup (e.g. <!-- wp:paragraph -->). Use block markup for native editor compatibility. Ignored when blocks is provided.',
					),
					'blocks'                => array(
						'type'        => 'array',
						'description' => 'Optional. Array of block objects (blockName, attrs, innerContent, innerBlocks) to set as post content. Takes precedence over content when provided.',
					),
					'status'                => array(
						'type'    => 'string',
						'default' => 'draft',
					),
					'post_type'             => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'categories'            => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
					'tags'                  => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
					'excerpt'               => array(
						'type'    => 'string',
						'default' => '',
					),
					'featured_image_url'    => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'URL of image to set as featured image. Use this or featured_image_base64, not both.',
					),
					'featured_image_base64' => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Base64-encoded image data (or data URL) to set as featured image.',
					),
					'featured_image_alt'    => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Alt text for the featured image.',
					),
				),
			),
			array( 'type' => 'object' ),
			'publish_posts',
			array( __CLASS__, 'execute_create_post' )
		);

		Abilities::register_ability(
			'wp-pinch/update-post',
			__( 'Update Post', 'wp-pinch' ),
			__( 'Update an existing post by ID. Pass post_modified from get-post for optimistic locking; updates are rejected if the post changed since then.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id'            => array( 'type' => 'integer' ),
					'title'         => array( 'type' => 'string' ),
					'content'       => array( 'type' => 'string' ),
					'blocks'        => array(
						'type'        => 'array',
						'description' => 'Optional. Array of block objects (blockName, attrs, innerContent, innerBlocks) to set as post content. Takes precedence over content when provided.',
					),
					'status'        => array( 'type' => 'string' ),
					'excerpt'       => array( 'type' => 'string' ),
					'post_modified' => array(
						'type'        => 'string',
						'description' => 'Value from get-post modified field for optimistic locking. If provided and the post has changed since, the update is rejected.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_update_post' )
		);

		Abilities::register_ability(
			'wp-pinch/delete-post',
			__( 'Delete Post', 'wp-pinch' ),
			__( 'Trash or permanently delete a post.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id'    => array( 'type' => 'integer' ),
					'force' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'delete_posts',
			array( __CLASS__, 'execute_delete_post' )
		);

		Abilities::register_ability(
			'wp-pinch/list-taxonomies',
			__( 'List Taxonomies', 'wp-pinch' ),
			__( 'List registered taxonomies and their terms.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type'    => 'string',
						'default' => 'category',
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_list_taxonomies' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/manage-terms',
			__( 'Manage Terms', 'wp-pinch' ),
			__( 'Create, update, or delete taxonomy terms.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'action', 'taxonomy' ),
				'properties' => array(
					'action'   => array(
						'type' => 'string',
						'enum' => array( 'create', 'update', 'delete' ),
					),
					'taxonomy' => array( 'type' => 'string' ),
					'term_id'  => array( 'type' => 'integer' ),
					'name'     => array( 'type' => 'string' ),
					'slug'     => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'manage_categories',
			array( __CLASS__, 'execute_manage_terms' )
		);
	}

	/**
	 * List posts.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with posts, total, total_pages, page; or error key.
	 */
	public static function execute_list_posts( array $input ): array {
		$post_type = sanitize_key( $input['post_type'] ?? 'post' );

		if ( 'post' !== $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );
			if ( ! $post_type_obj ) {
				return array( 'error' => __( 'Invalid post type.', 'wp-pinch' ) );
			}
			if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
				return array( 'error' => __( 'Insufficient permissions for this post type.', 'wp-pinch' ) );
			}
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => sanitize_key( $input['status'] ?? 'publish' ),
			'posts_per_page' => max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) ),
			'paged'          => max( 1, absint( $input['page'] ?? 1 ) ),
		);

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['category'] ) ) {
			$args['category_name'] = sanitize_text_field( $input['category'] );
		}

		$query = new \WP_Query( $args );
		$posts = array();

		foreach ( $query->posts as $post ) {
			$posts[] = Abilities::format_post( $post );
		}

		return array(
			'posts'       => $posts,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'page'        => $args['paged'],
		);
	}

	/**
	 * Get a single post.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Post data or error key.
	 */
	public static function execute_get_post( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}
		return Abilities::format_post( $post, true );
	}

	/**
	 * Create a post.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with id, url, preview_url; or error key.
	 */
	public static function execute_create_post( array $input ): array {
		$post_type = sanitize_key( $input['post_type'] ?? 'post' );

		if ( ! post_type_exists( $post_type ) ) {
			return array(
				'error' => sprintf(
					/* translators: %s: post type slug */
					__( 'Post type "%s" does not exist.', 'wp-pinch' ),
					$post_type
				),
			);
		}

		$post_content = isset( $input['blocks'] ) && is_array( $input['blocks'] ) && ! empty( $input['blocks'] )
			? self::blocks_to_content( $input['blocks'] )
			: null;
		if ( is_wp_error( $post_content ) ) {
			return array( 'error' => $post_content->get_error_message() );
		}

		$post_data = array(
			'post_title'   => sanitize_text_field( $input['title'] ),
			'post_content' => null !== $post_content ? $post_content : wp_kses_post( $input['content'] ?? '' ),
			'post_status'  => sanitize_key( $input['status'] ?? 'draft' ),
			'post_type'    => $post_type,
			'post_excerpt' => sanitize_text_field( $input['excerpt'] ?? '' ),
		);

		if ( ! empty( $input['categories'] ) ) {
			$post_data['post_category'] = array_map( 'absint', $input['categories'] );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return array( 'error' => $post_id->get_error_message() );
		}

		update_post_meta( $post_id, '_wp_pinch_ai_generated', time() );

		if ( ! empty( $input['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $input['tags'] ) );
		}

		$featured_url           = isset( $input['featured_image_url'] ) && is_string( $input['featured_image_url'] ) ? trim( $input['featured_image_url'] ) : '';
		$featured_b64           = isset( $input['featured_image_base64'] ) && is_string( $input['featured_image_base64'] ) ? trim( $input['featured_image_base64'] ) : '';
		$featured_alt           = isset( $input['featured_image_alt'] ) && is_string( $input['featured_image_alt'] ) ? sanitize_text_field( $input['featured_image_alt'] ) : '';
		$featured_attachment_id = 0;
		if ( '' !== $featured_url || '' !== $featured_b64 ) {
			$attachment_id = Media_Abilities::create_attachment_from_url_or_base64( $featured_url, $featured_b64, $featured_alt, $post_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
				$featured_attachment_id = (int) $attachment_id;
			}
		}

		Audit_Table::insert( 'post_created', 'ability', sprintf( 'Post #%d created via ability.', $post_id ), array( 'post_id' => $post_id ) );

		$post_obj    = get_post( $post_id );
		$preview     = $post_obj ? get_preview_post_link( $post_obj ) : false;
		$preview_url = ( is_string( $preview ) && '' !== $preview ) ? $preview : get_permalink( $post_id );

		$result = array(
			'id'           => $post_id,
			'url'          => get_permalink( $post_id ),
			'preview_url'  => $preview_url,
			'ai_generated' => true,
		);
		if ( $featured_attachment_id > 0 ) {
			$result['featured_image_id']  = $featured_attachment_id;
			$result['featured_image_url'] = wp_get_attachment_image_url( $featured_attachment_id, 'full' );
		}
		return $result;
	}

	/**
	 * Update a post.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with id, updated, preview_url, url; or error key.
	 */
	public static function execute_update_post( array $input ): array {
		$post_id = absint( $input['id'] );

		$before = get_post( $post_id );
		if ( ! $before ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to edit this post.', 'wp-pinch' ) );
		}

		$expected_modified = isset( $input['post_modified'] ) && is_string( $input['post_modified'] )
			? trim( $input['post_modified'] )
			: '';

		if ( '' !== $expected_modified ) {
			$current_modified = $before->post_modified;
			if ( $current_modified !== $expected_modified ) {
				return array(
					'error'          => __( 'Post was modified by someone else. Refetch with get-post and retry.', 'wp-pinch' ),
					'conflict'       => true,
					'post_modified'  => $current_modified,
					'current_title'  => $before->post_title,
					'current_status' => $before->post_status,
				);
			}
		}

		$post_data = array( 'ID' => $post_id );

		if ( isset( $input['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['blocks'] ) && is_array( $input['blocks'] ) && ! empty( $input['blocks'] ) ) {
			$from_blocks = self::blocks_to_content( $input['blocks'] );
			if ( is_wp_error( $from_blocks ) ) {
				return array( 'error' => $from_blocks->get_error_message() );
			}
			$post_data['post_content'] = $from_blocks;
		} elseif ( isset( $input['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $input['content'] );
		}
		if ( isset( $input['status'] ) ) {
			$post_data['post_status'] = sanitize_key( $input['status'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		update_post_meta( $post_id, '_wp_pinch_ai_generated', time() );

		$context = array( 'post_id' => $post_id );
		if ( isset( $post_data['post_title'] ) || isset( $post_data['post_content'] ) ) {
			$context['diff'] = array(
				'title_length_before'   => mb_strlen( $before->post_title ),
				'title_length_after'    => isset( $post_data['post_title'] ) ? mb_strlen( $post_data['post_title'] ) : null,
				'content_length_before' => mb_strlen( $before->post_content ),
				'content_length_after'  => isset( $post_data['post_content'] ) ? mb_strlen( $post_data['post_content'] ) : null,
			);
		}
		Audit_Table::insert( 'post_updated', 'ability', sprintf( 'Post #%d updated via ability.', $post_id ), $context );

		$post_obj    = get_post( $post_id );
		$preview     = $post_obj ? get_preview_post_link( $post_obj ) : false;
		$preview_url = ( is_string( $preview ) && '' !== $preview ) ? $preview : get_permalink( $post_id );

		return array(
			'id'          => $post_id,
			'updated'     => true,
			'preview_url' => $preview_url,
			'url'         => get_permalink( $post_id ),
		);
	}

	/**
	 * Delete a post.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with id, deleted, force; or error key.
	 */
	public static function execute_delete_post( array $input ): array {
		$post_id = absint( $input['id'] );

		if ( ! get_post( $post_id ) ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to delete this post.', 'wp-pinch' ) );
		}

		$force  = ! empty( $input['force'] );
		$result = wp_delete_post( $post_id, $force );

		if ( ! $result ) {
			return array( 'error' => __( 'Failed to delete post.', 'wp-pinch' ) );
		}

		Audit_Table::insert( 'post_deleted', 'ability', sprintf( 'Post #%d %s via ability.', $post_id, $force ? 'permanently deleted' : 'trashed' ), array( 'post_id' => $post_id ) );

		return array(
			'id'      => $post_id,
			'deleted' => true,
			'force'   => $force,
		);
	}

	/**
	 * List taxonomies and their terms.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with taxonomy and terms; or error key.
	 */
	public static function execute_list_taxonomies( array $input ): array {
		$taxonomy = sanitize_key( $input['taxonomy'] ?? 'category' );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array( 'error' => __( 'Taxonomy not found.', 'wp-pinch' ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'number'     => min( absint( $input['per_page'] ?? 50 ), 200 ),
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array( 'error' => $terms->get_error_message() );
		}

		return array(
			'taxonomy' => $taxonomy,
			'terms'    => array_map(
				function ( $term ) {
					return array(
						'id'    => $term->term_id,
						'name'  => $term->name,
						'slug'  => $term->slug,
						'count' => $term->count,
					);
				},
				$terms
			),
		);
	}

	/**
	 * Manage taxonomy terms.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with term_id, created, updated, or deleted; or error key.
	 */
	public static function execute_manage_terms( array $input ): array {
		$action   = sanitize_key( $input['action'] );
		$taxonomy = sanitize_key( $input['taxonomy'] );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array( 'error' => __( 'Taxonomy not found.', 'wp-pinch' ) );
		}

		switch ( $action ) {
			case 'create':
				$name = sanitize_text_field( $input['name'] ?? '' );
				if ( '' === $name ) {
					return array( 'error' => __( 'Term name is required.', 'wp-pinch' ) );
				}
				$result = wp_insert_term(
					$name,
					$taxonomy,
					array(
						'slug' => sanitize_title( $input['slug'] ?? '' ),
					)
				);
				if ( is_wp_error( $result ) ) {
					return array( 'error' => $result->get_error_message() );
				}
				return array(
					'term_id' => $result['term_id'],
					'created' => true,
				);

			case 'update':
				$term_id = absint( $input['term_id'] ?? 0 );
				if ( ! term_exists( $term_id, $taxonomy ) ) {
					return array( 'error' => __( 'Term not found.', 'wp-pinch' ) );
				}
				$result = wp_update_term(
					$term_id,
					$taxonomy,
					array(
						'name' => sanitize_text_field( $input['name'] ?? '' ),
						'slug' => sanitize_title( $input['slug'] ?? '' ),
					)
				);
				if ( is_wp_error( $result ) ) {
					return array( 'error' => $result->get_error_message() );
				}
				return array(
					'term_id' => $result['term_id'],
					'updated' => true,
				);

			case 'delete':
				$delete_term_id = absint( $input['term_id'] ?? 0 );
				if ( ! term_exists( $delete_term_id, $taxonomy ) ) {
					return array( 'error' => __( 'Term not found.', 'wp-pinch' ) );
				}
				$result = wp_delete_term( $delete_term_id, $taxonomy );
				if ( is_wp_error( $result ) ) {
					return array( 'error' => $result->get_error_message() );
				}
				return array( 'deleted' => (bool) $result );

			default:
				return array( 'error' => __( 'Invalid action.', 'wp-pinch' ) );
		}
	}

	/**
	 * Convert an array of block structures to post_content (serialized block markup).
	 *
	 * @param array<int, array<string, mixed>> $blocks Raw blocks from API.
	 * @return string|\WP_Error Serialized content or error.
	 */
	private static function blocks_to_content( array $blocks ) {
		if ( ! function_exists( 'serialize_blocks' ) ) {
			return new \WP_Error( 'blocks_unavailable', __( 'Block editor (serialize_blocks) is not available.', 'wp-pinch' ) );
		}
		$normalized = self::normalize_blocks_for_serialize( array_slice( $blocks, 0, 500 ) );
		if ( empty( $normalized ) ) {
			return new \WP_Error( 'invalid_blocks', __( 'No valid blocks provided. Each block needs blockName (e.g. core/paragraph) and optionally attrs, innerContent, innerBlocks.', 'wp-pinch' ) );
		}
		return serialize_blocks( $normalized );
	}

	/**
	 * Normalize raw block arrays to the shape expected by serialize_blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks Raw blocks.
	 * @return array<int, array{blockName: string, attrs: array, innerHTML: string, innerContent: array, innerBlocks: array}> Normalized blocks.
	 */
	private static function normalize_blocks_for_serialize( array $blocks ): array {
		$out = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';
			if ( '' === $name || ! preg_match( '/^[a-z0-9_-]+\/[a-z0-9_-]+$/', $name ) ) {
				continue;
			}
			$attrs         = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$inner_content = isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ? $block['innerContent'] : array();
			if ( empty( $inner_content ) && isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
				$inner_content = array( $block['innerHTML'] );
			}
			$inner_blocks = array();
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$inner_blocks = self::normalize_blocks_for_serialize( $block['innerBlocks'] );
			}
			$inner_html = implode( '', array_filter( $inner_content, 'is_string' ) );
			$out[]      = array(
				'blockName'    => $name,
				'attrs'        => $attrs,
				'innerHTML'    => $inner_html,
				'innerContent' => $inner_content,
				'innerBlocks'  => $inner_blocks,
			);
		}
		return $out;
	}
}
