<?php
/**
 * Tests for the CLI command registration and command classes.
 *
 * Since WP-CLI commands depend on WP_CLI being defined, these tests
 * verify the registration and command class structure rather than full execution.
 *
 * @package WP_Pinch
 */

use WP_Pinch\CLI;
use WP_Pinch\CLI\Status_Command;
use WP_Pinch\CLI\Webhook_Command;
use WP_Pinch\CLI\Governance_Command;
use WP_Pinch\CLI\Audit_Command;
use WP_Pinch\CLI\Abilities_Command;
use WP_Pinch\CLI\Features_Command;
use WP_Pinch\CLI\Config_Command;
use WP_Pinch\CLI\Molt_Command;
use WP_Pinch\CLI\Ghostwrite_Command;
use WP_Pinch\CLI\Cache_Command;
use WP_Pinch\CLI\Approvals_Command;
use WP_Pinch\Audit_Table;

// Load WP-CLI stub so CLI bootstrap can load when not in WP-CLI.
require_once __DIR__ . '/includes/wp-cli-stub.php';

/**
 * Test CLI command registration and structure.
 */
class Test_CLI extends WP_UnitTestCase {

	/**
	 * Set up — ensure the audit table exists and CLI (and command classes) are loaded.
	 */
	public function set_up(): void {
		parent::set_up();
		Audit_Table::create_table();
		require_once dirname( __DIR__ ) . '/includes/class-cli.php';
	}

	/**
	 * Test that the CLI bootstrap class exists and has register.
	 */
	public function test_cli_class_exists(): void {
		$this->assertTrue( class_exists( CLI::class ) );
		$this->assertTrue( method_exists( CLI::class, 'register' ) );
	}

	/**
	 * Test that register is static.
	 */
	public function test_register_is_static(): void {
		$ref = new ReflectionMethod( CLI::class, 'register' );
		$this->assertTrue( $ref->isStatic() );
	}

	/**
	 * Test that all CLI command classes exist and have register + run.
	 */
	public function test_command_classes_exist_and_have_register_and_run(): void {
		$command_classes = array(
			Status_Command::class,
			Webhook_Command::class,
			Governance_Command::class,
			Audit_Command::class,
			Abilities_Command::class,
			Features_Command::class,
			Config_Command::class,
			Molt_Command::class,
			Ghostwrite_Command::class,
			Cache_Command::class,
			Approvals_Command::class,
		);

		foreach ( $command_classes as $class ) {
			$this->assertTrue( class_exists( $class ), "{$class} should exist." );
			$this->assertTrue( method_exists( $class, 'register' ), "{$class}::register() should exist." );
			$this->assertTrue( method_exists( $class, 'run' ), "{$class}::run() should exist." );

			$ref_register = new ReflectionMethod( $class, 'register' );
			$ref_run      = new ReflectionMethod( $class, 'run' );
			$this->assertTrue( $ref_register->isStatic(), "{$class}::register() should be static." );
			$this->assertTrue( $ref_run->isStatic(), "{$class}::run() should be static." );

			$params = $ref_run->getParameters();
			$this->assertGreaterThanOrEqual( 2, count( $params ), "{$class}::run() should accept args and assoc_args." );
			$this->assertSame( 'args', $params[0]->getName() );
			$this->assertSame( 'assoc_args', $params[1]->getName() );
		}
	}
}
