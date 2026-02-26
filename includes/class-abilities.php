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
	use Ability_Names_Trait;
	use Core_Passthrough_Trait;
	use Woo_Passthrough_Trait;

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
			'format'   => get_post_format( $post->ID ) ?: 'standard',
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
