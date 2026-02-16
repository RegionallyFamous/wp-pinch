<?php
/**
 * REST response JSON schemas.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Schema definitions for REST endpoints.
 */
class Schemas {

	/**
	 * Schema for the /chat endpoint.
	 *
	 * @return array<string, mixed> JSON Schema.
	 */
	public static function get_chat_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wp-pinch-chat',
			'type'       => 'object',
			'properties' => array(
				'reply'       => array(
					'description' => __( 'The AI assistant reply text.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'session_key' => array(
					'description' => __( 'Session key for conversation continuity.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Schema for the /status endpoint.
	 *
	 * @return array<string, mixed> JSON Schema.
	 */
	public static function get_status_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wp-pinch-status',
			'type'       => 'object',
			'properties' => array(
				'plugin_version' => array(
					'description' => __( 'Current WP Pinch plugin version.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'configured'     => array(
					'description' => __( 'Whether the plugin is configured with gateway URL and API token.', 'wp-pinch' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'mcp_endpoint'   => array(
					'description' => __( 'The MCP server REST endpoint URL.', 'wp-pinch' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'gateway'        => array(
					'description' => __( 'Gateway connection status.', 'wp-pinch' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'connected' => array( 'type' => 'boolean' ),
						'status'    => array( 'type' => 'integer' ),
					),
				),
			),
		);
	}

	/**
	 * Schema for the /health endpoint.
	 *
	 * @return array<string, mixed> JSON Schema.
	 */
	public static function get_health_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wp-pinch-health',
			'type'       => 'object',
			'properties' => array(
				'status'     => array(
					'description' => __( 'Health status (ok).', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'version'    => array(
					'description' => __( 'Plugin version.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'configured' => array(
					'description' => __( 'Whether gateway URL and token are configured.', 'wp-pinch' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'rate_limit' => array(
					'description' => __( 'Rate limit config (requests per minute).', 'wp-pinch' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'limit' => array( 'type' => 'integer' ),
					),
				),
				'circuit'    => array(
					'description' => __( 'Circuit breaker state.', 'wp-pinch' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'state'           => array( 'type' => 'string' ),
						'retry_after'     => array( 'type' => 'integer' ),
						'last_failure_at' => array(
							'type'   => array( 'string', 'null' ),
							'format' => 'date-time',
						),
					),
				),
				'timestamp'  => array(
					'description' => __( 'ISO 8601 timestamp.', 'wp-pinch' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
			),
		);
	}
}
