<?php
/**
 * WP-CLI: wp pinch molt
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Molt command — repackage a post into multiple formats.
 */
class Molt_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch molt', array( __CLASS__, 'run' ) );
	}

	/**
	 * Run Molt on a post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID to repackage.
	 *
	 * [--output-types=<types>]
	 * : Comma-separated output types (e.g. summary,meta_description,social). Omit for all.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml).
	 * ---
	 * default: json
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		if ( ! \WP_Pinch\Feature_Flags::is_enabled( 'molt' ) ) {
			\WP_CLI::error( 'Molt is disabled. Enable the "molt" feature flag first.' );
		}

		$post_id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( $post_id < 1 ) {
			\WP_CLI::error( 'Provide a valid post ID.' );
		}

		$types_str    = $assoc_args['output_types'] ?? '';
		$output_types = array();
		if ( '' !== $types_str ) {
			$output_types = array_map( 'trim', array_filter( explode( ',', $types_str ) ) );
		}

		$result = \WP_Pinch\Molt::molt( $post_id, $output_types );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		$format = $assoc_args['format'] ?? 'json';
		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			\WP_CLI\Utils\format_items( $format, array( $result ), array_keys( $result ) );
		}
		\WP_CLI::success( 'Molt completed for post #' . $post_id . '.' );
	}
}
