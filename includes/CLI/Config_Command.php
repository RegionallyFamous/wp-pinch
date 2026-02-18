<?php
/**
 * WP-CLI: wp pinch config
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Config command — get or set key options.
 */
class Config_Command {

	/**
	 * Option keys allowed for get/set (safe, non-secret).
	 *
	 * @var string[]
	 */
	const ALLOWED_OPTIONS = array(
		'wp_pinch_gateway_url',
		'wp_pinch_session_idle_minutes',
		'wp_pinch_ability_cache_ttl',
	);

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch config', array( __CLASS__, 'run' ) );
	}

	/**
	 * Get or set WP Pinch config options.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : "get" or "set".
	 *
	 * [<key>]
	 * : Option name (e.g. wp_pinch_gateway_url).
	 *
	 * [<value>]
	 * : Value for "set".
	 *
	 * [--format=<format>]
	 * : Output format for "get" (table, json, yaml).
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		$subcommand = $args[0] ?? '';

		if ( 'get' === $subcommand ) {
			$key    = $args[1] ?? '';
			$format = $assoc_args['format'] ?? 'table';

			if ( '' === $key ) {
				$data = array();
				foreach ( self::ALLOWED_OPTIONS as $opt ) {
					$val = get_option( $opt, '' );
					if ( 'wp_pinch_gateway_url' === $opt && $val ) {
						$data[] = array(
							'Key'   => $opt,
							'Value' => $val,
						);
					} else {
						$data[] = array(
							'Key'   => $opt,
							'Value' => (string) $val,
						);
					}
				}
				\WP_CLI\Utils\format_items( $format, $data, array( 'Key', 'Value' ) );
				return;
			}

			if ( ! in_array( $key, self::ALLOWED_OPTIONS, true ) ) {
				\WP_CLI::error( "Unknown or disallowed option: {$key}. Allowed: " . implode( ', ', self::ALLOWED_OPTIONS ) );
			}

			$value = get_option( $key, '' );
			if ( 'json' === $format ) {
				\WP_CLI::line( wp_json_encode( array( $key => $value ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			} else {
				\WP_CLI::log( (string) $value );
			}
			return;
		}

		if ( 'set' === $subcommand ) {
			$key   = $args[1] ?? '';
			$value = $args[2] ?? '';

			if ( '' === $key || '' === $value ) {
				\WP_CLI::error( 'Usage: wp pinch config set <key> <value>' );
			}

			if ( ! in_array( $key, self::ALLOWED_OPTIONS, true ) ) {
				\WP_CLI::error( "Unknown or disallowed option: {$key}." );
			}

			if ( 'wp_pinch_ability_cache_ttl' === $key || 'wp_pinch_session_idle_minutes' === $key ) {
				$value = absint( $value );
			}
			update_option( $key, $value );
			\WP_CLI::success( "Updated {$key}." );
			return;
		}

		\WP_CLI::error( 'Unknown subcommand. Use "get" or "set".' );
	}
}
