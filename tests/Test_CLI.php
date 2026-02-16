<?php
/**
 * Tests for the CLI class.
 *
 * Since WP-CLI commands depend on WP_CLI being defined, these tests
 * verify the registration and method signatures rather than full execution.
 *
 * @package WP_Pinch
 */

use WP_Pinch\CLI;
use WP_Pinch\Audit_Table;

/**
 * Test CLI command registration and method existence.
 */
class Test_CLI extends WP_UnitTestCase {

	/**
	 * Set up — ensure the audit table exists.
	 */
	public function set_up(): void {
		parent::set_up();
		Audit_Table::create_table();
	}

	// =========================================================================
	// Class structure
	// =========================================================================

	/**
	 * Test that the CLI class exists.
	 */
	public function test_cli_class_exists(): void {
		$this->assertTrue( class_exists( CLI::class ) );
	}

	/**
	 * Test that all expected command methods exist.
	 */
	public function test_command_methods_exist(): void {
		$methods = array( 'register', 'status', 'webhook_test', 'governance', 'audit', 'abilities' );

		foreach ( $methods as $method ) {
			$this->assertTrue(
				method_exists( CLI::class, $method ),
				"CLI::{$method}() should exist."
			);
		}
	}

	/**
	 * Test that command methods have correct signatures (accept args and assoc_args).
	 */
	public function test_command_method_signatures(): void {
		$commands = array( 'status', 'webhook_test', 'governance', 'audit', 'abilities' );

		foreach ( $commands as $command ) {
			$ref = new ReflectionMethod( CLI::class, $command );
			$params = $ref->getParameters();

			$this->assertGreaterThanOrEqual(
				2,
				count( $params ),
				"CLI::{$command}() should accept at least 2 parameters."
			);

			$this->assertSame( 'args', $params[0]->getName() );
			$this->assertSame( 'assoc_args', $params[1]->getName() );
		}
	}

	/**
	 * Test that register method is static.
	 */
	public function test_register_is_static(): void {
		$ref = new ReflectionMethod( CLI::class, 'register' );
		$this->assertTrue( $ref->isStatic() );
	}

	/**
	 * Test that all command methods are static.
	 */
	public function test_all_commands_are_static(): void {
		$commands = array( 'status', 'webhook_test', 'governance', 'audit', 'abilities' );

		foreach ( $commands as $command ) {
			$ref = new ReflectionMethod( CLI::class, $command );
			$this->assertTrue(
				$ref->isStatic(),
				"CLI::{$command}() should be static."
			);
		}
	}
}
