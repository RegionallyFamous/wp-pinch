<?php
/**
 * Plugin and theme lifecycle abilities with strict guardrails.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

/**
 * Install/update/delete extensions.
 */
class Extension_Lifecycle_Abilities {

	/**
	 * Register extension lifecycle abilities.
	 */
	public static function register(): void {
		Abilities::register_ability(
			'wp-pinch/manage-plugin-lifecycle',
			__( 'Manage Plugin Lifecycle', 'wp-pinch' ),
			__( 'Install, update, or delete a plugin with explicit confirmation for risky operations.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'action' ),
				'properties' => array(
					'action'  => array(
						'type' => 'string',
						'enum' => array( 'install', 'update', 'delete' ),
					),
					'slug'    => array(
						'type'        => 'string',
						'description' => 'Plugin slug for install.',
					),
					'plugin'  => array(
						'type'        => 'string',
						'description' => 'Plugin file path (e.g. akismet/akismet.php) for update/delete.',
					),
					'confirm' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Required true for update/delete.',
					),
				),
			),
			array( 'type' => 'object' ),
			'install_plugins',
			array( __CLASS__, 'execute_manage_plugin_lifecycle' )
		);

		Abilities::register_ability(
			'wp-pinch/manage-theme-lifecycle',
			__( 'Manage Theme Lifecycle', 'wp-pinch' ),
			__( 'Install, update, or delete a theme with explicit confirmation for risky operations.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'action' ),
				'properties' => array(
					'action'     => array(
						'type' => 'string',
						'enum' => array( 'install', 'update', 'delete' ),
					),
					'slug'       => array(
						'type'        => 'string',
						'description' => 'Theme slug for install.',
					),
					'stylesheet' => array(
						'type'        => 'string',
						'description' => 'Theme stylesheet for update/delete.',
					),
					'confirm'    => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Required true for update/delete.',
					),
				),
			),
			array( 'type' => 'object' ),
			'install_themes',
			array( __CLASS__, 'execute_manage_theme_lifecycle' )
		);
	}

	/**
	 * Manage plugin lifecycle.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_manage_plugin_lifecycle( array $input ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$action  = sanitize_key( (string) ( $input['action'] ?? '' ) );
		$confirm = ! empty( $input['confirm'] );

		if ( ! in_array( $action, array( 'install', 'update', 'delete' ), true ) ) {
			return array( 'error' => __( 'Invalid action.', 'wp-pinch' ) );
		}
		if ( in_array( $action, array( 'update', 'delete' ), true ) && ! $confirm ) {
			return array( 'error' => __( 'confirm=true is required for update/delete.', 'wp-pinch' ) );
		}
		$required_cap = self::plugin_action_capability( $action );
		if ( ! current_user_can( $required_cap ) ) {
			return array( 'error' => __( 'You do not have permission for this plugin action.', 'wp-pinch' ) );
		}

		if ( 'install' === $action ) {
			$slug = sanitize_key( (string) ( $input['slug'] ?? '' ) );
			if ( '' === $slug ) {
				return array( 'error' => __( 'slug is required for plugin install.', 'wp-pinch' ) );
			}

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);
			if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
				return array( 'error' => __( 'Could not fetch plugin package details.', 'wp-pinch' ) );
			}

			$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->install( (string) $api->download_link );

			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
			if ( true !== $result ) {
				return array( 'error' => __( 'Plugin install failed.', 'wp-pinch' ) );
			}

			Audit_Table::insert(
				'plugin_lifecycle',
				'ability',
				sprintf( 'Plugin "%s" installed.', $slug ),
				array(
					'action' => 'install',
					'slug'   => $slug,
				)
			);

			return array(
				'action'  => 'install',
				'slug'    => $slug,
				'success' => true,
			);
		}

		$plugin = sanitize_text_field( (string) ( $input['plugin'] ?? '' ) );
		if ( '' === $plugin || ! isset( get_plugins()[ $plugin ] ) ) {
			return array( 'error' => __( 'Valid plugin file is required for update/delete.', 'wp-pinch' ) );
		}

		if ( plugin_basename( WP_PINCH_FILE ) === $plugin && 'delete' === $action ) {
			return array( 'error' => __( 'WP Pinch cannot delete itself via an ability.', 'wp-pinch' ) );
		}

		if ( 'update' === $action ) {
			wp_update_plugins();
			$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->upgrade( $plugin );
			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
			if ( true !== $result ) {
				return array( 'error' => __( 'Plugin update failed.', 'wp-pinch' ) );
			}
		} else {
			if ( is_plugin_active( $plugin ) ) {
				deactivate_plugins( $plugin );
			}
			$result = delete_plugins( array( $plugin ) );
			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
			if ( true !== $result ) {
				return array( 'error' => __( 'Plugin delete failed.', 'wp-pinch' ) );
			}
		}

		Audit_Table::insert(
			'plugin_lifecycle',
			'ability',
			sprintf( 'Plugin "%s" %s.', $plugin, $action ),
			array(
				'action' => $action,
				'plugin' => $plugin,
			)
		);

		return array(
			'action'  => $action,
			'plugin'  => $plugin,
			'success' => true,
		);
	}

	/**
	 * Manage theme lifecycle.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_manage_theme_lifecycle( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$action  = sanitize_key( (string) ( $input['action'] ?? '' ) );
		$confirm = ! empty( $input['confirm'] );

		if ( ! in_array( $action, array( 'install', 'update', 'delete' ), true ) ) {
			return array( 'error' => __( 'Invalid action.', 'wp-pinch' ) );
		}
		if ( in_array( $action, array( 'update', 'delete' ), true ) && ! $confirm ) {
			return array( 'error' => __( 'confirm=true is required for update/delete.', 'wp-pinch' ) );
		}
		$required_cap = self::theme_action_capability( $action );
		if ( ! current_user_can( $required_cap ) ) {
			return array( 'error' => __( 'You do not have permission for this theme action.', 'wp-pinch' ) );
		}

		if ( 'install' === $action ) {
			$slug = sanitize_key( (string) ( $input['slug'] ?? '' ) );
			if ( '' === $slug ) {
				return array( 'error' => __( 'slug is required for theme install.', 'wp-pinch' ) );
			}

			$api = themes_api(
				'theme_information',
				array(
					'slug' => $slug,
				)
			);
			if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
				return array( 'error' => __( 'Could not fetch theme package details.', 'wp-pinch' ) );
			}

			$upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->install( (string) $api->download_link );

			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
			if ( true !== $result ) {
				return array( 'error' => __( 'Theme install failed.', 'wp-pinch' ) );
			}

			Audit_Table::insert(
				'theme_lifecycle',
				'ability',
				sprintf( 'Theme "%s" installed.', $slug ),
				array(
					'action' => 'install',
					'slug'   => $slug,
				)
			);

			return array(
				'action'  => 'install',
				'slug'    => $slug,
				'success' => true,
			);
		}

		$stylesheet = sanitize_text_field( (string) ( $input['stylesheet'] ?? '' ) );
		if ( '' === $stylesheet || ! wp_get_theme( $stylesheet )->exists() ) {
			return array( 'error' => __( 'Valid stylesheet is required for update/delete.', 'wp-pinch' ) );
		}

		if ( 'delete' === $action && get_stylesheet() === $stylesheet ) {
			return array( 'error' => __( 'Cannot delete the active theme.', 'wp-pinch' ) );
		}

		if ( 'update' === $action ) {
			wp_update_themes();
			$upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->upgrade( $stylesheet );
			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
			if ( true !== $result ) {
				return array( 'error' => __( 'Theme update failed.', 'wp-pinch' ) );
			}
		} else {
			$result = delete_theme( $stylesheet );
			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
			if ( ! $result ) {
				return array( 'error' => __( 'Theme delete failed.', 'wp-pinch' ) );
			}
		}

		Audit_Table::insert(
			'theme_lifecycle',
			'ability',
			sprintf( 'Theme "%s" %s.', $stylesheet, $action ),
			array(
				'action'     => $action,
				'stylesheet' => $stylesheet,
			)
		);

		return array(
			'action'     => $action,
			'stylesheet' => $stylesheet,
			'success'    => true,
		);
	}

	/**
	 * Resolve plugin capability by lifecycle action.
	 *
	 * @param string $action Action name.
	 * @return string
	 */
	private static function plugin_action_capability( string $action ): string {
		if ( 'update' === $action ) {
			return 'update_plugins';
		}
		if ( 'delete' === $action ) {
			return 'delete_plugins';
		}
		return 'install_plugins';
	}

	/**
	 * Resolve theme capability by lifecycle action.
	 *
	 * @param string $action Action name.
	 * @return string
	 */
	private static function theme_action_capability( string $action ): string {
		if ( 'update' === $action ) {
			return 'update_themes';
		}
		if ( 'delete' === $action ) {
			return 'delete_themes';
		}
		return 'install_themes';
	}
}
