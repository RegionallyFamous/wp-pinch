<?php
/**
 * WP-CLI: wp pinch status
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Status command — check OpenClaw gateway connection.
 */
class Status_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch status', array( __CLASS__, 'run' ) );
	}

	/**
	 * Check the OpenClaw gateway connection.
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
	public static function run( array $args, array $assoc_args ): void {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = \WP_Pinch\Settings::get_api_token();
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

		$circuit_state = \WP_Pinch\Circuit_Breaker::get_state();

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
			$json = array();
			foreach ( $data as $row ) {
				$json[ sanitize_title( $row['Field'] ) ] = $row['Value'];
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
}
