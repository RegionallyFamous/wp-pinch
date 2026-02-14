<?php
/**
 * Tests for the Site_Health class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Site_Health;
use WP_Pinch\Audit_Table;

/**
 * Test Site Health debug info and status tests.
 */
class Test_Site_Health extends WP_UnitTestCase {

	/**
	 * Set up — ensure the audit table exists.
	 */
	public function set_up(): void {
		parent::set_up();
		Audit_Table::create_table();
	}

	// =========================================================================
	// Hook registration
	// =========================================================================

	/**
	 * Test that init registers the expected hooks.
	 */
	public function test_init_registers_hooks(): void {
		Site_Health::init();

		$this->assertIsInt( has_filter( 'debug_information', array( Site_Health::class, 'add_debug_info' ) ) );
		$this->assertIsInt( has_filter( 'site_status_tests', array( Site_Health::class, 'register_tests' ) ) );
	}

	// =========================================================================
	// Debug info
	// =========================================================================

	/**
	 * Test that debug info adds the wp-pinch section.
	 */
	public function test_add_debug_info_section(): void {
		$info = Site_Health::add_debug_info( array() );

		$this->assertArrayHasKey( 'wp-pinch', $info );
		$this->assertSame( 'WP Pinch', $info['wp-pinch']['label'] );
	}

	/**
	 * Test that debug info contains expected fields.
	 */
	public function test_debug_info_fields(): void {
		$info = Site_Health::add_debug_info( array() );
		$fields = $info['wp-pinch']['fields'];

		$expected_keys = array(
			'version',
			'gateway_url',
			'api_token',
			'abilities_api',
			'mcp_adapter',
			'action_scheduler',
			'rate_limit',
			'webhook_events',
			'governance_tasks',
			'governance_mode',
			'registered_abilities',
			'audit_table',
			'mcp_endpoint',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $fields, "Missing debug info field: {$key}" );
		}
	}

	/**
	 * Test that plugin version is reported correctly.
	 */
	public function test_debug_info_version(): void {
		$info = Site_Health::add_debug_info( array() );

		$this->assertSame( WP_PINCH_VERSION, $info['wp-pinch']['fields']['version']['value'] );
	}

	/**
	 * Test that API token field is marked private.
	 */
	public function test_debug_info_token_is_private(): void {
		$info = Site_Health::add_debug_info( array() );

		$this->assertTrue( $info['wp-pinch']['fields']['api_token']['private'] );
	}

	/**
	 * Test that gateway URL field is marked private.
	 */
	public function test_debug_info_gateway_is_private(): void {
		$info = Site_Health::add_debug_info( array() );

		$this->assertTrue( $info['wp-pinch']['fields']['gateway_url']['private'] );
	}

	/**
	 * Test that unconfigured gateway shows "Not configured".
	 */
	public function test_debug_info_unconfigured_gateway(): void {
		delete_option( 'wp_pinch_gateway_url' );
		$info = Site_Health::add_debug_info( array() );

		$this->assertStringContainsString(
			'Not configured',
			$info['wp-pinch']['fields']['gateway_url']['value']
		);
	}

	/**
	 * Test that API token shows "Not configured" when empty.
	 */
	public function test_debug_info_unconfigured_token(): void {
		delete_option( 'wp_pinch_api_token' );
		$info = Site_Health::add_debug_info( array() );

		$this->assertStringContainsString(
			'Not configured',
			$info['wp-pinch']['fields']['api_token']['value']
		);
	}

	/**
	 * Test that audit table status shows entry count.
	 */
	public function test_debug_info_audit_table_count(): void {
		Audit_Table::insert( 'test', 'test', 'Test entry' );

		$info = Site_Health::add_debug_info( array() );

		$this->assertMatchesRegularExpression(
			'/\d+ entries/',
			$info['wp-pinch']['fields']['audit_table']['value']
		);
	}

	/**
	 * Test that debug info preserves existing sections.
	 */
	public function test_add_debug_info_preserves_existing(): void {
		$existing = array(
			'existing-section' => array(
				'label' => 'Existing',
				'fields' => array(),
			),
		);

		$info = Site_Health::add_debug_info( $existing );

		$this->assertArrayHasKey( 'existing-section', $info );
		$this->assertArrayHasKey( 'wp-pinch', $info );
	}

	// =========================================================================
	// Status tests registration
	// =========================================================================

	/**
	 * Test that status tests are registered.
	 */
	public function test_register_tests(): void {
		$tests = Site_Health::register_tests( array( 'direct' => array() ) );

		$this->assertArrayHasKey( 'wp_pinch_gateway', $tests['direct'] );
		$this->assertArrayHasKey( 'wp_pinch_configuration', $tests['direct'] );
		$this->assertArrayHasKey( 'wp_pinch_rest_api', $tests['direct'] );
	}

	/**
	 * Test REST API availability test structure and status.
	 */
	public function test_rest_api_availability_test(): void {
		$result = Site_Health::test_rest_api_availability();

		$this->assertArrayHasKey( 'label', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'badge', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'actions', $result );
		$this->assertSame( 'wp_pinch_rest_api', $result['test'] );
		$this->assertContains( $result['status'], array( 'good', 'critical' ), 'REST API test status should be good or critical.' );
	}

	/**
	 * Test that register_tests preserves existing tests.
	 */
	public function test_register_tests_preserves_existing(): void {
		$existing = array(
			'direct' => array(
				'existing_test' => array( 'label' => 'Existing' ),
			),
		);

		$tests = Site_Health::register_tests( $existing );

		$this->assertArrayHasKey( 'existing_test', $tests['direct'] );
	}

	// =========================================================================
	// Gateway connectivity test
	// =========================================================================

	/**
	 * Test gateway test returns recommended when not configured.
	 */
	public function test_gateway_test_unconfigured(): void {
		delete_option( 'wp_pinch_gateway_url' );
		delete_option( 'wp_pinch_api_token' );

		$result = Site_Health::test_gateway_connectivity();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'not configured', $result['label'] );
	}

	/**
	 * Test gateway test has correct structure.
	 */
	public function test_gateway_test_structure(): void {
		$result = Site_Health::test_gateway_connectivity();

		$this->assertArrayHasKey( 'label', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'badge', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'actions', $result );
		$this->assertArrayHasKey( 'test', $result );
		$this->assertSame( 'wp_pinch_gateway', $result['test'] );
	}

	// =========================================================================
	// Configuration test
	// =========================================================================

	/**
	 * Test configuration test has correct structure.
	 */
	public function test_configuration_test_structure(): void {
		$result = Site_Health::test_configuration();

		$this->assertArrayHasKey( 'label', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'badge', $result );
		$this->assertArrayHasKey( 'test', $result );
		$this->assertSame( 'wp_pinch_configuration', $result['test'] );
	}

	/**
	 * Test configuration test returns recommended when Abilities API is missing.
	 */
	public function test_configuration_test_status(): void {
		// In test environment, Abilities API likely isn't available.
		$result = Site_Health::test_configuration();

		// Status should be either 'good' or 'recommended' depending on environment.
		$this->assertContains( $result['status'], array( 'good', 'recommended' ) );
	}

	/**
	 * Test configuration badge has WP Pinch label.
	 */
	public function test_configuration_badge_label(): void {
		$result = Site_Health::test_configuration();

		$this->assertSame( 'WP Pinch', $result['badge']['label'] );
	}
}
