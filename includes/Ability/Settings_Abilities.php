<?php
/**
 * Settings abilities — get/update option, list/toggle plugins, list/switch themes.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Settings (options, plugins, themes) abilities.
 */
class Settings_Abilities {

	/**
	 * Register settings abilities.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Abilities::register_ability(
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

		Abilities::register_ability(
			'wp-pinch/update-option',
			__( 'Update Option', 'wp-pinch' ),
			__( 'Update an allowed WordPress option.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'key', 'value' ),
				'properties' => array(
					'key'   => array( 'type' => 'string' ),
					'value' => array( 'type' => 'string' ),
				),
			),
			array(
				'type'       => 'object',
				'properties' => array(
					'key'     => array( 'type' => 'string' ),
					'updated' => array( 'type' => 'boolean' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'manage_options',
			array( __CLASS__, 'execute_update_option' )
		);

		Abilities::register_ability(
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

		Abilities::register_ability(
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

		Abilities::register_ability(
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

		Abilities::register_ability(
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
	}

	/**
	 * Get an option value.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_get_option( array $input ): array {
		$key = sanitize_text_field( (string) ( $input['key'] ?? '' ) );

		if ( in_array( $key, Abilities::OPTION_DENYLIST, true ) ) {
			return array( 'error' => __( 'This option cannot be read via abilities.', 'wp-pinch' ) );
		}

		/** @var string[] $allowlist */
		$allowlist = apply_filters(
			'wp_pinch_option_read_allowlist',
			array_merge(
				Abilities::OPTION_ALLOWLIST,
				array( 'WPLANG', 'permalink_structure' )
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
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_update_option( array $input ): array {
		$key = sanitize_text_field( (string) ( $input['key'] ?? '' ) );

		if ( in_array( $key, Abilities::OPTION_DENYLIST, true ) ) {
			return array( 'error' => __( 'This option cannot be modified via abilities.', 'wp-pinch' ) );
		}

		/** @var string[] $allowlist */
		$allowlist = apply_filters( 'wp_pinch_option_write_allowlist', Abilities::OPTION_ALLOWLIST );

		if ( ! in_array( $key, $allowlist, true ) ) {
			return array( 'error' => __( 'Option key not in allowlist.', 'wp-pinch' ) );
		}

		$old = get_option( $key );
		update_option( $key, sanitize_text_field( (string) ( $input['value'] ?? '' ) ) );

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

	/**
	 * List installed plugins.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_plugins( array $input ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$status_filter  = sanitize_key( (string) ( $input['status'] ?? 'all' ) );
		$plugins        = array();

		foreach ( $all_plugins as $file => $data ) {
			$is_active = in_array( $file, (array) $active_plugins, true );

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
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_toggle_plugin( array $input ): array {
		$plugin   = sanitize_text_field( (string) ( $input['plugin'] ?? '' ) );
		$activate = ! empty( $input['activate'] );

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
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
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
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_switch_theme( array $input ): array {
		$stylesheet = sanitize_text_field( (string) ( $input['stylesheet'] ?? '' ) );
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
}
