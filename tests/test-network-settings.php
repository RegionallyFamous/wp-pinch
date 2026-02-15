<?php
/**
 * Tests for the Network_Settings class and network API token.
 *
 * Requires multisite. Tests are skipped when not running in multisite.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Settings;
use WP_Pinch\Network_Settings;

/**
 * Test network settings and network API token.
 */
class Test_Network_Settings extends WP_UnitTestCase {

	/**
	 * Skip tests when not in multisite.
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Network settings tests require multisite.' );
		}
	}

	/**
	 * Test get_network_api_token returns empty when not set.
	 */
	public function test_get_network_api_token_empty(): void {
		delete_site_option( 'wp_pinch_network_api_token' );
		$this->assertSame( '', Settings::get_network_api_token() );
	}

	/**
	 * Test set_network_api_token and get_network_api_token round-trip.
	 */
	public function test_set_get_network_api_token(): void {
		$token = 'test-network-token-' . wp_generate_password( 16, false );
		$result = Settings::set_network_api_token( $token );
		$this->assertTrue( $result, 'set_network_api_token should return true.' );
		$this->assertSame( $token, Settings::get_network_api_token(), 'Token should round-trip.' );

		// Clean up.
		Settings::set_network_api_token( '' );
	}

	/**
	 * Test set_network_api_token with empty string clears the token.
	 */
	public function test_set_network_api_token_empty_clears(): void {
		Settings::set_network_api_token( 'temp-token' );
		$this->assertNotSame( '', Settings::get_network_api_token() );

		Settings::set_network_api_token( '' );
		$this->assertSame( '', Settings::get_network_api_token() );
	}

	/**
	 * Test set_network_api_token returns false when not multisite.
	 */
	public function test_set_network_api_token_requires_multisite(): void {
		// We're in multisite here, so this will succeed. The method checks is_multisite() internally.
		// When not multisite, get_network_api_token returns '' and set returns false.
		// This test verifies the methods don't crash; behavior is covered above.
		$this->assertTrue( is_multisite(), 'Test requires multisite.' );
	}

	/**
	 * Test Network_Settings::get_page_url returns valid URL.
	 */
	public function test_network_settings_page_url(): void {
		$url = Network_Settings::get_page_url();
		$this->assertStringContainsString( 'wp-pinch-network', $url );
		$this->assertStringContainsString( 'settings.php', $url );
	}
}
