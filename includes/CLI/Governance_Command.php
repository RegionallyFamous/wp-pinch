<?php
/**
 * WP-CLI: wp pinch governance
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Governance command — run tasks or list.
 */
class Governance_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch governance', array( __CLASS__, 'run' ) );
	}

	/**
	 * Run a governance task or list tasks.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : "run" or "list".
	 *
	 * [<task>]
	 * : The governance task to run.
	 *
	 * [--all]
	 * : Run all governance tasks.
	 *
	 * [--format=<format>]
	 * : Output format for "list".
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
			$tasks   = \WP_Pinch\Governance::get_available_tasks();
			$enabled = \WP_Pinch\Governance::get_enabled_tasks();
			$format  = $assoc_args['format'] ?? 'table';

			$rows = array();
			foreach ( $tasks as $key => $label ) {
				$rows[] = array(
					'Task'    => $key,
					'Label'   => $label,
					'Enabled' => in_array( $key, $enabled, true ) ? 'Yes' : 'No',
				);
			}
			\WP_CLI\Utils\format_items( $format, $rows, array( 'Task', 'Label', 'Enabled' ) );
			return;
		}

		if ( 'run' !== $subcommand ) {
			\WP_CLI::error( 'Unknown subcommand. Use "run" or "list".' );
		}

		$run_all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( $run_all ) {
			$tasks = array_keys( \WP_Pinch\Governance::get_available_tasks() );
		} else {
			$task = $args[1] ?? '';
			if ( empty( $task ) ) {
				\WP_CLI::error( 'Specify a task name or use --all.' );
			}
			$tasks = array( $task );
		}

		$available = array_keys( \WP_Pinch\Governance::get_available_tasks() );

		foreach ( $tasks as $task ) {
			if ( ! in_array( $task, $available, true ) ) {
				\WP_CLI::warning( "Unknown task: {$task}. Skipping." );
				continue;
			}
			\WP_CLI::log( "Running governance task: {$task}..." );

			$method = 'task_' . $task;
			if ( method_exists( \WP_Pinch\Governance::class, $method ) ) {
				\WP_Pinch\Governance::$method();
				\WP_CLI::success( "Task '{$task}' completed." );
			} else {
				\WP_CLI::warning( "Method not found for task: {$task}." );
			}
		}
	}
}
