<?php
/**
 * WP-CLI: wp pinch features
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Feature flags command — list, get, enable, disable.
 */
class Features_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch features', array( __CLASS__, 'run' ) );
	}

	/**
	 * List, get, enable, or disable feature flags.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : "list", "get", "enable", or "disable".
	 *
	 * [<flag>]
	 * : Feature flag name (for get, enable, disable).
	 *
	 * [--format=<format>]
	 * : Output format for list.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		$subcommand = $args[0] ?? 'list';

		if ( 'list' === $subcommand ) {
			$flags  = \WP_Pinch\Feature_Flags::get_all();
			$format = $assoc_args['format'] ?? 'table';

			$rows = array();
			foreach ( $flags as $flag => $enabled ) {
				$rows[] = array(
					'Flag'    => $flag,
					'Enabled' => $enabled ? 'Yes' : 'No',
				);
			}
			\WP_CLI\Utils\format_items( $format, $rows, array( 'Flag', 'Enabled' ) );
			return;
		}

		if ( in_array( $subcommand, array( 'get', 'enable', 'disable' ), true ) ) {
			$flag = $args[1] ?? '';
			if ( '' === $flag ) {
				\WP_CLI::error( 'Specify a feature flag name.' );
			}

			$all = \WP_Pinch\Feature_Flags::get_all();
			if ( ! array_key_exists( $flag, $all ) ) {
				\WP_CLI::error( "Unknown flag: {$flag}. Use \"wp pinch features list\" to see available flags." );
			}

			if ( 'get' === $subcommand ) {
				$enabled = \WP_Pinch\Feature_Flags::is_enabled( $flag );
				\WP_CLI::log( $enabled ? 'enabled' : 'disabled' );
				return;
			}

			if ( 'enable' === $subcommand ) {
				\WP_Pinch\Feature_Flags::enable( $flag );
				\WP_CLI::success( "Feature \"{$flag}\" enabled." );
				return;
			}

			// Subcommand is 'disable' at this point.
			\WP_Pinch\Feature_Flags::disable( $flag );
			\WP_CLI::success( "Feature \"{$flag}\" disabled." );
			return;
		}

		\WP_CLI::error( 'Unknown subcommand. Use "list", "get", "enable", or "disable".' );
	}
}
