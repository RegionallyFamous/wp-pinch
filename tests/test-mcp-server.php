<?php
/**
 * Tests for the MCP_Server class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\MCP_Server;
use WP_Pinch\Audit_Table;

/**
 * Test MCP server registration, ability exposure, and filtering.
 */
class Test_MCP_Server extends WP_UnitTestCase {

	/**
	 * Set up — ensure the audit table exists.
	 */
	public function set_up(): void {
		parent::set_up();
		Audit_Table::create_table();
	}

	// =========================================================================
	// CORE_ABILITIES constant
	// =========================================================================

	/**
	 * Test that CORE_ABILITIES only contains get-site-info.
	 */
	public function test_core_abilities_contains_only_site_info(): void {
		$this->assertSame(
			array( 'core/get-site-info' ),
			MCP_Server::CORE_ABILITIES,
			'CORE_ABILITIES should only contain core/get-site-info.'
		);
	}

	/**
	 * Test that get-user-info is NOT in CORE_ABILITIES (security).
	 */
	public function test_core_abilities_excludes_user_info(): void {
		$this->assertNotContains(
			'core/get-user-info',
			MCP_Server::CORE_ABILITIES,
			'CORE_ABILITIES must not expose get-user-info.'
		);
	}

	/**
	 * Test that get-environment-info is NOT in CORE_ABILITIES (security).
	 */
	public function test_core_abilities_excludes_environment_info(): void {
		$this->assertNotContains(
			'core/get-environment-info',
			MCP_Server::CORE_ABILITIES,
			'CORE_ABILITIES must not expose get-environment-info.'
		);
	}

	// =========================================================================
	// expose_core_abilities filter
	// =========================================================================

	/**
	 * Test that core abilities get mcp.public = true.
	 */
	public function test_expose_core_abilities_sets_public_flag(): void {
		$args   = array();
		$result = MCP_Server::expose_core_abilities( $args, 'core/get-site-info' );

		$this->assertTrue( $result['meta']['mcp']['public'] );
	}

	/**
	 * Test that non-core abilities are NOT modified.
	 */
	public function test_expose_core_abilities_ignores_non_core(): void {
		$args   = array( 'some' => 'data' );
		$result = MCP_Server::expose_core_abilities( $args, 'wp-pinch/list-posts' );

		$this->assertArrayNotHasKey( 'meta', $result );
		$this->assertSame( array( 'some' => 'data' ), $result );
	}

	/**
	 * Test that expose_core_abilities preserves existing meta.
	 */
	public function test_expose_core_abilities_preserves_existing_meta(): void {
		$args   = array(
			'meta' => array(
				'custom' => 'value',
			),
		);
		$result = MCP_Server::expose_core_abilities( $args, 'core/get-site-info' );

		$this->assertSame( 'value', $result['meta']['custom'] );
		$this->assertTrue( $result['meta']['mcp']['public'] );
	}

	/**
	 * Test that expose_core_abilities preserves existing mcp meta.
	 */
	public function test_expose_core_abilities_preserves_existing_mcp_meta(): void {
		$args   = array(
			'meta' => array(
				'mcp' => array(
					'custom_flag' => true,
				),
			),
		);
		$result = MCP_Server::expose_core_abilities( $args, 'core/get-site-info' );

		$this->assertTrue( $result['meta']['mcp']['custom_flag'] );
		$this->assertTrue( $result['meta']['mcp']['public'] );
	}

	// =========================================================================
	// init hook registration
	// =========================================================================

	/**
	 * Test that init registers the expected hooks.
	 */
	public function test_init_registers_hooks(): void {
		MCP_Server::init();

		$this->assertIsInt( has_action( 'mcp_adapter_init', array( MCP_Server::class, 'register_server' ) ) );
		$this->assertIsInt( has_filter( 'wp_register_ability_args', array( MCP_Server::class, 'expose_core_abilities' ) ) );
	}

	// =========================================================================
	// register_server
	// =========================================================================

	/**
	 * Test that register_server exits gracefully when adapter lacks create_server.
	 */
	public function test_register_server_exits_gracefully_without_create_server(): void {
		$adapter = new stdClass();

		// Should not throw or error.
		MCP_Server::register_server( $adapter );
		$this->assertTrue( true, 'register_server should exit gracefully.' );
	}

	/**
	 * Test that register_server calls create_server with correct arguments.
	 */
	public function test_register_server_calls_create_server(): void {
		$mock = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'create_server' ) )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'create_server' )
			->with(
				'wp-pinch',       // Server ID.
				'wp-pinch',       // REST namespace.
				'mcp',            // Route.
				$this->isType( 'string' ), // Name.
				$this->isType( 'string' ), // Description.
				$this->isType( 'string' ), // Version.
				$this->isType( 'array' ),  // Transports.
				$this->anything(),         // Error handler.
				$this->anything(),         // Observability.
				$this->isType( 'array' )   // Abilities.
			);

		MCP_Server::register_server( $mock );
	}

	/**
	 * Test that register_server includes CORE_ABILITIES in the abilities list.
	 */
	public function test_register_server_includes_core_abilities(): void {
		$captured_abilities = null;

		$mock = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'create_server' ) )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'create_server' )
			->willReturnCallback(
				function () use ( &$captured_abilities ) {
					$args               = func_get_args();
					$captured_abilities = $args[9]; // 10th argument = abilities.
				}
			);

		MCP_Server::register_server( $mock );

		$this->assertContains( 'core/get-site-info', $captured_abilities );
	}

	/**
	 * Test that the wp_pinch_mcp_server_abilities filter works.
	 */
	public function test_mcp_server_abilities_filter(): void {
		$captured_abilities = null;

		$mock = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'create_server' ) )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'create_server' )
			->willReturnCallback(
				function () use ( &$captured_abilities ) {
					$args               = func_get_args();
					$captured_abilities = $args[9];
				}
			);

		add_filter(
			'wp_pinch_mcp_server_abilities',
			function ( $abilities ) {
				$abilities[] = 'custom/my-ability';
				return $abilities;
			}
		);

		MCP_Server::register_server( $mock );

		$this->assertContains( 'custom/my-ability', $captured_abilities );

		remove_all_filters( 'wp_pinch_mcp_server_abilities' );
	}

	/**
	 * Test that register_server logs error on create_server failure.
	 */
	public function test_register_server_logs_error_on_failure(): void {
		$mock = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'create_server' ) )
			->getMock();

		$mock->method( 'create_server' )
			->willThrowException( new \RuntimeException( 'Test error' ) );

		MCP_Server::register_server( $mock );

		// Verify audit log entry was created.
		$result = Audit_Table::query( array( 'event_type' => 'mcp_server_error' ) );
		$this->assertGreaterThan( 0, $result['total'] );
		$this->assertStringContainsString( 'Test error', $result['items'][0]['message'] );
	}
}
