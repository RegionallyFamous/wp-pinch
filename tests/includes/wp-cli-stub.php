<?php
/**
 * WP-CLI stub for unit tests when not running inside WP-CLI.
 *
 * Defines WP_CLI constant and a minimal WP_CLI class so includes/class-cli.php
 * can load and register commands without the real WP-CLI.
 *
 * @package WP_Pinch
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Stub for WP-CLI.
	class WP_CLI {
		public static function add_command( $name, $callback ) {}
	}
}
if ( ! defined( 'WP_CLI' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Stub for WP-CLI constant.
	define( 'WP_CLI', true );
}
