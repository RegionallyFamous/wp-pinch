<?php
/**
 * Tests for the Rest_Availability class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Rest_Availability;

/**
 * Test REST API availability checker.
 */
class Test_Rest_Availability extends WP_UnitTestCase {

	/**
	 * Clean up transients after each test.
	 */
	public function tear_down(): void {
		delete_transient( Rest_Availability::TRANSIENT_KEY );
		parent::tear_down();
	}

	/**
	 * Test that init registers the expected hooks.
	 */
	public function test_init_registers_hooks(): void {
		Rest_Availability::init();

		$this->assertIsInt( has_action( 'admin_init', array( Rest_Availability::class, 'handle_dismiss' ) ) );
		$this->assertIsInt( has_action( 'admin_init', array( Rest_Availability::class, 'maybe_check' ) ) );
		$this->assertIsInt( has_action( 'admin_notices', array( Rest_Availability::class, 'show_notice' ) ) );
	}

	/**
	 * Test check returns true when health endpoint returns 200.
	 */
	public function test_check_returns_true_on_200(): void {
		add_filter(
			'pre_http_request',
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{}',
				);
			}
		);

		$result = Rest_Availability::check();

		remove_all_filters( 'pre_http_request' );

		$this->assertTrue( $result );
	}

	/**
	 * Test check returns false when health endpoint returns 5xx.
	 */
	public function test_check_returns_false_on_5xx(): void {
		add_filter(
			'pre_http_request',
			function () {
				return array(
					'response' => array( 'code' => 503 ),
					'body'     => '',
				);
			}
		);

		$result = Rest_Availability::check();

		remove_all_filters( 'pre_http_request' );

		$this->assertFalse( $result );
	}

	/**
	 * Test check returns false when wp_remote_get returns WP_Error.
	 */
	public function test_check_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'mock_error', 'Mock HTTP error' );
			}
		);

		$result = Rest_Availability::check();

		remove_all_filters( 'pre_http_request' );

		$this->assertFalse( $result );
	}

	/**
	 * Test TRANSIENT_KEY and CHECK_INTERVAL constants.
	 */
	public function test_constants(): void {
		$this->assertSame( 'wp_pinch_rest_available', Rest_Availability::TRANSIENT_KEY );
		$this->assertSame( 300, Rest_Availability::CHECK_INTERVAL );
	}
}
