<?php
/**
 * WordPress Abilities registration — core + conditional abilities across content, media, admin, governance, and commerce.
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
		'wp_pinch_capture_token',
		'siteurl',
		'home',
		'admin_email',
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
	 * Default ability names that use the configurable transient cache (TTL and invalidation on post changes).
	 *
	 * @return string[]
	 */
	public static function get_cacheable_ability_names(): array {
		$default = array( 'list-posts', 'search-content', 'list-media', 'list-taxonomies' );
		return (array) apply_filters( 'wp_pinch_cacheable_abilities', $default );
	}

	/**
	 * Invalidate ability cache (e.g. on save_post / deleted_post).
	 *
	 * Bumps the cache generation so all existing ability result transients become stale.
	 */
	public static function invalidate_ability_cache(): void {
		$gen = (int) get_option( 'wp_pinch_ability_cache_generation', 0 );
		update_option( 'wp_pinch_ability_cache_generation', $gen + 1, false );
	}

	/**
	 * Flush all read-only ability caches for the current user.
	 *
	 * Called after any mutation ability executes so that subsequent
	 * reads reflect the change immediately.
	 *
	 * Flushes the object cache group when a persistent backend is
	 * available, and falls back to clearing transients.
	 */
	private static function flush_user_cache(): void {
		// When using an external object cache, flush the whole abilities group.
		if ( wp_using_ext_object_cache() ) {
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				try {
					wp_cache_flush_group( 'wp-pinch-abilities' );
				} catch ( \Throwable $e ) {
					// Some backends define the function but do not support group flushing.
					// Keys will expire via TTL (CACHE_TTL). No action needed.
					unset( $e );
				}
			}
			// Fallback: wp_cache_flush_group may not exist on older object cache backends.
			// In that case, individual keys expire via TTL.
			return;
		}

		// Transient fallback — delete all transients with the user-scoped prefix.
		global $wpdb;

		$prefix = 'wp_pinch_' . md5( get_current_user_id() . ':' );
		$like   = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$names  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);
		foreach ( (array) $names as $option_name ) {
			if ( str_starts_with( $option_name, '_transient_' ) && ! str_starts_with( $option_name, '_transient_timeout_' ) ) {
				delete_transient( str_replace( '_transient_', '', $option_name ) );
			}
		}
	}

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ), 20 );
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
			'wp_abilities_api_init',
			function () {
				do_action( 'wp_pinch_register_abilities' );
			},
			25
		);
	}

	/**
	 * Register the wp-pinch ability category.
	 *
	 * Must run on wp_abilities_api_categories_init (before wp_abilities_api_init)
	 * per the Abilities API: https://developer.wordpress.org/apis/abilities-api/
	 *
	 * @return void
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			'wp-pinch',
			array(
				'label'       => __( 'WP Pinch', 'wp-pinch' ),
				'description' => __( 'Abilities for content, media, options, and site management via OpenClaw.', 'wp-pinch' ),
			)
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
			'wp-pinch/duplicate-post',
			'wp-pinch/schedule-post',
			'wp-pinch/find-replace-content',
			'wp-pinch/reorder-posts',
			'wp-pinch/compare-revisions',

			// Media.
			'wp-pinch/list-media',
			'wp-pinch/upload-media',
			'wp-pinch/delete-media',
			'wp-pinch/set-featured-image',
			'wp-pinch/list-unused-media',
			'wp-pinch/regenerate-media-thumbnails',

			// Users.
			'wp-pinch/list-users',
			'wp-pinch/get-user',
			'wp-pinch/update-user-role',
			'wp-pinch/create-user',
			'wp-pinch/delete-user',
			'wp-pinch/reset-user-password',

			// Comments.
			'wp-pinch/list-comments',
			'wp-pinch/moderate-comment',
			'wp-pinch/create-comment',
			'wp-pinch/update-comment',
			'wp-pinch/delete-comment',

			// Settings.
			'wp-pinch/get-option',
			'wp-pinch/update-option',

			// Plugins & Themes.
			'wp-pinch/list-plugins',
			'wp-pinch/toggle-plugin',
			'wp-pinch/list-themes',
			'wp-pinch/switch-theme',
			'wp-pinch/manage-plugin-lifecycle',
			'wp-pinch/manage-theme-lifecycle',

			// Analytics & Maintenance.
			'wp-pinch/site-health',
			'wp-pinch/content-health-report',
			'wp-pinch/recent-activity',
			'wp-pinch/search-content',
			'wp-pinch/export-data',
			'wp-pinch/site-digest',
			'wp-pinch/related-posts',
			'wp-pinch/synthesize',
			'wp-pinch/analytics-narratives',
			'wp-pinch/submit-conversational-form',
			'wp-pinch/generate-tldr',
			'wp-pinch/suggest-links',
			'wp-pinch/suggest-terms',
			'wp-pinch/quote-bank',
			'wp-pinch/what-do-i-know',
			'wp-pinch/project-assembly',
			'wp-pinch/spaced-resurfacing',
			'wp-pinch/find-similar',
			'wp-pinch/knowledge-graph',
			'wp-pinch/pinchdrop-generate',

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
			'wp-pinch/get-transient',
			'wp-pinch/set-transient',
			'wp-pinch/delete-transient',
			'wp-pinch/list-rewrite-rules',
			'wp-pinch/flush-rewrite-rules',
			'wp-pinch/maintenance-mode-status',
			'wp-pinch/set-maintenance-mode',
			'wp-pinch/search-replace-db-scoped',
			'wp-pinch/list-language-packs',
			'wp-pinch/install-language-pack',
			'wp-pinch/activate-language-pack',
			'wp-pinch/flush-cache',
			'wp-pinch/check-broken-links',
			'wp-pinch/get-php-error-log',
			'wp-pinch/list-posts-missing-meta',
			'wp-pinch/list-custom-post-types',

			// GEO & SEO.
			'wp-pinch/generate-llms-txt',
			'wp-pinch/get-llms-txt',
			'wp-pinch/bulk-seo-meta',
			'wp-pinch/suggest-internal-links',
			'wp-pinch/generate-schema-markup',
			'wp-pinch/suggest-seo-improvements',
		);

		// WooCommerce abilities — only registered when WooCommerce is active.
		if ( class_exists( 'WooCommerce' ) ) {
			$abilities[] = 'wp-pinch/woo-list-products';
			$abilities[] = 'wp-pinch/woo-manage-order';
		}

		return $abilities;
	}

	/**
	 * Register all ability modules.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Ability\Content_Abilities::register();
		Ability\Content_Workflow_Abilities::register();
		Ability\Media_Abilities::register();
		Ability\Media_Extended_Abilities::register();
		Ability\User_Comment_Abilities::register();
		Ability\User_Comment_Extended_Abilities::register();
		Ability\Settings_Abilities::register();
		Ability\Extension_Lifecycle_Abilities::register();
		Ability\Analytics_Abilities::register();
		Ability\Site_Ops_Abilities::register();
		Ability\System_Admin_Abilities::register();
		Ability\QuickWin_Abilities::register();
		Ability\PinchDrop_Abilities::register();
		Ability\Menu_Meta_Revisions_Abilities::register();
		Ability\Woo_Abilities::register();
		Ability\GhostWriter_Molt_Abilities::register();
		Ability\GEO_SEO_Abilities::register();
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
	/**
	 * Check whether an ability is disabled by admin toggle.
	 *
	 * @param string $name Ability name (e.g. 'wp-pinch/list-posts').
	 * @return bool True if disabled.
	 */
	public static function is_disabled( string $name ): bool {
		if ( ! Feature_Flags::is_enabled( 'ability_toggle' ) ) {
			return false;
		}

		$disabled = get_option( 'wp_pinch_disabled_abilities', array() );

		if ( ! is_array( $disabled ) ) {
			return false;
		}

		return in_array( $name, $disabled, true );
	}

	/**
	 * Register a single ability with the Abilities API. Public so Ability sub-modules can call it.
	 *
	 * @param string   $name        Ability name (e.g. 'wp-pinch/list-posts').
	 * @param string   $title       Label.
	 * @param string   $description Description.
	 * @param array    $input       Input schema.
	 * @param array    $output      Output schema.
	 * @param string   $capability  Required capability.
	 * @param callable $callback    Execute callback.
	 * @param bool     $readonly    Whether read-only (enables caching).
	 */
	public static function register_ability(
		string $name,
		string $title,
		string $description,
		array $input,
		array $output,
		string $capability,
		callable $callback,
		bool $readonly = false
	): void {
		// Skip registration if admin has disabled this ability.
		if ( self::is_disabled( $name ) ) {
			return;
		}

		wp_register_ability(
			$name,
			array(
				'label'               => $title,
				'description'         => $description,
				'category'            => 'wp-pinch',
				'input_schema'        => $input,
				'output_schema'       => $output,
				'permission_callback' => function () use ( $capability ) {
					return current_user_can( $capability );
				},
				'execute_callback'    => function ( $input ) use ( $name, $callback, $readonly ) {
					// Read-only mode: block all write abilities.
					if ( ! $readonly && Plugin::is_read_only_mode() ) {
						return array( 'error' => __( 'API is in read-only mode. Write operations are disabled.', 'wp-pinch' ) );
					}

					// Configurable cache for selected read-only abilities (TTL + invalidation on post save/delete).
					$cache_ttl    = (int) get_option( 'wp_pinch_ability_cache_ttl', 300 );
					$cacheable    = $readonly && $cache_ttl > 0 && in_array( $name, self::get_cacheable_ability_names(), true );
					$gen          = $cacheable ? (int) get_option( 'wp_pinch_ability_cache_generation', 0 ) : 0;
					$cache_key    = $cacheable ? 'wp_pinch_ab_' . $gen . '_' . md5( get_current_user_id() . ':' . $name . wp_json_encode( $input ) ) : '';

					if ( $cacheable ) {
						if ( wp_using_ext_object_cache() ) {
							$cached = wp_cache_get( $cache_key, 'wp-pinch-abilities' );
							if ( false !== $cached ) {
								return $cached;
							}
						} else {
							$cached = get_transient( $cache_key );
							if ( false !== $cached ) {
								return $cached;
							}
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

					if ( $cacheable ) {
						if ( wp_using_ext_object_cache() ) {
							wp_cache_set( $cache_key, $result, 'wp-pinch-abilities', $cache_ttl );
						} else {
							set_transient( $cache_key, $result, $cache_ttl );
						}
					} elseif ( ! $readonly ) {
						// Mutation ability — flush all read-only caches for the current user
						// so subsequent reads reflect the change immediately.
						self::flush_user_cache();
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

	// Content abilities — passthrough to Ability\Content_Abilities (for tests and backward compatibility).
	/** @param array<string, mixed> $input */
	public static function execute_list_posts( array $input ): array {
		return Ability\Content_Abilities::execute_list_posts( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_get_post( array $input ): array {
		return Ability\Content_Abilities::execute_get_post( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_create_post( array $input ): array {
		return Ability\Content_Abilities::execute_create_post( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_update_post( array $input ): array {
		return Ability\Content_Abilities::execute_update_post( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_delete_post( array $input ): array {
		return Ability\Content_Abilities::execute_delete_post( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_list_taxonomies( array $input ): array {
		return Ability\Content_Abilities::execute_list_taxonomies( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_manage_terms( array $input ): array {
		return Ability\Content_Abilities::execute_manage_terms( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_duplicate_post( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_duplicate_post( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_schedule_post( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_schedule_post( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_find_replace_content( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_find_replace_content( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_reorder_posts( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_reorder_posts( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_compare_revisions( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_compare_revisions( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_list_media( array $input ): array {
		return Ability\Media_Abilities::execute_list_media( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_upload_media( array $input ): array {
		return Ability\Media_Abilities::execute_upload_media( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_delete_media( array $input ): array {
		return Ability\Media_Abilities::execute_delete_media( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_set_featured_image( array $input ): array {
		return Ability\Media_Extended_Abilities::execute_set_featured_image( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_list_unused_media( array $input ): array {
		return Ability\Media_Extended_Abilities::execute_list_unused_media( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_regenerate_media_thumbnails( array $input ): array {
		return Ability\Media_Extended_Abilities::execute_regenerate_media_thumbnails( $input );
	}

	// =========================================================================
	// Execute callbacks — Users
	// =========================================================================

	/**
	 * List users. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_users( array $input ): array {
		return Ability\User_Comment_Abilities::execute_list_users( $input );
	}

	/**
	 * Get a single user. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_get_user( array $input ): array {
		return Ability\User_Comment_Abilities::execute_get_user( $input );
	}

	/**
	 * Update a user's role. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_update_user_role( array $input ): array {
		return Ability\User_Comment_Abilities::execute_update_user_role( $input );
	}

	/**
	 * List comments. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_comments( array $input ): array {
		return Ability\User_Comment_Abilities::execute_list_comments( $input );
	}

	/**
	 * Moderate a comment. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_moderate_comment( array $input ): array {
		return Ability\User_Comment_Abilities::execute_moderate_comment( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_create_user( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_create_user( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_delete_user( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_delete_user( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_reset_user_password( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_reset_user_password( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_create_comment( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_create_comment( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_update_comment( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_update_comment( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_delete_comment( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_delete_comment( $input );
	}

	/**
	 * Get an option value. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_get_option( array $input ): array {
		return Ability\Settings_Abilities::execute_get_option( $input );
	}

	/**
	 * Update an allowed option. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_update_option( array $input ): array {
		return Ability\Settings_Abilities::execute_update_option( $input );
	}

	/**
	 * List installed plugins. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_plugins( array $input ): array {
		return Ability\Settings_Abilities::execute_list_plugins( $input );
	}

	/**
	 * Activate or deactivate a plugin. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_toggle_plugin( array $input ): array {
		return Ability\Settings_Abilities::execute_toggle_plugin( $input );
	}

	/**
	 * List installed themes. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_themes( array $input ): array {
		return Ability\Settings_Abilities::execute_list_themes( $input );
	}

	/**
	 * Switch the active theme. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_switch_theme( array $input ): array {
		return Ability\Settings_Abilities::execute_switch_theme( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_manage_plugin_lifecycle( array $input ): array {
		return Ability\Extension_Lifecycle_Abilities::execute_manage_plugin_lifecycle( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_manage_theme_lifecycle( array $input ): array {
		return Ability\Extension_Lifecycle_Abilities::execute_manage_theme_lifecycle( $input );
	}

	// =========================================================================
	// Execute callbacks — Analytics & Maintenance (passthroughs)
	// =========================================================================

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_site_health */
	public static function execute_site_health( array $input ): array {
		return Ability\Analytics_Abilities::execute_site_health( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_content_health_report */
	public static function execute_content_health_report( array $input ): array {
		return Ability\Analytics_Abilities::execute_content_health_report( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_recent_activity */
	public static function execute_recent_activity( array $input ): array {
		return Ability\Analytics_Abilities::execute_recent_activity( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_search_content */
	public static function execute_search_content( array $input ): array {
		return Ability\Analytics_Abilities::execute_search_content( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_export_data */
	public static function execute_export_data( array $input ): array {
		return Ability\Analytics_Abilities::execute_export_data( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_site_digest */
	public static function execute_site_digest( array $input ): array {
		return Ability\Analytics_Abilities::execute_site_digest( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_related_posts */
	public static function execute_related_posts( array $input ): array {
		return Ability\Analytics_Abilities::execute_related_posts( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_synthesize */
	public static function execute_synthesize( array $input ): array {
		return Ability\Analytics_Abilities::execute_synthesize( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_analytics_narratives */
	public static function execute_analytics_narratives( array $input ): array {
		return Ability\Analytics_Abilities::execute_analytics_narratives( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_submit_conversational_form */
	public static function execute_submit_conversational_form( array $input ): array {
		return Ability\Analytics_Abilities::execute_submit_conversational_form( $input );
	}

	// =========================================================================
	// Execute callbacks — Quick-win (passthroughs)
	// =========================================================================

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_generate_tldr */
	public static function execute_generate_tldr( array $input ): array {
		return Ability\QuickWin_Abilities::execute_generate_tldr( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_suggest_links */
	public static function execute_suggest_links( array $input ): array {
		return Ability\QuickWin_Abilities::execute_suggest_links( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_suggest_terms */
	public static function execute_suggest_terms( array $input ): array {
		return Ability\QuickWin_Abilities::execute_suggest_terms( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_quote_bank */
	public static function execute_quote_bank( array $input ): array {
		return Ability\QuickWin_Abilities::execute_quote_bank( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_what_do_i_know */
	public static function execute_what_do_i_know( array $input ): array {
		return Ability\QuickWin_Abilities::execute_what_do_i_know( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_project_assembly */
	public static function execute_project_assembly( array $input ): array {
		return Ability\QuickWin_Abilities::execute_project_assembly( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_knowledge_graph */
	public static function execute_knowledge_graph( array $input ): array {
		return Ability\QuickWin_Abilities::execute_knowledge_graph( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_find_similar */
	public static function execute_find_similar( array $input ): array {
		return Ability\QuickWin_Abilities::execute_find_similar( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_spaced_resurfacing */
	public static function execute_spaced_resurfacing( array $input ): array {
		return Ability\QuickWin_Abilities::execute_spaced_resurfacing( $input );
	}

	/** @see \WP_Pinch\Ability\PinchDrop_Abilities::execute_pinchdrop_generate */
	public static function execute_pinchdrop_generate( array $input ): array {
		return Ability\PinchDrop_Abilities::execute_pinchdrop_generate( $input );
	}

	// =========================================================================
	// Execute callbacks — Menus, Meta, Revisions, Bulk, Cron (passthroughs)
	// =========================================================================

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_list_menus */
	public static function execute_list_menus( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_list_menus( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_manage_menu_item */
	public static function execute_manage_menu_item( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_manage_menu_item( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_get_post_meta */
	public static function execute_get_post_meta( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_get_post_meta( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_update_post_meta */
	public static function execute_update_post_meta( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_update_post_meta( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_list_revisions */
	public static function execute_list_revisions( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_list_revisions( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_restore_revision */
	public static function execute_restore_revision( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_restore_revision( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_bulk_edit_posts */
	public static function execute_bulk_edit_posts( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_bulk_edit_posts( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_list_cron_events */
	public static function execute_list_cron_events( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_list_cron_events( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_manage_cron */
	public static function execute_manage_cron( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_manage_cron( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_get_transient */
	public static function execute_get_transient( array $input ): array {
		return Ability\System_Admin_Abilities::execute_get_transient( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_set_transient */
	public static function execute_set_transient( array $input ): array {
		return Ability\System_Admin_Abilities::execute_set_transient( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_delete_transient */
	public static function execute_delete_transient( array $input ): array {
		return Ability\System_Admin_Abilities::execute_delete_transient( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_list_rewrite_rules */
	public static function execute_list_rewrite_rules( array $input ): array {
		return Ability\System_Admin_Abilities::execute_list_rewrite_rules( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_flush_rewrite_rules */
	public static function execute_flush_rewrite_rules( array $input ): array {
		return Ability\System_Admin_Abilities::execute_flush_rewrite_rules( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_maintenance_mode_status */
	public static function execute_maintenance_mode_status( array $input ): array {
		return Ability\System_Admin_Abilities::execute_maintenance_mode_status( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_set_maintenance_mode */
	public static function execute_set_maintenance_mode( array $input ): array {
		return Ability\System_Admin_Abilities::execute_set_maintenance_mode( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_search_replace_db_scoped */
	public static function execute_search_replace_db_scoped( array $input ): array {
		return Ability\System_Admin_Abilities::execute_search_replace_db_scoped( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_list_language_packs */
	public static function execute_list_language_packs( array $input ): array {
		return Ability\System_Admin_Abilities::execute_list_language_packs( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_install_language_pack */
	public static function execute_install_language_pack( array $input ): array {
		return Ability\System_Admin_Abilities::execute_install_language_pack( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_activate_language_pack */
	public static function execute_activate_language_pack( array $input ): array {
		return Ability\System_Admin_Abilities::execute_activate_language_pack( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_flush_cache */
	public static function execute_flush_cache( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_flush_cache( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_check_broken_links */
	public static function execute_check_broken_links( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_check_broken_links( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_get_php_error_log */
	public static function execute_get_php_error_log( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_get_php_error_log( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_list_posts_missing_meta */
	public static function execute_list_posts_missing_meta( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_list_posts_missing_meta( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_list_custom_post_types */
	public static function execute_list_custom_post_types( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_list_custom_post_types( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_list_products */
	public static function execute_woo_list_products( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_list_products( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_manage_order */
	public static function execute_woo_manage_order( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_manage_order( $input );
	}

	/** @see \WP_Pinch\Ability\GhostWriter_Molt_Abilities::execute_analyze_voice */
	public static function execute_analyze_voice( array $input ): array {
		return Ability\GhostWriter_Molt_Abilities::execute_analyze_voice( $input );
	}

	/** @see \WP_Pinch\Ability\GhostWriter_Molt_Abilities::execute_list_abandoned_drafts */
	public static function execute_list_abandoned_drafts( array $input ): array {
		return Ability\GhostWriter_Molt_Abilities::execute_list_abandoned_drafts( $input );
	}

	/** @see \WP_Pinch\Ability\GhostWriter_Molt_Abilities::execute_ghostwrite */
	public static function execute_ghostwrite( array $input ): array {
		return Ability\GhostWriter_Molt_Abilities::execute_ghostwrite( $input );
	}

	/** @see \WP_Pinch\Ability\GhostWriter_Molt_Abilities::execute_molt */
	public static function execute_molt( array $input ): array {
		return Ability\GhostWriter_Molt_Abilities::execute_molt( $input );
	}

	/**
	 * Format a WP_Post for API output. Public so Ability sub-modules (e.g. Content_Abilities) can call it.
	 *
	 * @param \WP_Post $post Post object.
	 * @param bool     $full Include full content.
	 * @return array<string, mixed> Formatted post data.
	 */
	public static function format_post( \WP_Post $post, bool $full = false ): array {
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
