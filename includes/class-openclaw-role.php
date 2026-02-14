<?php
/**
 * OpenClaw Role — dedicated least-privilege role for webhook ability execution.
 *
 * Creates an openclaw_agent role with configurable capability groups. When the
 * incoming webhook executes abilities, it runs as either the designated
 * OpenClaw agent user (if set) or the first administrator.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * OpenClaw role and capability management.
 */
class OpenClaw_Role {

	/**
	 * Role slug.
	 */
	const ROLE_SLUG = 'openclaw_agent';

	/**
	 * Capability groups and their WordPress capabilities.
	 *
	 * @var array<string, string[]>
	 */
	const CAPABILITY_GROUPS = array(
		'content'    => array(
			'read',
			'edit_posts',
			'publish_posts',
			'edit_others_posts',
			'delete_posts',
			'delete_others_posts',
			'edit_pages',
			'publish_pages',
			'edit_others_pages',
			'delete_pages',
			'delete_others_pages',
		),
		'media'      => array(
			'upload_files',
		),
		'taxonomies' => array(
			'manage_categories',
		),
		'users'      => array(
			'list_users',
		),
		'comments'   => array(
			'moderate_comments',
		),
		'settings'   => array(
			'manage_options',
		),
		'plugins'    => array(
			'activate_plugins',
		),
		'themes'     => array(
			'switch_themes',
		),
		'menus'      => array(
			'edit_theme_options',
		),
		'cron'       => array(
			'manage_options', // list/manage cron requires elevated access.
		),
	);

	/**
	 * Default capability groups for new installs (content, media, comments).
	 *
	 * @var string[]
	 */
	const DEFAULT_GROUPS = array( 'content', 'media', 'taxonomies', 'users', 'comments' );

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'wp_pinch_activated', array( __CLASS__, 'ensure_role_exists' ) );
		add_action( 'update_option_wp_pinch_openclaw_capability_groups', array( __CLASS__, 'sync_role_on_capability_save' ), 10, 3 );
	}

	/**
	 * Sync the OpenClaw role when capability groups are saved.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $value     New option value.
	 * @param string $option   Option name.
	 */
	public static function sync_role_on_capability_save( $old_value, $value, $option ): void {
		if ( is_array( $value ) ) {
			self::update_role_capabilities( $value );
		}
	}

	/**
	 * Ensure the OpenClaw role exists, creating it if needed.
	 */
	public static function ensure_role_exists(): void {
		if ( get_role( self::ROLE_SLUG ) ) {
			return;
		}
		self::update_role_capabilities( self::DEFAULT_GROUPS );
	}

	/**
	 * Get the user ID to use for webhook/ability execution.
	 *
	 * Returns the designated OpenClaw agent user if set and valid, otherwise
	 * the first administrator.
	 *
	 * @return int User ID, or 0 if none found.
	 */
	public static function get_execution_user_id(): int {
		$user_id = (int) get_option( 'wp_pinch_openclaw_user_id', 0 );
		if ( $user_id > 0 ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user && user_can( $user, self::ROLE_SLUG ) ) {
				return $user_id;
			}
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		return ! empty( $admins ) ? (int) $admins[0] : 0;
	}

	/**
	 * Get all capability group slugs.
	 *
	 * @return string[]
	 */
	public static function get_capability_group_slugs(): array {
		return array_keys( self::CAPABILITY_GROUPS );
	}

	/**
	 * Get human-readable labels for capability groups.
	 *
	 * @return array<string, string>
	 */
	public static function get_capability_group_labels(): array {
		return array(
			'content'    => __( 'Content (posts, pages)', 'wp-pinch' ),
			'media'      => __( 'Media', 'wp-pinch' ),
			'taxonomies' => __( 'Taxonomies (categories, tags)', 'wp-pinch' ),
			'users'      => __( 'Users (list only)', 'wp-pinch' ),
			'comments'   => __( 'Comments (moderation)', 'wp-pinch' ),
			'settings'   => __( 'Settings (options allowlist)', 'wp-pinch' ),
			'plugins'    => __( 'Plugins', 'wp-pinch' ),
			'themes'     => __( 'Themes', 'wp-pinch' ),
			'menus'      => __( 'Menus', 'wp-pinch' ),
			'cron'       => __( 'Cron (advanced)', 'wp-pinch' ),
		);
	}

	/**
	 * Update the OpenClaw role with capabilities from the selected groups.
	 *
	 * @param string[] $group_slugs Slugs of capability groups to enable.
	 */
	public static function update_role_capabilities( array $group_slugs ): void {
		$all_caps = array();
		foreach ( $group_slugs as $slug ) {
			if ( isset( self::CAPABILITY_GROUPS[ $slug ] ) ) {
				foreach ( self::CAPABILITY_GROUPS[ $slug ] as $cap ) {
					$all_caps[ $cap ] = true;
				}
			}
		}

		$role = get_role( self::ROLE_SLUG );
		if ( ! $role ) {
			add_role(
				self::ROLE_SLUG,
				__( 'OpenClaw Agent', 'wp-pinch' ),
				$all_caps
			);
			return;
		}

		// Remove all group caps, then add selected ones.
		$all_group_caps = array();
		foreach ( array_values( self::CAPABILITY_GROUPS ) as $caps ) {
			foreach ( $caps as $cap ) {
				$all_group_caps[ $cap ] = true;
			}
		}
		foreach ( array_keys( $all_group_caps ) as $cap ) {
			$role->remove_cap( $cap );
		}
		foreach ( $all_caps as $cap => $grant ) {
			$role->add_cap( $cap, $grant );
		}
	}

	/**
	 * Create an OpenClaw agent user.
	 *
	 * Creates a user with the openclaw_agent role and returns the user ID and
	 * a one-time password. Caller should prompt the admin to create an
	 * application password for this user.
	 *
	 * @return array{user_id: int, password: string}|\WP_Error
	 */
	public static function create_agent_user() {
		$username = 'openclaw-agent';
		if ( username_exists( $username ) ) {
			return new \WP_Error(
				'user_exists',
				__( 'User "openclaw-agent" already exists. Assign the OpenClaw Agent role to that user in Users → All Users.', 'wp-pinch' )
			);
		}

		$password = wp_generate_password( 24, true, true );
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$host     = ( false !== $host && '' !== $host ) ? $host : 'localhost';
		$user_id  = wp_create_user(
			$username,
			$password,
			sprintf( 'openclaw-agent-%s@%s', wp_generate_password( 8, false, false ), $host )
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create user.', 'wp-pinch' ) );
		}

		$user->set_role( self::ROLE_SLUG );
		update_option( 'wp_pinch_openclaw_user_id', $user_id );

		/**
		 * Fires after an OpenClaw agent user is created.
		 *
		 * @since 2.5.0
		 * @param int $user_id The new user ID.
		 */
		do_action( 'wp_pinch_openclaw_agent_user_created', $user_id );

		return array(
			'user_id'  => $user_id,
			'password' => $password,
		);
	}

	/**
	 * Remove the OpenClaw role (e.g. on uninstall).
	 */
	public static function remove_role(): void {
		remove_role( self::ROLE_SLUG );
	}
}
