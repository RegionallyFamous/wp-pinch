<?php
/**
 * WP-CLI commands for WP Pinch.
 *
 * Commands:
 *   wp pinch status          — Check OpenClaw gateway connection.
 *   wp pinch webhook-test    — Send a test webhook.
 *   wp pinch governance run  — Run a governance task on-demand.
 *   wp pinch audit list      — Show recent audit log entries.
 *   wp pinch abilities list  — List registered abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage WP Pinch — OpenClaw integration for WordPress.
 */
class CLI {

	/**
	 * Register commands.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch status', array( __CLASS__, 'status' ) );
		\WP_CLI::add_command( 'pinch webhook-test', array( __CLASS__, 'webhook_test' ) );
		\WP_CLI::add_command( 'pinch governance', array( __CLASS__, 'governance' ) );
		\WP_CLI::add_command( 'pinch audit', array( __CLASS__, 'audit' ) );
		\WP_CLI::add_command( 'pinch abilities', array( __CLASS__, 'abilities' ) );
	}

	/**
	 * Check the OpenClaw gateway connection.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pinch status
	 *     wp pinch status --format=json
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
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
	public static function status( array $args, array $assoc_args ): void {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = get_option( 'wp_pinch_api_token', '' );
		$format      = $assoc_args['format'] ?? 'table';

		$connected = false;
		$http_code = 0;
		$error_msg = '';

		if ( ! empty( $gateway_url ) && ! empty( $api_token ) ) {
			$response = wp_remote_get(
				trailingslashit( $gateway_url ) . 'api/v1/status',
				array(
					'timeout' => 10,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_token,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_msg = $response->get_error_message();
			} else {
				$http_code = wp_remote_retrieve_response_code( $response );
				$connected = ( $http_code >= 200 && $http_code < 300 );
			}
		}

		$circuit_state = Circuit_Breaker::get_state();

		$data = array(
			array(
				'Field' => 'Plugin Version',
				'Value' => WP_PINCH_VERSION,
			),
			array(
				'Field' => 'Gateway URL',
				'Value' => $gateway_url ? $gateway_url : '(not set)',
			),
			array(
				'Field' => 'API Token',
				'Value' => ! empty( $api_token ) ? '***configured***' : 'NOT SET',
			),
			array(
				'Field' => 'MCP Endpoint',
				'Value' => rest_url( 'wp-pinch/mcp' ),
			),
			array(
				'Field' => 'Gateway Connected',
				'Value' => $connected ? 'Yes' : 'No',
			),
			array(
				'Field' => 'Gateway HTTP Code',
				'Value' => $http_code ? $http_code : '-',
			),
			array(
				'Field' => 'Circuit Breaker',
				'Value' => $circuit_state,
			),
		);

		if ( $error_msg ) {
			$data[] = array(
				'Field' => 'Connection Error',
				'Value' => $error_msg,
			);
		}

		if ( 'json' === $format ) {
			// For JSON, output as key-value object.
			$json = array();
			foreach ( $data as $row ) {
				$key          = sanitize_title( $row['Field'] );
				$json[ $key ] = $row['Value'];
			}
			\WP_CLI::line( wp_json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			\WP_CLI\Utils\format_items( $format, $data, array( 'Field', 'Value' ) );
		}

		if ( 'table' === $format ) {
			if ( $connected ) {
				\WP_CLI::success( "Connected to OpenClaw gateway (HTTP {$http_code})." );
			} elseif ( empty( $gateway_url ) ) {
				\WP_CLI::warning( 'Gateway URL is not configured.' );
			} else {
				\WP_CLI::warning( 'Connection failed.' );
			}
		}
	}

	/**
	 * Send a test webhook to the OpenClaw gateway.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pinch webhook-test
	 *     wp pinch webhook-test --message="Hello from WP-CLI"
	 *
	 * ## OPTIONS
	 *
	 * [--message=<message>]
	 * : Custom test message.
	 * ---
	 * default: This is a test webhook from WP Pinch.
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function webhook_test( array $args, array $assoc_args ): void {
		$message = $assoc_args['message'] ?? 'This is a test webhook from WP Pinch.';

		\WP_CLI::log( 'Sending test webhook...' );

		$result = Webhook_Dispatcher::dispatch(
			'test',
			$message,
			array(
				'source'    => 'wp-cli',
				'timestamp' => gmdate( 'c' ),
			)
		);

		if ( $result ) {
			\WP_CLI::success( 'Test webhook sent successfully.' );
		} else {
			\WP_CLI::warning( 'Webhook dispatch returned false. Check settings and audit log.' );
		}
	}

	/**
	 * Run a governance task on-demand.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pinch governance run content_freshness
	 *     wp pinch governance run seo_health
	 *     wp pinch governance run --all
	 *     wp pinch governance list
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
	public static function governance( array $args, array $assoc_args ): void {
		$subcommand = $args[0] ?? 'list';

		if ( 'list' === $subcommand ) {
			$tasks   = Governance::get_available_tasks();
			$enabled = Governance::get_enabled_tasks();
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
			$tasks = array_keys( Governance::get_available_tasks() );
		} else {
			$task = $args[1] ?? '';
			if ( empty( $task ) ) {
				\WP_CLI::error( 'Specify a task name or use --all.' );
			}
			$tasks = array( $task );
		}

		$available = array_keys( Governance::get_available_tasks() );

		foreach ( $tasks as $task ) {
			if ( ! in_array( $task, $available, true ) ) {
				\WP_CLI::warning( "Unknown task: {$task}. Skipping." );
				continue;
			}

			\WP_CLI::log( "Running governance task: {$task}..." );

			$method = 'task_' . $task;
			if ( method_exists( Governance::class, $method ) ) {
				Governance::$method();
				\WP_CLI::success( "Task '{$task}' completed." );
			} else {
				\WP_CLI::warning( "Method not found for task: {$task}." );
			}
		}
	}

	/**
	 * Show audit log entries.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pinch audit list
	 *     wp pinch audit list --event_type=webhook_sent
	 *     wp pinch audit list --per_page=50 --page=2
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
	public static function audit( array $args, array $assoc_args ): void {
		$subcommand = $args[0] ?? 'list';

		if ( 'list' !== $subcommand ) {
			\WP_CLI::error( 'Unknown subcommand. Use "list".' );
		}

		$result = Audit_Table::query(
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

		// Simplify context for table display.
		$items = array_map(
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

	/**
	 * List registered WP Pinch abilities.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pinch abilities list
	 *     wp pinch abilities list --format=json
	 *     wp pinch abilities list --format=csv
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
	public static function abilities( array $args, array $assoc_args ): void {
		$subcommand = $args[0] ?? 'list';

		if ( 'list' !== $subcommand ) {
			\WP_CLI::error( 'Unknown subcommand. Use "list".' );
		}

		$format   = $assoc_args['format'] ?? 'table';
		$names    = Abilities::get_ability_names();
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

// Register commands.
CLI::register();
