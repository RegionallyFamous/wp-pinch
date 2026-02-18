<?php
/**
 * WP-CLI: wp pinch webhook-test
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Webhook test command.
 */
class Webhook_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch webhook-test', array( __CLASS__, 'run' ) );
	}

	/**
	 * Send a test webhook.
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
	public static function run( array $args, array $assoc_args ): void {
		$message = $assoc_args['message'] ?? 'This is a test webhook from WP Pinch.';

		\WP_CLI::log( 'Sending test webhook...' );

		$result = \WP_Pinch\Webhook_Dispatcher::dispatch(
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
}
