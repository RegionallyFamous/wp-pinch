<?php
/**
 * WP-CLI bootstrap for WP Pinch.
 *
 * Commands are implemented in includes/CLI/*.php to keep files small.
 *
 * Available commands:
 *   wp pinch status          — Check OpenClaw gateway connection.
 *   wp pinch webhook-test    — Send a test webhook.
 *   wp pinch governance      — Run tasks or list (run, list).
 *   wp pinch audit           — Audit log (list).
 *   wp pinch abilities       — List abilities (list).
 *   wp pinch features        — Feature flags (list, get, enable, disable).
 *   wp pinch config          — Get/set options (get, set).
 *   wp pinch molt            — Repackage a post (requires molt flag).
 *   wp pinch ghostwrite      — Abandoned drafts (list, run) (requires ghost_writer flag).
 *   wp pinch cache           — Flush ability cache (flush).
 *   wp pinch approvals       — Approval queue (list, approve, reject) (requires approval_workflow flag).
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Register all WP-CLI commands.
 */
class CLI {

	/**
	 * CLI command files (one file per command group to keep size down).
	 *
	 * @var string[]
	 */
	private static $command_files = array(
		'Status_Command.php',
		'Webhook_Command.php',
		'Governance_Command.php',
		'Audit_Command.php',
		'Abilities_Command.php',
		'Features_Command.php',
		'Config_Command.php',
		'Molt_Command.php',
		'Ghostwrite_Command.php',
		'Cache_Command.php',
		'Approvals_Command.php',
	);

	/**
	 * Register commands.
	 */
	public static function register(): void {
		$cli_dir = WP_PINCH_DIR . 'includes/CLI/';
		foreach ( self::$command_files as $file ) {
			$path = $cli_dir . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}

		$commands = array(
			\WP_Pinch\CLI\Status_Command::class,
			\WP_Pinch\CLI\Webhook_Command::class,
			\WP_Pinch\CLI\Governance_Command::class,
			\WP_Pinch\CLI\Audit_Command::class,
			\WP_Pinch\CLI\Abilities_Command::class,
			\WP_Pinch\CLI\Features_Command::class,
			\WP_Pinch\CLI\Config_Command::class,
			\WP_Pinch\CLI\Molt_Command::class,
			\WP_Pinch\CLI\Ghostwrite_Command::class,
			\WP_Pinch\CLI\Cache_Command::class,
			\WP_Pinch\CLI\Approvals_Command::class,
		);

		foreach ( $commands as $class ) {
			if ( method_exists( $class, 'register' ) ) {
				$class::register();
			}
		}
	}
}

if ( did_action( 'init' ) ) {
	CLI::register();
} else {
	add_action( 'init', array( CLI::class, 'register' ) );
}
