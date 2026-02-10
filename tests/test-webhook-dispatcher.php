<?php
/**
 * Tests for the Webhook Dispatcher.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Webhook_Dispatcher;
use WP_Pinch\Audit_Table;

/**
 * Test webhook dispatch logic.
 */
class Test_Webhook_Dispatcher extends WP_UnitTestCase {

	/**
	 * Set up test options.
	 */
	public function set_up(): void {
		parent::set_up();
		// Don't configure gateway so webhooks don't actually fire.
		delete_option( 'wp_pinch_gateway_url' );
		delete_option( 'wp_pinch_api_token' );
	}

	/**
	 * Test dispatch returns false when not configured.
	 */
	public function test_dispatch_returns_false_when_not_configured(): void {
		$result = Webhook_Dispatcher::dispatch( 'test', 'Test message' );
		$this->assertFalse( $result );
	}

	/**
	 * Test available events list.
	 */
	public function test_available_events(): void {
		$events = Webhook_Dispatcher::get_available_events();

		$this->assertIsArray( $events );
		$this->assertArrayHasKey( 'post_status_change', $events );
		$this->assertArrayHasKey( 'new_comment', $events );
		$this->assertArrayHasKey( 'user_register', $events );
		$this->assertArrayHasKey( 'governance_finding', $events );
	}

	/**
	 * Test that the before_webhook action fires.
	 */
	public function test_before_webhook_action(): void {
		update_option( 'wp_pinch_gateway_url', 'http://localhost:3000' );
		update_option( 'wp_pinch_api_token', 'test-token' );

		$fired = false;
		add_action( 'wp_pinch_before_webhook', function () use ( &$fired ) {
			$fired = true;
		} );

		// This will fail the HTTP request but the action should still fire.
		Webhook_Dispatcher::dispatch( 'test', 'Test message' );

		$this->assertTrue( $fired, 'wp_pinch_before_webhook action should fire.' );
	}

	/**
	 * Test payload filter.
	 */
	public function test_payload_filter(): void {
		update_option( 'wp_pinch_gateway_url', 'http://localhost:3000' );
		update_option( 'wp_pinch_api_token', 'test-token' );

		$filtered_payload = null;
		add_filter( 'wp_pinch_webhook_payload', function ( $payload ) use ( &$filtered_payload ) {
			$filtered_payload = $payload;
			return $payload;
		} );

		Webhook_Dispatcher::dispatch( 'test', 'Filtered test', array( 'key' => 'value' ) );

		$this->assertNotNull( $filtered_payload );
		$this->assertArrayHasKey( 'message', $filtered_payload );
		$this->assertArrayHasKey( 'metadata', $filtered_payload );
		$this->assertEquals( 'test', $filtered_payload['metadata']['event'] );
	}

	/**
	 * Test retry constants.
	 */
	public function test_retry_constants(): void {
		$this->assertEquals( 4, Webhook_Dispatcher::MAX_RETRIES );
		$this->assertCount( 4, Webhook_Dispatcher::RETRY_INTERVALS );
		$this->assertEquals( 300, Webhook_Dispatcher::RETRY_INTERVALS[0] );   // 5 min.
		$this->assertEquals( 1800, Webhook_Dispatcher::RETRY_INTERVALS[1] );  // 30 min.
		$this->assertEquals( 7200, Webhook_Dispatcher::RETRY_INTERVALS[2] );  // 2 hours.
		$this->assertEquals( 43200, Webhook_Dispatcher::RETRY_INTERVALS[3] ); // 12 hours.
	}
}
