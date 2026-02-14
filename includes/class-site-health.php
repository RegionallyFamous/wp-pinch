<?php
/**
 * Site Health integration — debug info section and custom status tests.
 *
 * Adds a "WP Pinch" section to Tools → Site Health → Info and registers
 * custom status tests for gateway connectivity and Action Scheduler health.
 *
 * @package WP_Pinch
 * @since   1.1.0
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Site Health debug info and tests.
 */
class Site_Health {

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_filter( 'debug_information', array( __CLASS__, 'add_debug_info' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'register_tests' ) );
	}

	// =========================================================================
	// Debug Information (Tools → Site Health → Info)
	// =========================================================================

	/**
	 * Add WP Pinch section to the Site Health debug info page.
	 *
	 * @param array $info Existing debug info sections.
	 * @return array
	 */
	public static function add_debug_info( array $info ): array {
		$gateway_url      = get_option( 'wp_pinch_gateway_url', '' );
		$api_token        = get_option( 'wp_pinch_api_token', '' );
		$webhook_events   = get_option( 'wp_pinch_webhook_events', array() );
		$governance_tasks = get_option( 'wp_pinch_governance_tasks', array() );
		$governance_mode  = get_option( 'wp_pinch_governance_mode', 'webhook' );
		$rate_limit       = get_option( 'wp_pinch_rate_limit', 30 );
		$abilities_api    = function_exists( 'wp_register_ability' );
		$mcp_adapter      = class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) || has_action( 'mcp_adapter_init' );
		$action_scheduler = function_exists( 'as_has_scheduled_action' );

		$info['wp-pinch'] = array(
			'label'       => __( 'WP Pinch', 'wp-pinch' ),
			'description' => __( 'Diagnostic information for the WP Pinch AI integration plugin.', 'wp-pinch' ),
			'fields'      => array(
				'version'              => array(
					'label' => __( 'Plugin version', 'wp-pinch' ),
					'value' => WP_PINCH_VERSION,
				),
				'gateway_url'          => array(
					'label'   => __( 'Gateway URL', 'wp-pinch' ),
					'value'   => ! empty( $gateway_url ) ? $gateway_url : __( 'Not configured', 'wp-pinch' ),
					'private' => true,
				),
				'api_token'            => array(
					'label'   => __( 'API token', 'wp-pinch' ),
					'value'   => $api_token ? __( 'Configured', 'wp-pinch' ) : __( 'Not configured', 'wp-pinch' ),
					'debug'   => $api_token ? Utils::mask_token( $api_token ) : 'not set',
					'private' => true,
				),
				'abilities_api'        => array(
					'label' => __( 'Abilities API', 'wp-pinch' ),
					'value' => $abilities_api ? __( 'Available', 'wp-pinch' ) : __( 'Missing', 'wp-pinch' ),
					'debug' => $abilities_api ? 'available' : 'missing',
				),
				'mcp_adapter'          => array(
					'label' => __( 'MCP Adapter', 'wp-pinch' ),
					'value' => $mcp_adapter ? __( 'Available', 'wp-pinch' ) : __( 'Not detected', 'wp-pinch' ),
					'debug' => $mcp_adapter ? 'available' : 'not detected',
				),
				'action_scheduler'     => array(
					'label' => __( 'Action Scheduler', 'wp-pinch' ),
					'value' => $action_scheduler ? __( 'Available', 'wp-pinch' ) : __( 'Missing', 'wp-pinch' ),
					'debug' => $action_scheduler ? 'available' : 'missing',
				),
				'rate_limit'           => array(
					'label' => __( 'Webhook rate limit', 'wp-pinch' ),
					'value' => sprintf(
						/* translators: %d: number of requests */
						__( '%d requests per minute', 'wp-pinch' ),
						$rate_limit
					),
					'debug' => $rate_limit . '/min',
				),
				'webhook_events'       => array(
					'label' => __( 'Enabled webhook events', 'wp-pinch' ),
					'value' => ! empty( $webhook_events ) ? implode( ', ', $webhook_events ) : __( 'None', 'wp-pinch' ),
				),
				'governance_tasks'     => array(
					'label' => __( 'Enabled governance tasks', 'wp-pinch' ),
					'value' => ! empty( $governance_tasks ) ? implode( ', ', $governance_tasks ) : __( 'None', 'wp-pinch' ),
				),
				'governance_mode'      => array(
					'label' => __( 'Governance delivery mode', 'wp-pinch' ),
					'value' => $governance_mode,
				),
				'registered_abilities' => array(
					'label' => __( 'Registered abilities', 'wp-pinch' ),
					'value' => count( Abilities::get_ability_names() ),
				),
				'audit_table'          => array(
					'label' => __( 'Audit log table', 'wp-pinch' ),
					'value' => self::get_audit_table_status(),
				),
				'mcp_endpoint'         => array(
					'label' => __( 'MCP endpoint', 'wp-pinch' ),
					'value' => rest_url( 'wp-pinch/mcp' ),
				),
			),
		);

		return $info;
	}

	/**
	 * Get audit table status info.
	 *
	 * @return string
	 */
	private static function get_audit_table_status(): string {
		global $wpdb;

		$table = Audit_Table::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( ! $exists ) {
			return __( 'Table does not exist', 'wp-pinch' );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from Audit_Table::table_name(), not user input.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

		return sprintf(
			/* translators: %d: number of entries */
			__( '%d entries', 'wp-pinch' ),
			$count
		);
	}

	// =========================================================================
	// Site Health Tests (Tools → Site Health → Status)
	// =========================================================================

	/**
	 * Register custom Site Health status tests.
	 *
	 * @param array $tests Existing tests.
	 * @return array
	 */
	public static function register_tests( array $tests ): array {
		$tests['direct']['wp_pinch_gateway'] = array(
			'label' => __( 'WP Pinch gateway connectivity', 'wp-pinch' ),
			'test'  => array( __CLASS__, 'test_gateway_connectivity' ),
		);

		$tests['direct']['wp_pinch_configuration'] = array(
			'label' => __( 'WP Pinch configuration', 'wp-pinch' ),
			'test'  => array( __CLASS__, 'test_configuration' ),
		);

		$tests['direct']['wp_pinch_rest_api'] = array(
			'label' => __( 'WP Pinch REST API availability', 'wp-pinch' ),
			'test'  => array( __CLASS__, 'test_rest_api_availability' ),
		);

		return $tests;
	}

	/**
	 * Test: OpenClaw gateway connectivity.
	 *
	 * @return array Site Health test result.
	 */
	public static function test_gateway_connectivity(): array {
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = get_option( 'wp_pinch_api_token', '' );

		$result = array(
			'label'       => __( 'WP Pinch can reach the AI gateway', 'wp-pinch' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'WP Pinch', 'wp-pinch' ),
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wp-pinch' ) ),
				__( 'Manage WP Pinch settings', 'wp-pinch' )
			),
			'test'        => 'wp_pinch_gateway',
		);

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'WP Pinch gateway is not configured', 'wp-pinch' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'The WP Pinch gateway URL and API token have not been set. AI chat and webhook features will not work until configured.', 'wp-pinch' )
			);
			return $result;
		}

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
			$result['status']      = 'critical';
			$result['label']       = __( 'WP Pinch cannot reach the AI gateway', 'wp-pinch' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: error message */
					__( 'Connection failed: %s', 'wp-pinch' ),
					esc_html( $response->get_error_message() )
				)
			);
			$result['badge']['color'] = 'red';
			return $result;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'WP Pinch AI gateway returned an error', 'wp-pinch' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'The AI gateway returned HTTP %d. Check your API token and gateway URL.', 'wp-pinch' ),
					$code
				)
			);
			$result['badge']['color'] = 'red';
			return $result;
		}

		$result['description'] = sprintf(
			'<p>%s</p>',
			__( 'The WP Pinch AI gateway is reachable and responding correctly.', 'wp-pinch' )
		);

		return $result;
	}

	/**
	 * Test: Plugin configuration completeness.
	 *
	 * @return array Site Health test result.
	 */
	public static function test_configuration(): array {
		$issues = array();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$issues[] = __( 'The WordPress Abilities API is not available. WP Pinch requires WordPress 6.9 or later.', 'wp-pinch' );
		}

		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$issues[] = __( 'Action Scheduler is not available. Background tasks (governance, webhook retries) will not run.', 'wp-pinch' );
		}

		if ( ! class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) && ! has_action( 'mcp_adapter_init' ) ) {
			$issues[] = __( 'The MCP Adapter plugin is not active. AI agents will not be able to discover WP Pinch abilities via MCP.', 'wp-pinch' );
		}

		$result = array(
			'label'       => __( 'WP Pinch is fully configured', 'wp-pinch' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'WP Pinch', 'wp-pinch' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'All WP Pinch dependencies and configuration are in order.', 'wp-pinch' )
			),
			'actions'     => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wp-pinch' ) ),
				__( 'Manage WP Pinch settings', 'wp-pinch' )
			),
			'test'        => 'wp_pinch_configuration',
		);

		if ( ! empty( $issues ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'WP Pinch has configuration recommendations', 'wp-pinch' );
			$result['description']    = '<p>' . implode( '</p><p>', array_map( 'esc_html', $issues ) ) . '</p>';
			$result['badge']['color'] = 'orange';
		}

		return $result;
	}

	/**
	 * Test: REST API reachable (WP Pinch routes).
	 *
	 * @return array Site Health test result.
	 */
	public static function test_rest_api_availability(): array {
		$available = Rest_Availability::check();

		$result = array(
			'label'       => $available
				? __( 'WP Pinch REST API is reachable', 'wp-pinch' )
				: __( 'WP Pinch REST API appears blocked', 'wp-pinch' ),
			'status'      => $available ? 'good' : 'critical',
			'badge'       => array(
				'label' => __( 'WP Pinch', 'wp-pinch' ),
				'color' => $available ? 'blue' : 'red',
			),
			'description' => $available
				? sprintf( '<p>%s</p>', __( 'The WP Pinch REST API is reachable. Chat, MCP, webhooks, and abilities can function.', 'wp-pinch' ) )
				: sprintf(
					'<p>%s</p>',
					__( 'The WordPress REST API appears disabled or blocked. WP Pinch requires the REST API for MCP, chat, webhooks, and ability execution. Check for plugins that disable the REST API, security plugins that block /wp-json/ requests, WAF rules, or page cache. See the Troubleshooting wiki for details.', 'wp-pinch' )
				),
			'actions'     => sprintf(
				'<a href="%s">%s</a> | <a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wp-pinch' ) ),
				__( 'WP Pinch Settings', 'wp-pinch' ),
				esc_url( 'https://github.com/RegionallyFamous/wp-pinch/wiki/Troubleshooting' ),
				__( 'Troubleshooting', 'wp-pinch' )
			),
			'test'        => 'wp_pinch_rest_api',
		);

		return $result;
	}
}
