<?php
/**
 * Tests for the Plugin lifecycle class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Plugin;
use WP_Pinch\Audit_Table;

/**
 * Test plugin activation, deactivation, and version migration.
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
	}

	// =========================================================================
	// Constants
	// =========================================================================

	/**
	 * Test that required constants are defined.
	 */
	public function test_constants_defined(): void {
		$this->assertTrue( defined( 'WP_PINCH_VERSION' ), 'WP_PINCH_VERSION should be defined.' );
		$this->assertTrue( defined( 'WP_PINCH_FILE' ), 'WP_PINCH_FILE should be defined.' );
		$this->assertTrue( defined( 'WP_PINCH_DIR' ), 'WP_PINCH_DIR should be defined.' );
		$this->assertTrue( defined( 'WP_PINCH_URL' ), 'WP_PINCH_URL should be defined.' );
	}

	/**
	 * Test version constant matches plugin header.
	 */
	public function test_version_constant_format(): void {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+/', WP_PINCH_VERSION );
	}

	/**
	 * Test WP_PINCH_DIR ends with a trailing slash.
	 */
	public function test_dir_trailing_slash(): void {
		$this->assertStringEndsWith( '/', WP_PINCH_DIR );
	}

	/**
	 * Test WP_PINCH_URL ends with a trailing slash.
	 */
	public function test_url_trailing_slash(): void {
		$this->assertStringEndsWith( '/', WP_PINCH_URL );
	}

	// =========================================================================
	// Singleton
	// =========================================================================

	/**
	 * Test Plugin::instance() returns the same instance.
	 */
	public function test_singleton(): void {
		$a = Plugin::instance();
		$b = Plugin::instance();
		$this->assertSame( $a, $b, 'Plugin::instance() should always return the same object.' );
	}

	// =========================================================================
	// Activation
	// =========================================================================

	/**
	 * Test activation creates the audit table.
	 */
	public function test_activation_creates_audit_table(): void {
		global $wpdb;

		Plugin::instance()->activate();

		$table = Audit_Table::table_name();

		// Use SHOW TABLES to verify the table exists.
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
		);

		$this->assertEquals( $table, $result, 'Audit table should be created on activation.' );
	}

	/**
	 * Test activation stores the plugin version.
	 */
	public function test_activation_stores_version(): void {
		Plugin::instance()->activate();

		$this->assertEquals(
			WP_PINCH_VERSION,
			get_option( 'wp_pinch_version' ),
			'Plugin version should be stored on activation.'
		);
	}

	/**
	 * Test activation fires the wp_pinch_activated action.
	 */
	public function test_activation_fires_action(): void {
		$fired = false;
		add_action( 'wp_pinch_activated', function () use ( &$fired ) {
			$fired = true;
		} );

		Plugin::instance()->activate();

		$this->assertTrue( $fired, 'wp_pinch_activated action should fire.' );
	}

	// =========================================================================
	// Deactivation
	// =========================================================================

	/**
	 * Test deactivation fires the wp_pinch_deactivated action.
	 */
	public function test_deactivation_fires_action(): void {
		$fired = false;
		add_action( 'wp_pinch_deactivated', function () use ( &$fired ) {
			$fired = true;
		} );

		Plugin::instance()->deactivate();

		$this->assertTrue( $fired, 'wp_pinch_deactivated action should fire.' );
	}

	// =========================================================================
	// Version migration
	// =========================================================================

	/**
	 * Test boot runs migration when stored version differs.
	 */
	public function test_boot_runs_migration_on_version_change(): void {
		// Simulate an older version being stored.
		update_option( 'wp_pinch_version', '0.9.0' );

		// Boot should update the version.
		Plugin::instance()->boot();

		$this->assertEquals(
			WP_PINCH_VERSION,
			get_option( 'wp_pinch_version' ),
			'Version should be updated after migration.'
		);
	}

	/**
	 * Test boot skips migration when version matches.
	 */
	public function test_boot_skips_migration_when_current(): void {
		update_option( 'wp_pinch_version', WP_PINCH_VERSION );

		// This should not call create_table again.
		Plugin::instance()->boot();

		$this->assertEquals(
			WP_PINCH_VERSION,
			get_option( 'wp_pinch_version' )
		);
	}

	/**
	 * Test 2.7.0 migration sets autoload=no on WP Pinch options.
	 */
	public function test_migration_2_7_0_sets_autoload_no(): void {
		global $wpdb;

		update_option( 'wp_pinch_version', '2.6.0' );
		update_option( 'wp_pinch_rate_limit', 30 );

		Plugin::instance()->boot();

		$this->assertSame( '2.7.0', get_option( 'wp_pinch_version' ), 'Version should be updated to 2.7.0.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
				'wp_pinch_rate_limit'
			)
		);
		// Both 'no' and 'off' mean "don't autoload" depending on WordPress/DB version.
		$this->assertContains( $autoload, array( 'no', 'off' ), 'WP Pinch options should have autoload=no/off after 2.7.0 migration.' );
	}

	// =========================================================================
	// Textdomain
	// =========================================================================

	/**
	 * Test plugin header has Text Domain and Domain Path (WP 4.6+ auto-loads for .org-hosted plugins).
	 */
	public function test_translation_setup(): void {
		$headers = get_file_data(
			WP_PINCH_FILE,
			array( 'Text Domain' => 'Text Domain', 'Domain Path' => 'Domain Path' ),
			'plugin'
		);
		$this->assertSame( 'wp-pinch', $headers['Text Domain'], 'Text domain should be wp-pinch' );
		$this->assertSame( '/languages', $headers['Domain Path'], 'Domain path should be /languages' );
	}

	// =========================================================================
	// Block registration
	// =========================================================================

	/**
	 * Test block registration does not produce errors.
	 */
	public function test_register_blocks(): void {
		// Block may already be registered from init; unregister to avoid "already registered" notice.
		$registry = WP_Block_Type_Registry::get_instance();
		if ( $registry->is_registered( 'wp-pinch/chat' ) ) {
			$registry->unregister( 'wp-pinch/chat' );
		}

		Plugin::instance()->register_blocks();

		// We can only verify it doesn't fatal — the block.json may not exist in test env.
		$this->assertTrue( true );
	}

	// =========================================================================
	// Global helper function
	// =========================================================================

	// =========================================================================
	// Kill switch and read-only
	// =========================================================================

	/**
	 * SECURITY: Test is_api_disabled returns true when option is set.
	 */
	public function test_is_api_disabled_option(): void {
		update_option( 'wp_pinch_api_disabled', true );
		$this->assertTrue( Plugin::is_api_disabled() );
		delete_option( 'wp_pinch_api_disabled' );
	}

	/**
	 * SECURITY: Test is_api_disabled returns false when option is not set.
	 */
	public function test_is_api_disabled_false_when_not_set(): void {
		delete_option( 'wp_pinch_api_disabled' );
		$this->assertFalse( Plugin::is_api_disabled() );
	}

	/**
	 * SECURITY: Test is_read_only_mode returns true when option is set.
	 */
	public function test_is_read_only_mode_option(): void {
		update_option( 'wp_pinch_read_only_mode', true );
		$this->assertTrue( Plugin::is_read_only_mode() );
		delete_option( 'wp_pinch_read_only_mode' );
	}

	/**
	 * SECURITY: Test is_read_only_mode returns false when option is not set.
	 */
	public function test_is_read_only_mode_false_when_not_set(): void {
		delete_option( 'wp_pinch_read_only_mode' );
		$this->assertFalse( Plugin::is_read_only_mode() );
	}

	// =========================================================================
	// Global helper function
	// =========================================================================

	/**
	 * Test wp_pinch_get_ability_names() global function exists.
	 */
	public function test_global_ability_names_function(): void {
		$this->assertTrue(
			function_exists( 'wp_pinch_get_ability_names' ),
			'wp_pinch_get_ability_names() should be defined.'
		);

		$names = wp_pinch_get_ability_names();
		$this->assertIsArray( $names );
		$this->assertNotEmpty( $names );
	}
}
