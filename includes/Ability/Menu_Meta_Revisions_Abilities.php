<?php
/**
 * Menu, post meta, revisions, bulk edit, and cron management abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, meta, revisions, bulk edit, and cron abilities.
 */
class Menu_Meta_Revisions_Abilities {
	use Menu_Meta_Revisions_Execute_Trait;

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
}
