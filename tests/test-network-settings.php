<?php
/**
 * Tests for the Network_Settings class and network API token.
 *
 * Tests run in both single-site and multisite. When not multisite,
 * get_network_api_token returns '' and set_network_api_token returns false.
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
	 * Test get_network_api_token returns empty when not set (or when single-site).
	 */
	public function test_get_network_api_token_empty(): void {
		if ( is_multisite() ) {
			delete_site_option( 'wp_pinch_network_api_token' );
		}
		$this->assertSame( '', Settings::get_network_api_token() );
	}

	/**
	 * Test set_network_api_token and get_network_api_token round-trip (multisite) or no-op (single-site).
	 */
	public function test_set_get_network_api_token(): void {
		$token = 'test-network-token-' . wp_generate_password( 16, false );

		if ( is_multisite() ) {
			$result = Settings::set_network_api_token( $token );
			$this->assertTrue( $result, 'set_network_api_token should return true on multisite.' );
			$this->assertSame( $token, Settings::get_network_api_token(), 'Token should round-trip on multisite.' );
			Settings::set_network_api_token( '' );
		} else {
			$result = Settings::set_network_api_token( $token );
			$this->assertFalse( $result, 'set_network_api_token should return false when not multisite.' );
			$this->assertSame( '', Settings::get_network_api_token(), 'get_network_api_token should return empty when not multisite.' );
		}
	}

	/**
	 * Test set_network_api_token with empty string clears (multisite) or returns false (single-site).
	 */
	public function test_set_network_api_token_empty_clears(): void {
		if ( is_multisite() ) {
			Settings::set_network_api_token( 'temp-token' );
			$this->assertNotSame( '', Settings::get_network_api_token() );
			Settings::set_network_api_token( '' );
			$this->assertSame( '', Settings::get_network_api_token() );
		} else {
			$this->assertFalse( Settings::set_network_api_token( '' ), 'set_network_api_token(empty) should return false when not multisite.' );
		}
	}

	/**
	 * Test set_network_api_token returns false when not multisite.
	 */
	public function test_set_network_api_token_requires_multisite(): void {
		if ( is_multisite() ) {
			$this->assertTrue( Settings::set_network_api_token( 'x' ), 'set should succeed on multisite.' );
			Settings::set_network_api_token( '' );
		} else {
			$this->assertFalse( Settings::set_network_api_token( 'x' ), 'set should return false when not multisite.' );
		}
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
