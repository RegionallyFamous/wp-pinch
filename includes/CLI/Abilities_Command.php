<?php
/**
 * WP-CLI: wp pinch abilities
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Abilities list command.
 */
class Abilities_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch abilities', array( __CLASS__, 'run' ) );
	}

	/**
	 * List registered abilities.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : "list".
	 *
	 * [--format=<format>]
	 * : Output format.
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

		if ( 'list' !== $subcommand ) {
			\WP_CLI::error( 'Unknown subcommand. Use "list".' );
		}

		$format   = $assoc_args['format'] ?? 'table';
		$names    = \WP_Pinch\Abilities::get_ability_names();
		$disabled = get_option( 'wp_pinch_disabled_abilities', array() );
		if ( ! is_array( $disabled ) ) {
			$disabled = array();
		}

		$rows = array();
		foreach ( $names as $name ) {
			$parts  = explode( '/', $name );
			$rows[] = array(
				'Name'     => $name,
				'Category' => $parts[0] ?? '',
				'Action'   => $parts[1] ?? '',
				'Status'   => in_array( $name, $disabled, true ) ? 'disabled' : 'enabled',
			);
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'Name', 'Category', 'Action', 'Status' ) );

		if ( 'table' === $format ) {
			$enabled_count  = count(
				array_filter(
					$rows,
					function ( $r ) {
						return 'enabled' === $r['Status'];
					}
				)
			);
			$disabled_count = count( $rows ) - $enabled_count;
			\WP_CLI::log( sprintf( '%d abilities (%d enabled, %d disabled).', count( $rows ), $enabled_count, $disabled_count ) );
		}
	}
}
