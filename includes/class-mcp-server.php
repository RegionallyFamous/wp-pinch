<?php
/**
 * Custom MCP server registration.
 *
 * Registers a dedicated "wp-pinch" MCP server via the MCP Adapter so that
 * OpenClaw (or any MCP client) gets a curated set of abilities instead of
 * seeing every ability registered by every plugin.
 *
 * Also ensures core abilities are exposed on the default server by setting
 * meta.mcp.public = true.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * MCP server integration.
 */
class MCP_Server {

	/**
	 * Core abilities to expose on the default MCP server.
	 *
	 * @var string[]
	 */
	/**
	 * Core abilities to include on the WP Pinch MCP server.
	 *
	 * Note: get-user-info and get-environment-info are intentionally excluded
	 * from the public list to prevent leaking sensitive server/user details.
	 */
	const CORE_ABILITIES = array(
		'core/get-site-info',
	);

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'mcp_adapter_init', array( __CLASS__, 'register_server' ) );
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'expose_core_abilities' ), 10, 2 );
	}

	/**
	 * Register the wp-pinch custom MCP server.
	 *
	 * The MCP Adapter's create_server method accepts the list of ability
	 * names to expose on this server. We merge our plugin abilities with
	 * core abilities so the agent gets everything it needs from one endpoint.
	 *
	 * @param object $adapter The MCP Adapter instance.
	 */
	public static function register_server( $adapter ): void {
		if ( ! method_exists( $adapter, 'create_server' ) ) {
			return;
		}

		$plugin_abilities = wp_pinch_get_ability_names();
		$all_abilities    = array_merge( self::CORE_ABILITIES, $plugin_abilities );

		/**
		 * Filter the abilities exposed on the WP Pinch MCP server.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $all_abilities Ability names to register.
		 */
		$all_abilities = apply_filters( 'wp_pinch_mcp_server_abilities', $all_abilities );

		// Determine transport classes.
		$http_transport = '\\WP\\MCP\\Transport\\HttpTransport';
		$transports     = class_exists( $http_transport ) ? array( $http_transport ) : array();

		// Error handler and observability.
		$error_handler = '\\WP\\MCP\\Infrastructure\\ErrorHandling\\ErrorLogMcpErrorHandler';
		$observability = '\\WP\\MCP\\Infrastructure\\Observability\\NullMcpObservabilityHandler';

		try {
			$adapter->create_server(
				'wp-pinch',                                           // Server ID.
				'wp-pinch',                                           // REST namespace.
				'mcp',                                                // Route.
				__( 'WP Pinch MCP Server', 'wp-pinch' ),              // Name.
				__( 'Full WordPress site management for OpenClaw and AI agents.', 'wp-pinch' ), // Description.
				WP_PINCH_VERSION,                                     // Version.
				$transports,                                          // Transports.
				class_exists( $error_handler ) ? $error_handler : null,
				class_exists( $observability ) ? $observability : null,
				$all_abilities                                        // Abilities.
			);
		} catch ( \Throwable $e ) {
			Audit_Table::insert(
				'mcp_server_error',
				'mcp',
				sprintf( 'Failed to register custom MCP server: %s', $e->getMessage() )
			);
		}
	}

	/**
	 * Ensure core abilities are public on the default MCP Adapter server.
	 *
	 * Sets meta.mcp.public = true for core abilities so they appear on
	 * the default /wp-json/mcp/mcp-adapter-default-server endpoint.
	 *
	 * @param array  $args Ability registration arguments.
	 * @param string $name Ability name.
	 * @return array Modified args.
	 */
	public static function expose_core_abilities( array $args, string $name ): array {
		if ( in_array( $name, self::CORE_ABILITIES, true ) ) {
			if ( ! isset( $args['meta'] ) ) {
				$args['meta'] = array();
			}
			if ( ! isset( $args['meta']['mcp'] ) ) {
				$args['meta']['mcp'] = array();
			}
			$args['meta']['mcp']['public'] = true;
		}

		return $args;
	}
}
