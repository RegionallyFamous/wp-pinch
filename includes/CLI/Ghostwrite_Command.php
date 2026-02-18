<?php
/**
 * WP-CLI: wp pinch ghostwrite
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Ghostwrite command — list abandoned drafts or resurrect one.
 */
class Ghostwrite_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch ghostwrite', array( __CLASS__, 'run' ) );
	}

	/**
	 * List abandoned drafts or run ghostwrite on a post.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : "list" or "run".
	 *
	 * [<post_id>]
	 * : Post ID for "run" (draft to resurrect).
	 *
	 * [--format=<format>]
	 * : Output format for list.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		if ( ! \WP_Pinch\Feature_Flags::is_enabled( 'ghost_writer' ) ) {
			\WP_CLI::error( 'Ghost Writer is disabled. Enable the "ghost_writer" feature flag first.' );
		}

		$subcommand = $args[0] ?? 'list';

		if ( 'list' === $subcommand ) {
			$result = \WP_Pinch\Abilities::execute_list_abandoned_drafts( array() );
			if ( empty( $result['drafts'] ) ) {
				\WP_CLI::log( 'No abandoned drafts found.' );
				return;
			}

			$format = $assoc_args['format'] ?? 'table';
			$rows   = array();
			foreach ( $result['drafts'] as $d ) {
				$rows[] = array(
					'ID'       => $d['post_id'] ?? '',
					'Title'    => $d['title'] ?? '',
					'Author'   => $d['author'] ?? '',
					'Modified' => $d['last_modified'] ?? '',
				);
			}
			\WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Title', 'Author', 'Modified' ) );
			return;
		}

		if ( 'run' === $subcommand ) {
			$post_id = isset( $args[1] ) ? absint( $args[1] ) : 0;
			if ( $post_id < 1 ) {
				\WP_CLI::error( 'Provide a post ID. Example: wp pinch ghostwrite run 123' );
			}

			$result = \WP_Pinch\Ghost_Writer::ghostwrite( $post_id, true );
			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}
			\WP_CLI::success( 'Ghostwrite completed for post #' . $post_id . '.' );
			return;
		}

		\WP_CLI::error( 'Unknown subcommand. Use "list" or "run".' );
	}
}
