<?php
/**
 * WP-CLI: wp pinch audit
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Audit log command.
 */
class Audit_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch audit', array( __CLASS__, 'run' ) );
	}

	/**
	 * Show audit log entries.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : "list".
	 *
	 * [--event_type=<type>]
	 * : Filter by event type.
	 *
	 * [--source=<source>]
	 * : Filter by source.
	 *
	 * [--per_page=<number>]
	 * : Results per page.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--page=<number>]
	 * : Page number.
	 * ---
	 * default: 1
	 * ---
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

		$result = \WP_Pinch\Audit_Table::query(
			array(
				'event_type' => $assoc_args['event_type'] ?? '',
				'source'     => $assoc_args['source'] ?? '',
				'per_page'   => absint( $assoc_args['per_page'] ?? 20 ),
				'page'       => absint( $assoc_args['page'] ?? 1 ),
			)
		);

		if ( empty( $result['items'] ) ) {
			\WP_CLI::log( 'No audit log entries found.' );
			return;
		}

		$format = $assoc_args['format'] ?? 'table';
		$items  = array_map(
			function ( $item ) {
				unset( $item['context'] );
				return $item;
			},
			$result['items']
		);

		\WP_CLI\Utils\format_items(
			$format,
			$items,
			array( 'id', 'event_type', 'source', 'message', 'created_at' )
		);
		\WP_CLI::log( sprintf( 'Total: %d entries', $result['total'] ) );
	}
}
