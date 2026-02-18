<?php
/**
 * WP-CLI: wp pinch cache
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Cache command — flush ability cache.
 */
class Cache_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch cache', array( __CLASS__, 'run' ) );
	}

	/**
	 * Flush ability cache.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : "flush".
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		$subcommand = $args[0] ?? '';

		if ( 'flush' !== $subcommand ) {
			\WP_CLI::error( 'Unknown subcommand. Use "flush".' );
		}

		\WP_Pinch\Abilities::invalidate_ability_cache();
		\WP_CLI::success( 'Ability cache invalidated.' );
	}
}
