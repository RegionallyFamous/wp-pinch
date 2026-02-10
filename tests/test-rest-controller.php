<?php
/**
 * Tests for the REST Controller.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Rest_Controller;
use WP_Pinch\Audit_Table;

/**
 * Test REST API endpoints, permissions, and rate limiting.
 */
class Test_Rest_Controller extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Subscriber user ID (no edit_posts capability).
	 *
	 * @var int
	 */
	private int $subscriber_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private int $editor_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		Audit_Table::create_table();

		$this->admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->editor_id     = $this->factory->user->create( array( 'role' => 'editor' ) );
	}

	/**
	 * Clean up rate-limit transients between tests.
	 */
	public function tear_down(): void {
		delete_transient( 'wp_pinch_rest_rate_' . $this->admin_id );
		delete_transient( 'wp_pinch_rest_rate_' . $this->editor_id );
		delete_transient( 'wp_pinch_rest_rate_' . $this->subscriber_id );

		parent::tear_down();
	}

	// =========================================================================
	// Permission checks
	// =========================================================================

	/**
	 * Test check_permission allows users with edit_posts.
	 */
	public function test_check_permission_allows_editor(): void {
		wp_set_current_user( $this->editor_id );
		$this->assertTrue( Rest_Controller::check_permission() );
	}

	/**
	 * Test check_permission allows administrators.
	 */
	public function test_check_permission_allows_admin(): void {
		wp_set_current_user( $this->admin_id );
		$this->assertTrue( Rest_Controller::check_permission() );
	}

	/**
	 * Test check_permission denies subscribers.
	 */
	public function test_check_permission_denies_subscriber(): void {
		wp_set_current_user( $this->subscriber_id );
		$result = Rest_Controller::check_permission();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test check_permission denies logged-out users.
	 */
	public function test_check_permission_denies_logged_out(): void {
		wp_set_current_user( 0 );
		$result = Rest_Controller::check_permission();
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// =========================================================================
	// Status endpoint
	// =========================================================================

	/**
	 * Test status endpoint returns correct structure when not configured.
	 */
	public function test_handle_status_not_configured(): void {
		wp_set_current_user( $this->admin_id );

		delete_option( 'wp_pinch_gateway_url' );
		delete_option( 'wp_pinch_api_token' );

		$request  = new WP_REST_Request( 'GET', '/wp-pinch/v1/status' );
		$response = Rest_Controller::handle_status( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['configured'] );
		$this->assertFalse( $data['gateway']['connected'] );
		$this->assertArrayHasKey( 'plugin_version', $data );
	}

	/**
	 * SECURITY: Test status endpoint hides gateway URL from non-admin users.
	 */
	public function test_handle_status_hides_gateway_url_from_editor(): void {
		wp_set_current_user( $this->editor_id );

		update_option( 'wp_pinch_gateway_url', 'http://internal.gateway:3000' );
		update_option( 'wp_pinch_api_token', 'test-token' );

		$request  = new WP_REST_Request( 'GET', '/wp-pinch/v1/status' );
		$response = Rest_Controller::handle_status( $request );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'url', $data['gateway'], 'Gateway URL should NOT be visible to editors.' );
	}

	/**
	 * Test status endpoint shows gateway URL to administrators.
	 */
	public function test_handle_status_shows_gateway_url_to_admin(): void {
		wp_set_current_user( $this->admin_id );

		update_option( 'wp_pinch_gateway_url', 'http://internal.gateway:3000' );
		update_option( 'wp_pinch_api_token', 'test-token' );

		$request  = new WP_REST_Request( 'GET', '/wp-pinch/v1/status' );
		$response = Rest_Controller::handle_status( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'url', $data['gateway'] );
		$this->assertStringContainsString( 'internal.gateway', $data['gateway']['url'] );
	}

	// =========================================================================
	// Chat endpoint
	// =========================================================================

	/**
	 * Test chat endpoint returns error when not configured.
	 */
	public function test_handle_chat_not_configured(): void {
		wp_set_current_user( $this->admin_id );

		delete_option( 'wp_pinch_gateway_url' );
		delete_option( 'wp_pinch_api_token' );

		$request = new WP_REST_Request( 'POST', '/wp-pinch/v1/chat' );
		$request->set_param( 'message', 'Hello' );

		$result = Rest_Controller::handle_chat( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_configured', $result->get_error_code() );
	}

	// =========================================================================
	// Rate limiting
	// =========================================================================

	/**
	 * Test rate limit constant value.
	 */
	public function test_rate_limit_constant(): void {
		$this->assertEquals( 10, Rest_Controller::RATE_LIMIT );
	}

	// =========================================================================
	// Route registration
	// =========================================================================

	/**
	 * Test that routes are registered.
	 */
	public function test_routes_registered(): void {
		Rest_Controller::register_routes();

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp-pinch/v1/chat', $routes, 'Chat route should be registered.' );
		$this->assertArrayHasKey( '/wp-pinch/v1/status', $routes, 'Status route should be registered.' );
	}
}
