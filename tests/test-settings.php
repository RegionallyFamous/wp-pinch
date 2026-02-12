<?php
/**
 * Tests for the Settings class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Settings;

/**
 * Test settings registration, sanitization, and admin integration.
 */
class Test_Settings extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	// =========================================================================
	// Settings Registration
	// =========================================================================

	/**
	 * Test that all expected settings are registered.
	 */
	public function test_settings_registered(): void {
		Settings::register_settings();

		// Connection group.
		$this->assertNotFalse( get_registered_settings()['wp_pinch_gateway_url'] ?? false );
		$this->assertNotFalse( get_registered_settings()['wp_pinch_api_token'] ?? false );
		$this->assertNotFalse( get_registered_settings()['wp_pinch_rate_limit'] ?? false );

		// Webhooks group.
		$this->assertNotFalse( get_registered_settings()['wp_pinch_webhook_events'] ?? false );

		// Governance group.
		$this->assertNotFalse( get_registered_settings()['wp_pinch_governance_tasks'] ?? false );
		$this->assertNotFalse( get_registered_settings()['wp_pinch_governance_mode'] ?? false );
	}

	/**
	 * Test that all settings have show_in_rest set to false.
	 */
	public function test_settings_hidden_from_rest(): void {
		Settings::register_settings();

		$settings_to_check = array(
			'wp_pinch_gateway_url',
			'wp_pinch_api_token',
			'wp_pinch_rate_limit',
			'wp_pinch_webhook_events',
			'wp_pinch_governance_tasks',
			'wp_pinch_governance_mode',
		);

		$registered = get_registered_settings();

		foreach ( $settings_to_check as $setting ) {
			$this->assertArrayHasKey( $setting, $registered, "Setting '{$setting}' should be registered." );
			$this->assertFalse(
				$registered[ $setting ]['show_in_rest'],
				"Setting '{$setting}' must have show_in_rest=false."
			);
		}
	}

	// =========================================================================
	// Sanitization
	// =========================================================================

	/**
	 * Test gateway URL is sanitized with esc_url_raw.
	 */
	public function test_gateway_url_sanitization(): void {
		Settings::register_settings();
		$registered = get_registered_settings();

		$sanitize = $registered['wp_pinch_gateway_url']['sanitize_callback'];
		$this->assertEquals( 'esc_url_raw', $sanitize );

		// Verify the callback works.
		$result = call_user_func( $sanitize, 'http://example.com/<script>' );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * Test API token is sanitized with sanitize_text_field.
	 */
	public function test_api_token_sanitization(): void {
		Settings::register_settings();
		$registered = get_registered_settings();

		$sanitize = $registered['wp_pinch_api_token']['sanitize_callback'];
		$this->assertIsCallable( $sanitize );

		// Normal token value should be sanitized and returned.
		$result = call_user_func( $sanitize, 'my-secret-token' );
		$this->assertEquals( 'my-secret-token', $result );

		// HTML should be stripped.
		$result = call_user_func( $sanitize, '<script>bad</script>token' );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * Test rate limit is sanitized with absint.
	 */
	public function test_rate_limit_sanitization(): void {
		Settings::register_settings();
		$registered = get_registered_settings();

		$this->assertEquals(
			'absint',
			$registered['wp_pinch_rate_limit']['sanitize_callback']
		);
	}

	/**
	 * Test webhook events sanitize_callback handles non-array input.
	 */
	public function test_webhook_events_sanitization_non_array(): void {
		Settings::register_settings();
		$registered = get_registered_settings();

		$sanitize = $registered['wp_pinch_webhook_events']['sanitize_callback'];
		$result   = call_user_func( $sanitize, 'not_an_array' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test webhook events sanitize_callback handles array input.
	 */
	public function test_webhook_events_sanitization_array(): void {
		Settings::register_settings();
		$registered = get_registered_settings();

		$sanitize = $registered['wp_pinch_webhook_events']['sanitize_callback'];
		$result   = call_user_func( $sanitize, array( 'post_status_change', 'NEW_COMMENT', 'with spaces' ) );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		// sanitize_key lowercases and removes non-alphanumeric characters.
		$this->assertEquals( 'post_status_change', $result[0] );
		$this->assertEquals( 'new_comment', $result[1] );
	}

	// =========================================================================
	// Action Links
	// =========================================================================

	/**
	 * Test add_action_links adds a Settings link.
	 */
	public function test_add_action_links(): void {
		$links = Settings::add_action_links( array( 'deactivate' => '<a href="#">Deactivate</a>' ) );

		// Settings link should be prepended.
		$first_link = reset( $links );
		$this->assertStringContainsString( 'Settings', $first_link );
		$this->assertStringContainsString( 'page=wp-pinch', $first_link );
	}

	/**
	 * Test add_action_links preserves existing links.
	 */
	public function test_add_action_links_preserves_existing(): void {
		$existing = array(
			'deactivate' => '<a href="#">Deactivate</a>',
		);

		$links = Settings::add_action_links( $existing );

		// Should now have 2 links (Settings + Deactivate).
		$this->assertCount( 2, $links );
	}

	// =========================================================================
	// Menu Registration
	// =========================================================================

	/**
	 * Test that admin menu page is added as a top-level page.
	 */
	public function test_admin_menu_registered(): void {
		global $menu;

		Settings::add_menu();

		// Search for wp-pinch in the admin menu.
		$found = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && 'wp-pinch' === $item[2] ) {
					$found = true;
					break;
				}
			}
		}

		$this->assertTrue( $found, 'WP Pinch should be registered as a top-level admin menu.' );
	}
}
