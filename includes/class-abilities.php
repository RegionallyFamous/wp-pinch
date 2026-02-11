<?php
/**
 * WordPress Abilities registration — 12 categories, 34 core abilities (+2 WooCommerce).
 *
 * Every ability:
 * - Uses wp_register_ability() with typed JSON schema input/output.
 * - Sets meta.mcp.public = true for default MCP Adapter exposure.
 * - Wraps execute output in apply_filters( 'wp_pinch_ability_result', ... ).
 * - Uses transient caching (5 min) for read-only abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Register all WP Pinch abilities.
 */
class Abilities {

	/**
	 * Transient cache TTL in seconds.
	 */
	const CACHE_TTL = 300; // 5 minutes.

	/**
	 * Allowed option keys for the update-option ability.
	 *
	 * @var string[]
	 */
	const OPTION_ALLOWLIST = array(
		'blogname',
		'blogdescription',
		'timezone_string',
		'date_format',
		'time_format',
		'posts_per_page',
		'default_comment_status',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
	);

	/**
	 * Options that must NEVER be read or written via abilities, regardless of filters.
	 *
	 * @var string[]
	 */
	const OPTION_DENYLIST = array(
		'auth_key',
		'auth_salt',
		'logged_in_key',
		'logged_in_salt',
		'nonce_key',
		'nonce_salt',
		'secure_auth_key',
		'secure_auth_salt',
		'active_plugins',
		'users_can_register',
		'default_role',
		'wp_pinch_api_token',
	);

	/**
	 * Core WordPress cron hooks that must not be deleted.
	 *
	 * @var string[]
	 */
	const PROTECTED_CRON_HOOKS = array(
		'wp_update_plugins',
		'wp_update_themes',
		'wp_version_check',
		'wp_scheduled_delete',
		'wp_scheduled_auto_draft_delete',
		'wp_site_health_scheduled_check',
		'recovery_mode_clean_expired_keys',
	);

	/**
	 * Capabilities that indicate a role is administrative / privileged.
	 *
	 * @var string[]
	 */
	const DANGEROUS_CAPABILITIES = array(
		'manage_options',
		'edit_users',
		'activate_plugins',
		'delete_users',
		'create_users',
		'unfiltered_html',
		'update_core',
	);

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_abilities' ), 20 );

		/**
		 * Fires when WP Pinch abilities are ready to be extended.
		 *
		 * Third-party plugins can use this to inject additional abilities:
		 *
		 *     add_action( 'wp_pinch_register_abilities', function() {
		 *         wp_register_ability( 'myplugin/my-ability', [ ... ] );
		 *     });
		 *
		 * @since 1.0.0
		 */
		add_action(
			'init',
			function () {
				do_action( 'wp_pinch_register_abilities' );
			},
			25
		);
	}

	/**
	 * Get the list of ability names registered by this plugin.
	 *
	 * @return string[]
	 */
	public static function get_ability_names(): array {
		$abilities = array(
			// Content.
			'wp-pinch/list-posts',
			'wp-pinch/get-post',
			'wp-pinch/create-post',
			'wp-pinch/update-post',
			'wp-pinch/delete-post',
			'wp-pinch/list-taxonomies',
			'wp-pinch/manage-terms',

			// Media.
			'wp-pinch/list-media',
			'wp-pinch/upload-media',
			'wp-pinch/delete-media',

			// Users.
			'wp-pinch/list-users',
			'wp-pinch/get-user',
			'wp-pinch/update-user-role',

			// Comments.
			'wp-pinch/list-comments',
			'wp-pinch/moderate-comment',

			// Settings.
			'wp-pinch/get-option',
			'wp-pinch/update-option',

			// Plugins & Themes.
			'wp-pinch/list-plugins',
			'wp-pinch/toggle-plugin',
			'wp-pinch/list-themes',
			'wp-pinch/switch-theme',

			// Analytics & Maintenance.
			'wp-pinch/site-health',
			'wp-pinch/recent-activity',
			'wp-pinch/search-content',
			'wp-pinch/export-data',

			// Navigation Menus.
			'wp-pinch/list-menus',
			'wp-pinch/manage-menu-item',

			// Post Meta.
			'wp-pinch/get-post-meta',
			'wp-pinch/update-post-meta',

			// Revisions.
			'wp-pinch/list-revisions',
			'wp-pinch/restore-revision',

			// Bulk Operations.
			'wp-pinch/bulk-edit-posts',

			// Cron Management.
			'wp-pinch/list-cron-events',
			'wp-pinch/manage-cron',
		);

		// WooCommerce abilities — only registered when WooCommerce is active.
		if ( class_exists( 'WooCommerce' ) ) {
			$abilities[] = 'wp-pinch/woo-list-products';
			$abilities[] = 'wp-pinch/woo-manage-order';
		}

		return $abilities;
	}

	/**
	 * Register all abilities (36 with WooCommerce, 34 without).
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// -- Content abilities --------------------------------------------------

		self::register(
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

		self::register(
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

		self::register(
			'wp-pinch/create-post',
			__( 'Create Post', 'wp-pinch' ),
			__( 'Create a new post, page, or custom post type.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'title' ),
				'properties' => array(
					'title'      => array( 'type' => 'string' ),
					'content'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'status'     => array(
						'type'    => 'string',
						'default' => 'draft',
					),
					'post_type'  => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'categories' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
					'tags'       => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
					'excerpt'    => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'publish_posts',
			array( __CLASS__, 'execute_create_post' )
		);

		self::register(
			'wp-pinch/update-post',
			__( 'Update Post', 'wp-pinch' ),
			__( 'Update an existing post by ID.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id'      => array( 'type' => 'integer' ),
					'title'   => array( 'type' => 'string' ),
					'content' => array( 'type' => 'string' ),
					'status'  => array( 'type' => 'string' ),
					'excerpt' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_update_post' )
		);

		self::register(
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

		self::register(
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

		self::register(
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

		// -- Media abilities ----------------------------------------------------

		self::register(
			'wp-pinch/list-media',
			__( 'List Media', 'wp-pinch' ),
			__( 'List media library items.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'mime_type' => array(
						'type'    => 'string',
						'default' => '',
					),
					'search'    => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'upload_files',
			array( __CLASS__, 'execute_list_media' ),
			true
		);

		self::register(
			'wp-pinch/upload-media',
			__( 'Upload Media', 'wp-pinch' ),
			__( 'Upload media from a URL.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'url' ),
				'properties' => array(
					'url'   => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'title' => array(
						'type'    => 'string',
						'default' => '',
					),
					'alt'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'upload_files',
			array( __CLASS__, 'execute_upload_media' )
		);

		self::register(
			'wp-pinch/delete-media',
			__( 'Delete Media', 'wp-pinch' ),
			__( 'Delete a media attachment.', 'wp-pinch' ),
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
			array( __CLASS__, 'execute_delete_media' )
		);

		// -- User abilities -----------------------------------------------------

		self::register(
			'wp-pinch/list-users',
			__( 'List Users', 'wp-pinch' ),
			__( 'List site users with optional role filter.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'role'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			array( 'type' => 'object' ),
			'list_users',
			array( __CLASS__, 'execute_list_users' ),
			true
		);

		self::register(
			'wp-pinch/get-user',
			__( 'Get User', 'wp-pinch' ),
			__( 'Retrieve a single user by ID.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id' => array( 'type' => 'integer' ),
				),
			),
			array( 'type' => 'object' ),
			'list_users',
			array( __CLASS__, 'execute_get_user' ),
			true
		);

		self::register(
			'wp-pinch/update-user-role',
			__( 'Update User Role', 'wp-pinch' ),
			__( 'Change a user\'s role.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id', 'role' ),
				'properties' => array(
					'id'   => array( 'type' => 'integer' ),
					'role' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'promote_users',
			array( __CLASS__, 'execute_update_user_role' )
		);

		// -- Comment abilities --------------------------------------------------

		self::register(
			'wp-pinch/list-comments',
			__( 'List Comments', 'wp-pinch' ),
			__( 'List comments with optional status filter.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'status'   => array(
						'type'    => 'string',
						'default' => 'all',
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'post_id'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			),
			array( 'type' => 'object' ),
			'moderate_comments',
			array( __CLASS__, 'execute_list_comments' ),
			true
		);

		self::register(
			'wp-pinch/moderate-comment',
			__( 'Moderate Comment', 'wp-pinch' ),
			__( 'Approve, spam, or trash a comment.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id', 'status' ),
				'properties' => array(
					'id'     => array( 'type' => 'integer' ),
					'status' => array(
						'type' => 'string',
						'enum' => array( 'approve', 'hold', 'spam', 'trash' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'moderate_comments',
			array( __CLASS__, 'execute_moderate_comment' )
		);

		// -- Settings abilities -------------------------------------------------

		self::register(
			'wp-pinch/get-option',
			__( 'Get Option', 'wp-pinch' ),
			__( 'Read a WordPress option value.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'key' ),
				'properties' => array(
					'key' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_get_option' ),
			true
		);

		self::register(
			'wp-pinch/update-option',
			__( 'Update Option', 'wp-pinch' ),
			__( 'Update an allowed WordPress option.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'key', 'value' ),
				'properties' => array(
					'key'   => array( 'type' => 'string' ),
					'value' => array(),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_update_option' )
		);

		// -- Plugin & Theme abilities -------------------------------------------

		self::register(
			'wp-pinch/list-plugins',
			__( 'List Plugins', 'wp-pinch' ),
			__( 'List installed plugins and their status.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'status' => array(
						'type'    => 'string',
						'default' => 'all',
						'enum'    => array( 'all', 'active', 'inactive' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'activate_plugins',
			array( __CLASS__, 'execute_list_plugins' ),
			true
		);

		self::register(
			'wp-pinch/toggle-plugin',
			__( 'Toggle Plugin', 'wp-pinch' ),
			__( 'Activate or deactivate a plugin.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'plugin', 'activate' ),
				'properties' => array(
					'plugin'   => array( 'type' => 'string' ),
					'activate' => array( 'type' => 'boolean' ),
				),
			),
			array( 'type' => 'object' ),
			'activate_plugins',
			array( __CLASS__, 'execute_toggle_plugin' )
		);

		self::register(
			'wp-pinch/list-themes',
			__( 'List Themes', 'wp-pinch' ),
			__( 'List installed themes.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => new \stdClass(),
			),
			array( 'type' => 'object' ),
			'switch_themes',
			array( __CLASS__, 'execute_list_themes' ),
			true
		);

		self::register(
			'wp-pinch/switch-theme',
			__( 'Switch Theme', 'wp-pinch' ),
			__( 'Switch the active theme.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'stylesheet' ),
				'properties' => array(
					'stylesheet' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'switch_themes',
			array( __CLASS__, 'execute_switch_theme' )
		);

		// -- Analytics & Maintenance abilities ----------------------------------

		self::register(
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

		self::register(
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

		self::register(
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

		self::register(
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

		// -- Navigation Menu abilities ------------------------------------------

		self::register(
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

		self::register(
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

		// -- Post Meta abilities ------------------------------------------------

		self::register(
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

		self::register(
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

		// -- Revision abilities -------------------------------------------------

		self::register(
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

		self::register(
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

		// -- Bulk Operations abilities ------------------------------------------

		self::register(
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

		// -- Cron Management abilities ------------------------------------------

		self::register(
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

		self::register(
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

		// -- WooCommerce abilities (conditional) --------------------------------

		if ( class_exists( 'WooCommerce' ) ) {
			self::register(
				'wp-pinch/woo-list-products',
				__( 'List WooCommerce Products', 'wp-pinch' ),
				__( 'List WooCommerce products with optional filters.', 'wp-pinch' ),
				array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array(
							'type'    => 'string',
							'default' => 'publish',
							'enum'    => array( 'publish', 'draft', 'pending', 'private', 'any' ),
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'category' => array(
							'type'        => 'string',
							'default'     => '',
							'description' => 'Product category slug.',
						),
						'search'   => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
				array( 'type' => 'object' ),
				'edit_products',
				array( __CLASS__, 'execute_woo_list_products' ),
				true
			);

			self::register(
				'wp-pinch/woo-manage-order',
				__( 'Manage WooCommerce Order', 'wp-pinch' ),
				__( 'Get details or update the status of a WooCommerce order.', 'wp-pinch' ),
				array(
					'type'       => 'object',
					'required'   => array( 'order_id' ),
					'properties' => array(
						'order_id' => array( 'type' => 'integer' ),
						'action'   => array(
							'type'    => 'string',
							'default' => 'get',
							'enum'    => array( 'get', 'update_status' ),
						),
						'status'   => array(
							'type'        => 'string',
							'default'     => '',
							'description' => 'New order status (for update_status). e.g. processing, completed, on-hold, cancelled.',
						),
						'note'     => array(
							'type'        => 'string',
							'default'     => '',
							'description' => 'Optional order note to add.',
						),
					),
				),
				array( 'type' => 'object' ),
				'edit_shop_orders',
				array( __CLASS__, 'execute_woo_manage_order' )
			);
		}
	}

	// =========================================================================
	// Helper: register with MCP public meta and filtered result
	// =========================================================================

	/**
	 * Register a single ability with MCP public meta.
	 *
	 * @param string   $name        Ability name.
	 * @param string   $title       Human-readable title.
	 * @param string   $description Description.
	 * @param array    $input       JSON Schema for input.
	 * @param array    $output      JSON Schema for output.
	 * @param string   $capability  Required user capability.
	 * @param callable $callback    Execute callback.
	 * @param bool     $readonly    Whether this is a read-only ability (enables caching).
	 */
	private static function register(
		string $name,
		string $title,
		string $description,
		array $input,
		array $output,
		string $capability,
		callable $callback,
		bool $readonly = false
	): void {
		wp_register_ability(
			$name,
			array(
				'title'               => $title,
				'description'         => $description,
				'input_schema'        => $input,
				'output_schema'       => $output,
				'permission_callback' => function () use ( $capability ) {
					return current_user_can( $capability );
				},
				'execute_callback'    => function ( $input ) use ( $name, $callback, $readonly ) {
					// Transient cache for read-only abilities (scoped per user).
					if ( $readonly ) {
						$cache_key = 'wp_pinch_' . md5( get_current_user_id() . ':' . $name . wp_json_encode( $input ) );
						$cached    = get_transient( $cache_key );
						if ( false !== $cached ) {
							return $cached;
						}
					}

					$result = call_user_func( $callback, $input );

					/**
					 * Filter the result of any WP Pinch ability execution.
					 *
					 * @since 1.0.0
					 *
					 * @param mixed  $result The ability result.
					 * @param string $name   The ability name.
					 * @param mixed  $input  The ability input.
					 */
					$result = apply_filters( 'wp_pinch_ability_result', $result, $name, $input );

					if ( $readonly ) {
						set_transient( $cache_key, $result, self::CACHE_TTL );
					}

					return $result;
				},
				'meta'                => array(
					'mcp' => array(
						'public' => true,
					),
				),
			)
		);
	}

	// =========================================================================
	// Execute callbacks — Content
	// =========================================================================

	/**
	 * List posts.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_posts( array $input ): array {
		$post_type = sanitize_key( $input['post_type'] ?? 'post' );

		// Verify type-specific capability when a non-default post type is requested.
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
			$posts[] = self::format_post( $post );
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
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_get_post( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}
		return self::format_post( $post, true );
	}

	/**
	 * Create a post.
	 *
	 * @param array $input Ability input.
	 * @return array
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

		$post_data = array(
			'post_title'   => sanitize_text_field( $input['title'] ),
			'post_content' => wp_kses_post( $input['content'] ?? '' ),
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

		if ( ! empty( $input['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $input['tags'] ) );
		}

		Audit_Table::insert( 'post_created', 'ability', sprintf( 'Post #%d created via ability.', $post_id ), array( 'post_id' => $post_id ) );

		return array(
			'id'  => $post_id,
			'url' => get_permalink( $post_id ),
		);
	}

	/**
	 * Update a post.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_update_post( array $input ): array {
		$post_id = absint( $input['id'] );

		if ( ! get_post( $post_id ) ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to edit this post.', 'wp-pinch' ) );
		}

		$post_data = array( 'ID' => $post_id );

		if ( isset( $input['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
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

		Audit_Table::insert( 'post_updated', 'ability', sprintf( 'Post #%d updated via ability.', $post_id ), array( 'post_id' => $post_id ) );

		return array(
			'id'      => $post_id,
			'updated' => true,
		);
	}

	/**
	 * Delete a post.
	 *
	 * @param array $input Ability input.
	 * @return array
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
	 * @param array $input Ability input.
	 * @return array
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
	 * @param array $input Ability input.
	 * @return array
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

	// =========================================================================
	// Execute callbacks — Media
	// =========================================================================

	/**
	 * List media items.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_media( array $input ): array {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) ),
			'paged'          => max( 1, absint( $input['page'] ?? 1 ) ),
		);

		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = sanitize_mime_type( $input['mime_type'] );
		}
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $attachment ) {
			$items[] = array(
				'id'        => $attachment->ID,
				'title'     => $attachment->post_title,
				'url'       => wp_get_attachment_url( $attachment->ID ),
				'mime_type' => $attachment->post_mime_type,
				'date'      => $attachment->post_date,
			);
		}

		return array(
			'items'       => $items,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
		);
	}

	/**
	 * Upload media from a URL.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_upload_media( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url = esc_url_raw( $input['url'] );

		// Only allow HTTP/HTTPS URLs.
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return array( 'error' => __( 'Only HTTP and HTTPS URLs are allowed.', 'wp-pinch' ) );
		}

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return array( 'error' => $tmp->get_error_message() );
		}

		$file_array = array(
			'name'     => wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return array( 'error' => $attachment_id->get_error_message() );
		}

		if ( ! empty( $input['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $attachment_id,
					'post_title' => sanitize_text_field( $input['title'] ),
				)
			);
		}
		if ( ! empty( $input['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt'] ) );
		}

		Audit_Table::insert( 'media_uploaded', 'ability', sprintf( 'Media #%d uploaded via ability.', $attachment_id ), array( 'attachment_id' => $attachment_id ) );

		return array(
			'id'  => $attachment_id,
			'url' => wp_get_attachment_url( $attachment_id ),
		);
	}

	/**
	 * Delete a media attachment.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_delete_media( array $input ): array {
		$id = absint( $input['id'] );

		if ( ! get_post( $id ) || 'attachment' !== get_post_type( $id ) ) {
			return array( 'error' => __( 'Media attachment not found.', 'wp-pinch' ) );
		}

		$force  = ! empty( $input['force'] );
		$result = wp_delete_attachment( $id, $force );

		if ( ! $result ) {
			return array( 'error' => __( 'Failed to delete media.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'media_deleted',
			'ability',
			sprintf( 'Media #%d deleted via ability.', $id ),
			array(
				'attachment_id' => $id,
				'force'         => $force,
			)
		);

		return array(
			'id'      => $id,
			'deleted' => true,
		);
	}

	// =========================================================================
	// Execute callbacks — Users
	// =========================================================================

	/**
	 * List users.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_users( array $input ): array {
		$args = array(
			'number' => max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) ),
			'paged'  => max( 1, absint( $input['page'] ?? 1 ) ),
		);

		if ( ! empty( $input['role'] ) ) {
			$args['role'] = sanitize_key( $input['role'] );
		}

		$user_query = new \WP_User_Query( $args );

		$users = array_map(
			function ( $user ) {
				return array(
					'id'           => $user->ID,
					'login'        => $user->user_login,
					'display_name' => $user->display_name,
					'roles'        => $user->roles,
					'registered'   => $user->user_registered,
				);
			},
			$user_query->get_results()
		);

		return array(
			'users' => $users,
			'total' => $user_query->get_total(),
		);
	}

	/**
	 * Get a single user.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_get_user( array $input ): array {
		$user = get_userdata( absint( $input['id'] ) );
		if ( ! $user ) {
			return array( 'error' => __( 'User not found.', 'wp-pinch' ) );
		}

		return array(
			'id'           => $user->ID,
			'login'        => $user->user_login,
			'display_name' => $user->display_name,
			'roles'        => $user->roles,
			'registered'   => $user->user_registered,
			'posts_count'  => count_user_posts( $user->ID ),
		);
	}

	/**
	 * Update a user's role.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_update_user_role( array $input ): array {
		$user = get_userdata( absint( $input['id'] ) );
		if ( ! $user ) {
			return array( 'error' => __( 'User not found.', 'wp-pinch' ) );
		}

		$role = sanitize_key( $input['role'] );
		if ( ! wp_roles()->is_role( $role ) ) {
			return array( 'error' => __( 'Invalid role.', 'wp-pinch' ) );
		}

		// Always block administrator role, regardless of filters.
		if ( 'administrator' === $role ) {
			return array( 'error' => __( 'The "administrator" role cannot be assigned via abilities.', 'wp-pinch' ) );
		}

		/**
		 * Filter additional blocked roles that cannot be assigned via the ability.
		 *
		 * Prevents AI agents from escalating users to privileged roles.
		 * Note: 'administrator' is always blocked and cannot be unblocked via this filter.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $blocked Blocked role slugs (in addition to administrator).
		 */
		$blocked_roles = apply_filters( 'wp_pinch_blocked_roles', array() );

		if ( in_array( $role, $blocked_roles, true ) ) {
			return array(
				'error' => sprintf(
					/* translators: %s: role slug */
					__( 'The "%s" role cannot be assigned via abilities.', 'wp-pinch' ),
					$role
				),
			);
		}

		// Block roles with dangerous capabilities (e.g. shop_manager with manage_options).
		$role_obj = get_role( $role );
		if ( $role_obj ) {
			foreach ( self::DANGEROUS_CAPABILITIES as $cap ) {
				if ( $role_obj->has_cap( $cap ) ) {
					return array(
						'error' => sprintf(
							/* translators: %s: role slug */
							__( 'The "%s" role has administrative capabilities and cannot be assigned via abilities.', 'wp-pinch' ),
							$role
						),
					);
				}
			}
		}

		// Prevent modifying the current user's own role.
		if ( get_current_user_id() === $user->ID ) {
			return array( 'error' => __( 'Cannot modify your own role.', 'wp-pinch' ) );
		}

		// Prevent downgrading existing administrators.
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return array( 'error' => __( 'Cannot modify an administrator\'s role via abilities.', 'wp-pinch' ) );
		}

		$user->set_role( $role );

		Audit_Table::insert(
			'user_role_changed',
			'ability',
			sprintf( 'User #%d role changed to %s.', $user->ID, $role ),
			array(
				'user_id' => $user->ID,
				'role'    => $role,
			)
		);

		return array(
			'id'      => $user->ID,
			'role'    => $role,
			'updated' => true,
		);
	}

	// =========================================================================
	// Execute callbacks — Comments
	// =========================================================================

	/**
	 * List comments.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_comments( array $input ): array {
		$args = array(
			'number' => max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) ),
			'paged'  => max( 1, absint( $input['page'] ?? 1 ) ),
		);

		$status = sanitize_key( $input['status'] ?? 'all' );
		if ( 'all' !== $status ) {
			$args['status'] = $status;
		}

		if ( ! empty( $input['post_id'] ) ) {
			$args['post_id'] = absint( $input['post_id'] );
		}

		$comments = get_comments( $args );

		// Count query must omit pagination params to get the real total.
		$count_args = $args;
		unset( $count_args['number'], $count_args['paged'] );
		$total = get_comments( array_merge( $count_args, array( 'count' => true ) ) );

		return array(
			'comments' => array_map(
				function ( $c ) {
					return array(
						'id'      => (int) $c->comment_ID,
						'post_id' => (int) $c->comment_post_ID,
						'author'  => $c->comment_author,
						'content' => wp_trim_words( $c->comment_content, 30 ),
						'status'  => wp_get_comment_status( $c ),
						'date'    => $c->comment_date,
					);
				},
				$comments
			),
			'total'    => (int) $total,
		);
	}

	/**
	 * Moderate a comment.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_moderate_comment( array $input ): array {
		$id     = absint( $input['id'] );
		$status = sanitize_key( $input['status'] );

		if ( ! get_comment( $id ) ) {
			return array( 'error' => __( 'Comment not found.', 'wp-pinch' ) );
		}

		$status_map = array(
			'approve' => '1',
			'hold'    => '0',
			'spam'    => 'spam',
			'trash'   => 'trash',
		);

		if ( ! isset( $status_map[ $status ] ) ) {
			return array( 'error' => __( 'Invalid status.', 'wp-pinch' ) );
		}

		$result = wp_set_comment_status( $id, $status_map[ $status ] );

		if ( ! $result ) {
			return array( 'error' => __( 'Failed to moderate comment.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'comment_moderated',
			'ability',
			sprintf( 'Comment #%d set to %s.', $id, $status ),
			array(
				'comment_id' => $id,
				'status'     => $status,
			)
		);

		return array(
			'id'        => $id,
			'status'    => $status,
			'moderated' => true,
		);
	}

	// =========================================================================
	// Execute callbacks — Settings
	// =========================================================================

	/**
	 * Get an option value.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_get_option( array $input ): array {
		$key = sanitize_text_field( $input['key'] );

		// Denylist is always enforced, regardless of filter output.
		if ( in_array( $key, self::OPTION_DENYLIST, true ) ) {
			return array( 'error' => __( 'This option cannot be read via abilities.', 'wp-pinch' ) );
		}

		/**
		 * Filter the option allowlist for reading.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $allowlist Allowed option keys.
		 */
		$allowlist = apply_filters(
			'wp_pinch_option_read_allowlist',
			array_merge(
				self::OPTION_ALLOWLIST,
				array( 'siteurl', 'home', 'WPLANG', 'permalink_structure' )
			)
		);

		if ( ! in_array( $key, $allowlist, true ) ) {
			return array( 'error' => __( 'Option key not in allowlist.', 'wp-pinch' ) );
		}

		return array(
			'key'   => $key,
			'value' => get_option( $key ),
		);
	}

	/**
	 * Update an allowed option.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_update_option( array $input ): array {
		$key = sanitize_text_field( $input['key'] );

		// Denylist is always enforced, regardless of filter output.
		if ( in_array( $key, self::OPTION_DENYLIST, true ) ) {
			return array( 'error' => __( 'This option cannot be modified via abilities.', 'wp-pinch' ) );
		}

		/**
		 * Filter the option allowlist for writing.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $allowlist Allowed option keys.
		 */
		$allowlist = apply_filters( 'wp_pinch_option_write_allowlist', self::OPTION_ALLOWLIST );

		if ( ! in_array( $key, $allowlist, true ) ) {
			return array( 'error' => __( 'Option key not in allowlist.', 'wp-pinch' ) );
		}

		$old = get_option( $key );
		update_option( $key, sanitize_text_field( (string) $input['value'] ) );

		Audit_Table::insert(
			'option_updated',
			'ability',
			sprintf( 'Option "%s" updated via ability.', $key ),
			array(
				'key' => $key,
				'old' => $old,
			)
		);

		return array(
			'key'     => $key,
			'updated' => true,
		);
	}

	// =========================================================================
	// Execute callbacks — Plugins & Themes
	// =========================================================================

	/**
	 * List installed plugins.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_plugins( array $input ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$status_filter  = sanitize_key( $input['status'] ?? 'all' );
		$plugins        = array();

		foreach ( $all_plugins as $file => $data ) {
			$is_active = in_array( $file, $active_plugins, true );

			if ( 'active' === $status_filter && ! $is_active ) {
				continue;
			}
			if ( 'inactive' === $status_filter && $is_active ) {
				continue;
			}

			$plugins[] = array(
				'file'        => $file,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'active'      => $is_active,
				'description' => wp_trim_words( $data['Description'] ?? '', 15 ),
			);
		}

		return array(
			'plugins' => $plugins,
			'total'   => count( $plugins ),
		);
	}

	/**
	 * Activate or deactivate a plugin.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_toggle_plugin( array $input ): array {
		$plugin   = sanitize_text_field( $input['plugin'] );
		$activate = ! empty( $input['activate'] );

		// Prevent deactivating this plugin via the ability.
		if ( ! $activate && plugin_basename( WP_PINCH_FILE ) === $plugin ) {
			return array( 'error' => __( 'WP Pinch cannot deactivate itself via an ability.', 'wp-pinch' ) );
		}

		if ( $activate ) {
			$result = activate_plugin( $plugin );
			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
		} else {
			deactivate_plugins( $plugin );
		}

		Audit_Table::insert(
			$activate ? 'plugin_activated' : 'plugin_deactivated',
			'ability',
			sprintf( 'Plugin "%s" %s via ability.', $plugin, $activate ? 'activated' : 'deactivated' ),
			array( 'plugin' => $plugin )
		);

		return array(
			'plugin' => $plugin,
			'active' => $activate,
		);
	}

	/**
	 * List installed themes.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_themes( array $input ): array {
		$themes       = wp_get_themes();
		$active_theme = get_stylesheet();

		$list = array();
		foreach ( $themes as $slug => $theme ) {
			$list[] = array(
				'stylesheet' => $slug,
				'name'       => $theme->get( 'Name' ),
				'version'    => $theme->get( 'Version' ),
				'active'     => $slug === $active_theme,
				'parent'     => $theme->parent() ? $theme->parent()->get_stylesheet() : null,
			);
		}

		return array(
			'themes' => $list,
			'total'  => count( $list ),
		);
	}

	/**
	 * Switch the active theme.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_switch_theme( array $input ): array {
		$stylesheet = sanitize_text_field( $input['stylesheet'] );
		$theme      = wp_get_theme( $stylesheet );

		if ( ! $theme->exists() ) {
			return array( 'error' => __( 'Theme not found.', 'wp-pinch' ) );
		}

		switch_theme( $stylesheet );

		Audit_Table::insert( 'theme_switched', 'ability', sprintf( 'Theme switched to "%s" via ability.', $stylesheet ), array( 'stylesheet' => $stylesheet ) );

		return array(
			'stylesheet' => $stylesheet,
			'switched'   => true,
		);
	}

	// =========================================================================
	// Execute callbacks — Analytics & Maintenance
	// =========================================================================

	/**
	 * Get site health info.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_site_health( array $input ): array {
		global $wpdb;

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
				'tables' => count( $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) ) ),
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
	 * Get recent site activity.
	 *
	 * @param array $input Ability input.
	 * @return array
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

		return array(
			'recent_posts'    => array_map(
				function ( $p ) {
					return array(
						'id'       => $p->ID,
						'title'    => $p->post_title,
						'status'   => $p->post_status,
						'modified' => $p->post_modified,
						'author'   => get_the_author_meta( 'display_name', (int) $p->post_author ),
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
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_search_content( array $input ): array {
		$post_type = sanitize_key( $input['post_type'] ?? 'any' );

		// Verify type-specific capability when a specific post type is requested.
		if ( 'any' !== $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
				return array( 'error' => __( 'Insufficient permissions for this post type.', 'wp-pinch' ) );
			}
		}

		// Respect read_private_posts capability.
		$statuses = current_user_can( 'read_private_posts' )
			? array( 'publish', 'draft', 'pending', 'private' )
			: array( 'publish', 'draft', 'pending' );

		$query = new \WP_Query(
			array(
				's'              => sanitize_text_field( $input['query'] ),
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
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_export_data( array $input ): array {
		$type     = sanitize_key( $input['type'] );
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
					'data'  => array_map( array( __CLASS__, 'format_post' ), $query->posts ),
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

	// =========================================================================
	// Execute callbacks — Navigation Menus
	// =========================================================================

	/**
	 * List navigation menus.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_menus( array $input ): array {
		$menu_identifier = sanitize_text_field( $input['menu'] ?? '' );

		// If a specific menu is requested, return its items.
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

		// List all menus.
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
	 * @param array $input Ability input.
	 * @return array
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

				// If an object reference is provided (e.g. page, post, category).
				if ( ! empty( $input['object'] ) && ! empty( $input['object_id'] ) ) {
					$object    = sanitize_key( $input['object'] );
					$object_id = absint( $input['object_id'] );
					$type      = taxonomy_exists( $object ) ? 'taxonomy' : 'post_type';

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

				// Verify the item is actually a nav_menu_item to prevent deleting arbitrary posts.
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

	// =========================================================================
	// Execute callbacks — Post Meta
	// =========================================================================

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
	 * Get post meta values.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_get_post_meta( array $input ): array {
		$post_id = absint( $input['post_id'] );

		if ( ! get_post( $post_id ) ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}

		$key = sanitize_text_field( $input['key'] ?? '' );

		// Return a specific meta key.
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

		// Return all non-protected meta.
		$all_meta = get_post_meta( $post_id );
		$filtered = array();

		foreach ( $all_meta as $meta_key => $values ) {
			if ( is_protected_meta( $meta_key, 'post' ) ) {
				continue;
			}

			// Skip internal WordPress meta prefixes.
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
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_update_post_meta( array $input ): array {
		$post_id = absint( $input['post_id'] );

		if ( ! get_post( $post_id ) ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}

		$key = sanitize_text_field( $input['key'] );

		if ( is_protected_meta( $key, 'post' ) ) {
			return array( 'error' => __( 'This meta key is protected and cannot be modified.', 'wp-pinch' ) );
		}

		// Delete action.
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

		// Set/update action — sanitize all value types.
		$value = $input['value'] ?? '';
		if ( is_string( $value ) ) {
			$value = sanitize_text_field( $value );
		} elseif ( is_array( $value ) ) {
			// Reject nested arrays — only flat arrays of scalar values are supported.
			foreach ( $value as $v ) {
				if ( ! is_scalar( $v ) ) {
					return array( 'error' => __( 'Nested arrays are not supported for post meta values.', 'wp-pinch' ) );
				}
			}
			$value = array_map( 'sanitize_text_field', $value );
		} elseif ( is_numeric( $value ) ) {
			// Numeric values are safe as-is.
			$value = $value + 0; // Normalize to int or float.
		} elseif ( is_bool( $value ) ) {
			$value = $value ? '1' : '0';
		} else {
			// Reject unsupported types (objects, etc.).
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

	// =========================================================================
	// Execute callbacks — Revisions
	// =========================================================================

	/**
	 * List post revisions.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_revisions( array $input ): array {
		$post_id = absint( $input['post_id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}

		if ( ! wp_revisions_enabled( $post ) ) {
			return array( 'error' => __( 'Revisions are not enabled for this post type.', 'wp-pinch' ) );
		}

		$revisions = wp_get_post_revisions( $post_id, array( 'order' => 'DESC' ) );

		return array(
			'post_id'   => $post_id,
			'revisions' => array_map(
				function ( $rev ) {
					return array(
						'id'      => $rev->ID,
						'author'  => get_the_author_meta( 'display_name', (int) $rev->post_author ),
						'date'    => $rev->post_date,
						'title'   => $rev->post_title,
						'excerpt' => wp_trim_words( $rev->post_content, 30 ),
					);
				},
				array_values( $revisions )
			),
			'total'     => count( $revisions ),
		);
	}

	/**
	 * Restore a post to a specific revision.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_restore_revision( array $input ): array {
		$revision_id = absint( $input['revision_id'] );
		$revision    = wp_get_post_revision( $revision_id );

		if ( ! $revision ) {
			return array( 'error' => __( 'Revision not found.', 'wp-pinch' ) );
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

	// =========================================================================
	// Execute callbacks — Bulk Operations
	// =========================================================================

	/**
	 * Bulk edit posts.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_bulk_edit_posts( array $input ): array {
		$post_ids = array_values( array_filter( array_map( 'absint', $input['post_ids'] ?? array() ) ) );
		$action   = sanitize_key( $input['action'] );

		// Safety limit.
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
					$result = wp_trash_post( $post_id );
					if ( ! $result ) {
						$results[] = array(
							'id'    => $post_id,
							'error' => __( 'Failed to trash.', 'wp-pinch' ),
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

				case 'delete':
					// Bulk delete always uses trash for safety — use single delete-post with force for permanent deletion.
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

	// =========================================================================
	// Execute callbacks — Cron Management
	// =========================================================================

	/**
	 * List scheduled cron events.
	 *
	 * @param array $input Ability input.
	 * @return array
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

		// Sort by timestamp.
		usort(
			$events,
			function ( $a, $b ) {
				return $a['timestamp'] <=> $b['timestamp'];
			}
		);

		$total = count( $events );

		// Cap the returned events to avoid oversized responses.
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
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_manage_cron( array $input ): array {
		$action    = sanitize_key( $input['action'] );
		$hook      = sanitize_text_field( $input['hook'] );
		$timestamp = absint( $input['timestamp'] ?? 0 );

		switch ( $action ) {
			case 'run':
				/**
				 * Run the cron hook immediately — only if it is an actual
				 * scheduled cron event. We never call do_action() on arbitrary
				 * hooks because that would be a code-execution primitive.
				 */
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
					/* translators: %s: WP-Cron hook name. */
					return array( 'error' => sprintf( __( 'No callbacks registered for hook "%s".', 'wp-pinch' ), $hook ) );
				}

				do_action( $hook ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

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
				// Protect critical WordPress core cron hooks from deletion.
				if ( in_array( $hook, self::PROTECTED_CRON_HOOKS, true ) ) {
					return array( 'error' => __( 'Cannot delete core WordPress cron events.', 'wp-pinch' ) );
				}

				if ( 0 === $timestamp ) {
					// Unschedule all events for this hook.
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

				// Unschedule a specific event by timestamp.
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

	// =========================================================================
	// Execute callbacks — WooCommerce
	// =========================================================================

	/**
	 * List WooCommerce products.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_woo_list_products( array $input ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => __( 'WooCommerce is not active.', 'wp-pinch' ) );
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => sanitize_key( $input['status'] ?? 'publish' ),
			'posts_per_page' => min( absint( $input['per_page'] ?? 20 ), 100 ),
			'paged'          => absint( $input['page'] ?? 1 ),
		);

		if ( 'any' === $args['post_status'] ) {
			$args['post_status'] = array( 'publish', 'draft', 'pending', 'private' );
		}

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['category'] ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => sanitize_text_field( $input['category'] ),
				),
			);
		}

		$query    = new \WP_Query( $args );
		$products = array();

		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}

			$products[] = array(
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'slug'          => $product->get_slug(),
				'type'          => $product->get_type(),
				'status'        => $product->get_status(),
				'sku'           => $product->get_sku(),
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'stock_status'  => $product->get_stock_status(),
				'stock_qty'     => $product->get_stock_quantity(),
				'categories'    => wp_list_pluck(
					$product->get_category_ids() ? get_terms(
						array(
							'include'  => $product->get_category_ids(),
							'taxonomy' => 'product_cat',
						)
					) : array(),
					'name'
				),
				'url'           => $product->get_permalink(),
			);
		}

		return array(
			'products'    => $products,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'page'        => $args['paged'],
		);
	}

	/**
	 * Get or manage a WooCommerce order.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_woo_manage_order( array $input ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => __( 'WooCommerce is not active.', 'wp-pinch' ) );
		}

		$order_id = absint( $input['order_id'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return array( 'error' => __( 'Order not found.', 'wp-pinch' ) );
		}

		$action = sanitize_key( $input['action'] ?? 'get' );

		// Add a note if provided.
		if ( ! empty( $input['note'] ) ) {
			$order->add_order_note( sanitize_text_field( $input['note'] ) );
		}

		if ( 'update_status' === $action ) {
			$new_status = sanitize_key( $input['status'] ?? '' );
			if ( '' === $new_status ) {
				return array( 'error' => __( 'Status is required for update_status action.', 'wp-pinch' ) );
			}

			$old_status = $order->get_status();
			$order->update_status( $new_status );

			Audit_Table::insert(
				'woo_order_updated',
				'ability',
				sprintf( 'Order #%d status changed from %s to %s.', $order_id, $old_status, $new_status ),
				array(
					'order_id'   => $order_id,
					'old_status' => $old_status,
					'new_status' => $new_status,
				)
			);

			return array(
				'order_id'   => $order_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'updated'    => true,
			);
		}

		// Default: get order details.
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
				'sku'      => $item->get_product() ? $item->get_product()->get_sku() : '',
			);
		}

		return array(
			'order_id'     => $order_id,
			'status'       => $order->get_status(),
			'total'        => $order->get_total(),
			'currency'     => $order->get_currency(),
			'date_created' => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
			'customer'     => array(
				'id'   => $order->get_customer_id(),
				'name' => $order->get_billing_first_name(), // First name only — no email/full name to prevent PII leakage.
			),
			'items'        => $items,
			'shipping'     => $order->get_shipping_total(),
			'notes'        => array_map(
				function ( $note ) {
					return array(
						'content' => $note->comment_content,
						'date'    => $note->comment_date,
					);
				},
				wc_get_order_notes(
					array(
						'order_id' => $order_id,
						'limit'    => 10,
					)
				)
			),
		);
	}

	// =========================================================================
	// Format helpers
	// =========================================================================

	/**
	 * Format a navigation menu item for API output.
	 *
	 * @param \WP_Post $item Menu item post object.
	 * @return array
	 */
	private static function format_menu_item( \WP_Post $item ): array {
		return array(
			'id'        => (int) $item->ID,
			'title'     => $item->title,
			'url'       => $item->url,
			'type'      => $item->type,
			'object'    => $item->object,
			'object_id' => (int) $item->object_id,
			'parent'    => (int) $item->menu_item_parent,
			'position'  => (int) $item->menu_order,
			'target'    => $item->target,
			'classes'   => array_filter( $item->classes ),
		);
	}


	/**
	 * Format a WP_Post for API output.
	 *
	 * @param \WP_Post $post    Post object.
	 * @param bool     $full    Include full content.
	 * @return array
	 */
	private static function format_post( \WP_Post $post, bool $full = false ): array {
		$data = array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'type'     => $post->post_type,
			'date'     => $post->post_date,
			'modified' => $post->post_modified,
			'author'   => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'url'      => get_permalink( $post->ID ),
			'excerpt'  => wp_trim_words( $post->post_content, 30 ),
		);

		if ( $full ) {
			$data['content']    = $post->post_content;
			$data['categories'] = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
			$data['tags']       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
			$data['thumbnail']  = get_the_post_thumbnail_url( $post->ID, 'full' );
		}

		return $data;
	}
}
