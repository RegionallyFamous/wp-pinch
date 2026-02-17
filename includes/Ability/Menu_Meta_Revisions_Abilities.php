<?php
/**
 * Menu, post meta, revisions, bulk edit, and cron management abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, meta, revisions, bulk edit, and cron abilities.
 */
class Menu_Meta_Revisions_Abilities {

	/**
	 * Meta key prefixes that are protected/internal and should not be exposed.
	 *
	 * @var string[]
	 */
	const PROTECTED_META_PREFIXES = array(
		'_edit_',
		'_wp_old_',
		'_encloseme',
		'_pingme',
	);

	/**
	 * Register menu, post meta, revision, bulk edit, and cron abilities.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Abilities::register_ability(
			'wp-pinch/list-menus',
			__( 'List Navigation Menus', 'wp-pinch' ),
			__( 'List all registered navigation menus and their items.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'menu' => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Menu slug, name, or ID. Leave empty to list all menus.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_theme_options',
			array( __CLASS__, 'execute_list_menus' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/manage-menu-item',
			__( 'Manage Menu Item', 'wp-pinch' ),
			__( 'Create, update, or delete a navigation menu item.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'action', 'menu' ),
				'properties' => array(
					'action'    => array(
						'type' => 'string',
						'enum' => array( 'create', 'update', 'delete' ),
					),
					'menu'      => array(
						'type'        => 'string',
						'description' => 'Menu slug, name, or ID.',
					),
					'item_id'   => array(
						'type'        => 'integer',
						'description' => 'Menu item ID (required for update/delete).',
					),
					'title'     => array(
						'type'        => 'string',
						'description' => 'Menu item title.',
					),
					'url'       => array(
						'type'        => 'string',
						'description' => 'Menu item URL (for custom links).',
					),
					'object'    => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Object type slug (page, post, category, etc.).',
					),
					'object_id' => array(
						'type'        => 'integer',
						'default'     => 0,
						'description' => 'Object ID (post ID, term ID, etc.).',
					),
					'parent'    => array(
						'type'        => 'integer',
						'default'     => 0,
						'description' => 'Parent menu item ID.',
					),
					'position'  => array(
						'type'        => 'integer',
						'default'     => 0,
						'description' => 'Menu order position.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_theme_options',
			array( __CLASS__, 'execute_manage_menu_item' )
		);

		Abilities::register_ability(
			'wp-pinch/get-post-meta',
			__( 'Get Post Meta', 'wp-pinch' ),
			__( 'Retrieve custom field values for a post.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
					'key'     => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Specific meta key. Leave empty to get all public meta.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_get_post_meta' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/update-post-meta',
			__( 'Update Post Meta', 'wp-pinch' ),
			__( 'Set or delete a custom field on a post.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id', 'key' ),
				'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
					'key'     => array( 'type' => 'string' ),
					'value'   => array( 'description' => 'Value to set. Omit or set null to delete the meta key.' ),
					'delete'  => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Set true to delete the meta key.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_update_post_meta' )
		);

		Abilities::register_ability(
			'wp-pinch/list-revisions',
			__( 'List Revisions', 'wp-pinch' ),
			__( 'List all revisions of a post.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_list_revisions' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/restore-revision',
			__( 'Restore Revision', 'wp-pinch' ),
			__( 'Restore a post to a specific revision.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'revision_id' ),
				'properties' => array(
					'revision_id' => array( 'type' => 'integer' ),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_restore_revision' )
		);

		Abilities::register_ability(
			'wp-pinch/bulk-edit-posts',
			__( 'Bulk Edit Posts', 'wp-pinch' ),
			__( 'Batch update status, category, or delete multiple posts at once.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_ids', 'action' ),
				'properties' => array(
					'post_ids'    => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Array of post IDs to operate on (max 50).',
					),
					'action'      => array(
						'type' => 'string',
						'enum' => array( 'update_status', 'add_category', 'remove_category', 'trash', 'delete' ),
					),
					'status'      => array(
						'type'        => 'string',
						'description' => 'New status (for update_status action).',
					),
					'category_id' => array(
						'type'        => 'integer',
						'description' => 'Category term ID (for add/remove_category).',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_others_posts',
			array( __CLASS__, 'execute_bulk_edit_posts' )
		);

		Abilities::register_ability(
			'wp-pinch/list-cron-events',
			__( 'List Cron Events', 'wp-pinch' ),
			__( 'List all scheduled WordPress cron events.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'hook' => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Filter by hook name (partial match).',
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_list_cron_events' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/manage-cron',
			__( 'Manage Cron Event', 'wp-pinch' ),
			__( 'Run or delete a scheduled cron event.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'action', 'hook' ),
				'properties' => array(
					'action'    => array(
						'type' => 'string',
						'enum' => array( 'run', 'delete' ),
					),
					'hook'      => array(
						'type'        => 'string',
						'description' => 'The cron hook name.',
					),
					'timestamp' => array(
						'type'        => 'integer',
						'default'     => 0,
						'description' => 'Specific event timestamp (for delete). 0 = all events for hook.',
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_manage_cron' )
		);
	}

	/**
	 * Format a navigation menu item for API output.
	 * wp_get_nav_menu_items() returns WP_Post instances with dynamic properties (title, url, type, etc.).
	 *
	 * @param \WP_Post $item Menu item post object.
	 * @return array<string, mixed>
	 */
	private static function format_menu_item( \WP_Post $item ): array {
		$classes = isset( $item->classes ) && is_array( $item->classes ) ? $item->classes : array();
		return array(
			'id'        => (int) $item->ID,
			'title'     => isset( $item->title ) ? (string) $item->title : '',
			'url'       => isset( $item->url ) ? (string) $item->url : '',
			'type'      => isset( $item->type ) ? (string) $item->type : '',
			'object'    => isset( $item->object ) ? (string) $item->object : '',
			'object_id' => isset( $item->object_id ) ? (int) $item->object_id : 0,
			'parent'    => isset( $item->menu_item_parent ) ? (int) $item->menu_item_parent : 0,
			'position'  => (int) $item->menu_order,
			'target'    => isset( $item->target ) ? (string) $item->target : '',
			'classes'   => array_filter( $classes ),
		);
	}

	/**
	 * List navigation menus.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_menus( array $input ): array {
		$menu_identifier = sanitize_text_field( $input['menu'] ?? '' );

		if ( '' !== $menu_identifier ) {
			$menu_items = wp_get_nav_menu_items( $menu_identifier );
			if ( false === $menu_items ) {
				return array( 'error' => __( 'Menu not found.', 'wp-pinch' ) );
			}
			$menu_obj = wp_get_nav_menu_object( $menu_identifier );
			return array(
				'menu'  => array(
					'id'    => $menu_obj->term_id,
					'name'  => $menu_obj->name,
					'slug'  => $menu_obj->slug,
					'count' => $menu_obj->count,
				),
				'items' => array_map( array( __CLASS__, 'format_menu_item' ), $menu_items ),
			);
		}

		$menus     = wp_get_nav_menus();
		$locations = get_nav_menu_locations();
		$result    = array();
		foreach ( $menus as $menu ) {
			$assigned = array();
			foreach ( $locations as $location => $menu_id ) {
				if ( $menu_id === $menu->term_id ) {
					$assigned[] = $location;
				}
			}
			$result[] = array(
				'id'        => $menu->term_id,
				'name'      => $menu->name,
				'slug'      => $menu->slug,
				'count'     => $menu->count,
				'locations' => $assigned,
			);
		}
		$registered_locations = get_registered_nav_menus();
		return array(
			'menus'     => $result,
			'total'     => count( $result ),
			'locations' => $registered_locations,
		);
	}

	/**
	 * Manage a menu item (create, update, delete).
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_manage_menu_item( array $input ): array {
		$action = sanitize_key( $input['action'] );
		$menu   = wp_get_nav_menu_object( sanitize_text_field( $input['menu'] ) );
		if ( ! $menu ) {
			return array( 'error' => __( 'Menu not found.', 'wp-pinch' ) );
		}
		switch ( $action ) {
			case 'create':
				$item_data = array(
					'menu-item-title'     => sanitize_text_field( $input['title'] ?? '' ),
					'menu-item-url'       => esc_url_raw( $input['url'] ?? '' ),
					'menu-item-status'    => 'publish',
					'menu-item-position'  => absint( $input['position'] ?? 0 ),
					'menu-item-parent-id' => absint( $input['parent'] ?? 0 ),
				);
				if ( ! empty( $input['object'] ) && ! empty( $input['object_id'] ) ) {
					$object                           = sanitize_key( $input['object'] );
					$object_id                        = absint( $input['object_id'] );
					$type                             = taxonomy_exists( $object ) ? 'taxonomy' : 'post_type';
					$item_data['menu-item-type']      = $type;
					$item_data['menu-item-object']    = $object;
					$item_data['menu-item-object-id'] = $object_id;
				} else {
					$item_data['menu-item-type'] = 'custom';
				}
				$item_id = wp_update_nav_menu_item( $menu->term_id, 0, $item_data );
				if ( is_wp_error( $item_id ) ) {
					return array( 'error' => $item_id->get_error_message() );
				}
				Audit_Table::insert(
					'menu_item_created',
					'ability',
					sprintf( 'Menu item #%d created in menu "%s".', $item_id, $menu->name ),
					array(
						'menu_id' => $menu->term_id,
						'item_id' => $item_id,
					)
				);
				return array(
					'item_id' => $item_id,
					'created' => true,
				);

			case 'update':
				$item_id = absint( $input['item_id'] ?? 0 );
				if ( ! $item_id || ! get_post( $item_id ) ) {
					return array( 'error' => __( 'Menu item not found.', 'wp-pinch' ) );
				}
				$item_data = array();
				if ( isset( $input['title'] ) ) {
					$item_data['menu-item-title'] = sanitize_text_field( $input['title'] );
				}
				if ( isset( $input['url'] ) ) {
					$item_data['menu-item-url'] = esc_url_raw( $input['url'] );
				}
				if ( isset( $input['position'] ) ) {
					$item_data['menu-item-position'] = absint( $input['position'] );
				}
				if ( isset( $input['parent'] ) ) {
					$item_data['menu-item-parent-id'] = absint( $input['parent'] );
				}
				$result = wp_update_nav_menu_item( $menu->term_id, $item_id, $item_data );
				if ( is_wp_error( $result ) ) {
					return array( 'error' => $result->get_error_message() );
				}
				Audit_Table::insert(
					'menu_item_updated',
					'ability',
					sprintf( 'Menu item #%d updated in menu "%s".', $item_id, $menu->name ),
					array(
						'menu_id' => $menu->term_id,
						'item_id' => $item_id,
					)
				);
				return array(
					'item_id' => $item_id,
					'updated' => true,
				);

			case 'delete':
				$item_id = absint( $input['item_id'] ?? 0 );
				if ( ! $item_id ) {
					return array( 'error' => __( 'Menu item ID required.', 'wp-pinch' ) );
				}
				$item_post = get_post( $item_id );
				if ( ! $item_post || 'nav_menu_item' !== $item_post->post_type ) {
					return array( 'error' => __( 'Menu item not found.', 'wp-pinch' ) );
				}
				$result = wp_delete_post( $item_id, true );
				if ( ! $result ) {
					return array( 'error' => __( 'Failed to delete menu item.', 'wp-pinch' ) );
				}
				Audit_Table::insert(
					'menu_item_deleted',
					'ability',
					sprintf( 'Menu item #%d deleted from menu "%s".', $item_id, $menu->name ),
					array(
						'menu_id' => $menu->term_id,
						'item_id' => $item_id,
					)
				);
				return array(
					'item_id' => $item_id,
					'deleted' => true,
				);

			default:
				return array( 'error' => __( 'Invalid action.', 'wp-pinch' ) );
		}
	}

	/**
	 * Get post meta values.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_get_post_meta( array $input ): array {
		$post_id = absint( $input['post_id'] );
		if ( ! get_post( $post_id ) ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to read meta for this post.', 'wp-pinch' ) );
		}
		$key = sanitize_text_field( $input['key'] ?? '' );
		if ( '' !== $key ) {
			if ( is_protected_meta( $key, 'post' ) ) {
				return array( 'error' => __( 'This meta key is protected.', 'wp-pinch' ) );
			}
			$value = get_post_meta( $post_id, $key, true );
			return array(
				'post_id' => $post_id,
				'key'     => $key,
				'value'   => $value,
			);
		}
		$all_meta = get_post_meta( $post_id );
		$filtered = array();
		foreach ( $all_meta as $meta_key => $values ) {
			if ( is_protected_meta( $meta_key, 'post' ) ) {
				continue;
			}
			$skip = false;
			foreach ( self::PROTECTED_META_PREFIXES as $prefix ) {
				if ( str_starts_with( $meta_key, $prefix ) ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}
			$filtered[ $meta_key ] = count( $values ) === 1 ? $values[0] : $values;
		}
		return array(
			'post_id' => $post_id,
			'meta'    => $filtered,
			'count'   => count( $filtered ),
		);
	}

	/**
	 * Update or delete post meta.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_update_post_meta( array $input ): array {
		$post_id = absint( $input['post_id'] );
		if ( ! get_post( $post_id ) ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to modify meta for this post.', 'wp-pinch' ) );
		}
		$key = sanitize_text_field( $input['key'] );
		if ( is_protected_meta( $key, 'post' ) ) {
			return array( 'error' => __( 'This meta key is protected and cannot be modified.', 'wp-pinch' ) );
		}
		if ( ! empty( $input['delete'] ) ) {
			$deleted = delete_post_meta( $post_id, $key );
			Audit_Table::insert(
				'post_meta_deleted',
				'ability',
				sprintf( 'Meta key "%s" deleted from post #%d.', $key, $post_id ),
				array(
					'post_id' => $post_id,
					'key'     => $key,
				)
			);
			return array(
				'post_id' => $post_id,
				'key'     => $key,
				'deleted' => $deleted,
			);
		}
		$value = $input['value'] ?? '';
		if ( is_string( $value ) ) {
			$value = sanitize_text_field( $value );
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				if ( ! is_scalar( $v ) ) {
					return array( 'error' => __( 'Nested arrays are not supported for post meta values.', 'wp-pinch' ) );
				}
			}
			$value = array_map( 'sanitize_text_field', $value );
		} elseif ( is_numeric( $value ) ) {
			$value = $value + 0;
		} elseif ( is_bool( $value ) ) {
			$value = $value ? '1' : '0';
		} else {
			return array( 'error' => __( 'Unsupported value type. Use string, number, boolean, or array of strings.', 'wp-pinch' ) );
		}
		update_post_meta( $post_id, $key, $value );
		Audit_Table::insert(
			'post_meta_updated',
			'ability',
			sprintf( 'Meta key "%s" updated on post #%d.', $key, $post_id ),
			array(
				'post_id' => $post_id,
				'key'     => $key,
			)
		);
		return array(
			'post_id' => $post_id,
			'key'     => $key,
			'updated' => true,
		);
	}

	/**
	 * List post revisions.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_revisions( array $input ): array {
		$post_id = absint( $input['post_id'] );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to view revisions for this post.', 'wp-pinch' ) );
		}
		if ( ! wp_revisions_enabled( $post ) ) {
			return array( 'error' => __( 'Revisions are not enabled for this post type.', 'wp-pinch' ) );
		}
		$revisions      = wp_get_post_revisions( $post_id, array( 'order' => 'DESC' ) );
		$rev_list       = array_values( $revisions );
		$rev_author_ids = array_unique(
			array_map(
				function ( $r ) {
					return (int) $r->post_author;
				},
				$rev_list
			)
		);
		$rev_author_map = array();
		if ( ! empty( $rev_author_ids ) ) {
			$rev_authors = get_users(
				array(
					'include' => $rev_author_ids,
					'fields'  => array( 'ID', 'display_name' ),
				)
			);
			foreach ( $rev_authors as $a ) {
				$rev_author_map[ (int) $a->ID ] = $a->display_name;
			}
		}
		return array(
			'post_id'   => $post_id,
			'revisions' => array_map(
				function ( $rev ) use ( $rev_author_map ) {
					return array(
						'id'      => $rev->ID,
						'author'  => $rev_author_map[ (int) $rev->post_author ] ?? __( 'Unknown', 'wp-pinch' ),
						'date'    => $rev->post_date,
						'title'   => $rev->post_title,
						'excerpt' => wp_trim_words( $rev->post_content, 30 ),
					);
				},
				$rev_list
			),
			'total'     => count( $revisions ),
		);
	}

	/**
	 * Restore a post to a specific revision.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_restore_revision( array $input ): array {
		$revision_id = absint( $input['revision_id'] );
		$revision    = wp_get_post_revision( $revision_id );
		if ( ! $revision ) {
			return array( 'error' => __( 'Revision not found.', 'wp-pinch' ) );
		}
		$parent_id = (int) $revision->post_parent;
		if ( ! current_user_can( 'edit_post', $parent_id ) ) {
			return array( 'error' => __( 'You do not have permission to restore revisions for this post.', 'wp-pinch' ) );
		}
		$post_id = wp_restore_post_revision( $revision_id );
		if ( ! $post_id ) {
			return array( 'error' => __( 'Failed to restore revision.', 'wp-pinch' ) );
		}
		Audit_Table::insert(
			'revision_restored',
			'ability',
			sprintf( 'Post #%d restored to revision #%d.', $post_id, $revision_id ),
			array(
				'post_id'     => $post_id,
				'revision_id' => $revision_id,
			)
		);
		return array(
			'post_id'     => $post_id,
			'revision_id' => $revision_id,
			'restored'    => true,
		);
	}

	/**
	 * Bulk edit posts.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_bulk_edit_posts( array $input ): array {
		$post_ids = array_values( array_filter( array_map( 'absint', $input['post_ids'] ?? array() ) ) );
		$action   = sanitize_key( $input['action'] );
		if ( count( $post_ids ) > 50 ) {
			return array( 'error' => __( 'Maximum 50 posts per bulk operation.', 'wp-pinch' ) );
		}
		if ( empty( $post_ids ) ) {
			return array( 'error' => __( 'No valid post IDs provided.', 'wp-pinch' ) );
		}
		$results  = array();
		$success  = 0;
		$failures = 0;
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				$results[] = array(
					'id'    => $post_id,
					'error' => __( 'Not found.', 'wp-pinch' ),
				);
				++$failures;
				continue;
			}
			switch ( $action ) {
				case 'update_status':
					$status = sanitize_key( $input['status'] ?? '' );
					if ( '' === $status ) {
						$results[] = array(
							'id'    => $post_id,
							'error' => __( 'Status required.', 'wp-pinch' ),
						);
						++$failures;
						break;
					}
					$result = wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => $status,
						),
						true
					);
					if ( is_wp_error( $result ) ) {
						$results[] = array(
							'id'    => $post_id,
							'error' => $result->get_error_message(),
						);
						++$failures;
					} else {
						$results[] = array(
							'id'     => $post_id,
							'status' => $status,
						);
						++$success;
					}
					break;
				case 'add_category':
					$cat_id = absint( $input['category_id'] ?? 0 );
					if ( ! $cat_id ) {
						$results[] = array(
							'id'    => $post_id,
							'error' => __( 'Category ID required.', 'wp-pinch' ),
						);
						++$failures;
						break;
					}
					$existing   = wp_get_post_categories( $post_id );
					$existing[] = $cat_id;
					wp_set_post_categories( $post_id, array_unique( $existing ) );
					$results[] = array(
						'id'             => $post_id,
						'category_added' => $cat_id,
					);
					++$success;
					break;
				case 'remove_category':
					$cat_id = absint( $input['category_id'] ?? 0 );
					if ( ! $cat_id ) {
						$results[] = array(
							'id'    => $post_id,
							'error' => __( 'Category ID required.', 'wp-pinch' ),
						);
						++$failures;
						break;
					}
					$existing = wp_get_post_categories( $post_id );
					$existing = array_diff( $existing, array( $cat_id ) );
					wp_set_post_categories( $post_id, array_values( $existing ) );
					$results[] = array(
						'id'               => $post_id,
						'category_removed' => $cat_id,
					);
					++$success;
					break;
				case 'trash':
				case 'delete':
					$result = wp_trash_post( $post_id );
					if ( ! $result ) {
						$results[] = array(
							'id'    => $post_id,
							'error' => __( 'Failed to delete.', 'wp-pinch' ),
						);
						++$failures;
					} else {
						$results[] = array(
							'id'      => $post_id,
							'trashed' => true,
						);
						++$success;
					}
					break;
				default:
					$results[] = array(
						'id'    => $post_id,
						'error' => __( 'Invalid action.', 'wp-pinch' ),
					);
					++$failures;
			}
		}
		Audit_Table::insert(
			'bulk_edit',
			'ability',
			sprintf( 'Bulk %s: %d succeeded, %d failed out of %d posts.', $action, $success, $failures, count( $post_ids ) ),
			array(
				'action'   => $action,
				'success'  => $success,
				'failures' => $failures,
			)
		);
		return array(
			'action'   => $action,
			'results'  => $results,
			'success'  => $success,
			'failures' => $failures,
			'total'    => count( $post_ids ),
		);
	}

	/**
	 * List scheduled cron events.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_cron_events( array $input ): array {
		$cron_array  = _get_cron_array();
		$hook_filter = sanitize_text_field( $input['hook'] ?? '' );
		$events      = array();
		if ( ! is_array( $cron_array ) ) {
			return array(
				'events' => array(),
				'total'  => 0,
			);
		}
		foreach ( $cron_array as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $schedules ) {
				if ( '' !== $hook_filter && false === stripos( $hook, $hook_filter ) ) {
					continue;
				}
				foreach ( $schedules as $sig => $data ) {
					$events[] = array(
						'hook'      => $hook,
						'timestamp' => $timestamp,
						'date'      => gmdate( 'Y-m-d H:i:s', $timestamp ),
						'schedule'  => $data['schedule'] ?? 'single',
						'interval'  => $data['interval'] ?? null,
						'args'      => $data['args'] ?? array(),
					);
				}
			}
		}
		usort(
			$events,
			function ( $a, $b ) {
				return $a['timestamp'] <=> $b['timestamp'];
			}
		);
		$total = count( $events );
		if ( $total > 200 ) {
			$events = array_slice( $events, 0, 200 );
		}
		return array(
			'events'    => $events,
			'total'     => $total,
			'truncated' => $total > 200,
		);
	}

	/**
	 * Run or delete a cron event.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_manage_cron( array $input ): array {
		$action    = sanitize_key( $input['action'] );
		$hook      = sanitize_text_field( $input['hook'] );
		$timestamp = absint( $input['timestamp'] ?? 0 );
		switch ( $action ) {
			case 'run':
				$cron_array   = _get_cron_array();
				$is_cron_hook = false;
				if ( is_array( $cron_array ) ) {
					foreach ( $cron_array as $ts => $hooks ) {
						if ( isset( $hooks[ $hook ] ) ) {
							$is_cron_hook = true;
							break;
						}
					}
				}
				if ( ! $is_cron_hook ) {
					return array( 'error' => __( 'Hook is not a scheduled cron event.', 'wp-pinch' ) );
				}
				if ( ! has_action( $hook ) ) {
					return array(
						'error' => sprintf(
							/* translators: %s: cron hook name */
							__( 'No callbacks registered for hook "%s".', 'wp-pinch' ),
							$hook
						),
					);
				}
				do_action( $hook );
				Audit_Table::insert(
					'cron_run',
					'ability',
					sprintf( 'Cron hook "%s" executed manually.', $hook ),
					array( 'hook' => $hook )
				);
				return array(
					'hook'     => $hook,
					'executed' => true,
				);

			case 'delete':
				if ( in_array( $hook, Abilities::PROTECTED_CRON_HOOKS, true ) ) {
					return array( 'error' => __( 'Cannot delete core WordPress cron events.', 'wp-pinch' ) );
				}
				if ( 0 === $timestamp ) {
					$cron    = _get_cron_array();
					$removed = 0;
					if ( is_array( $cron ) ) {
						foreach ( $cron as $ts => $hooks ) {
							if ( isset( $hooks[ $hook ] ) ) {
								foreach ( $hooks[ $hook ] as $sig => $data ) {
									wp_unschedule_event( $ts, $hook, $data['args'] );
									++$removed;
								}
							}
						}
					}
					Audit_Table::insert(
						'cron_deleted',
						'ability',
						sprintf( '%d cron event(s) for hook "%s" removed.', $removed, $hook ),
						array(
							'hook'    => $hook,
							'removed' => $removed,
						)
					);
					return array(
						'hook'    => $hook,
						'removed' => $removed,
					);
				}
				$cron = _get_cron_array();
				if ( ! isset( $cron[ $timestamp ][ $hook ] ) ) {
					return array( 'error' => __( 'Cron event not found at that timestamp.', 'wp-pinch' ) );
				}
				foreach ( $cron[ $timestamp ][ $hook ] as $sig => $data ) {
					wp_unschedule_event( $timestamp, $hook, $data['args'] );
				}
				Audit_Table::insert(
					'cron_deleted',
					'ability',
					sprintf( 'Cron event for hook "%s" at %d removed.', $hook, $timestamp ),
					array(
						'hook'      => $hook,
						'timestamp' => $timestamp,
					)
				);
				return array(
					'hook'      => $hook,
					'timestamp' => $timestamp,
					'removed'   => true,
				);

			default:
				return array( 'error' => __( 'Invalid action.', 'wp-pinch' ) );
		}
	}
}
