<?php
/**
 * Tests for the Dashboard_Widget class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Dashboard_Widget;
use WP_Pinch\Audit_Table;

/**
 * Test Dashboard Widget.
 */
class Test_Dashboard_Widget extends WP_UnitTestCase {

	/**
	 * Set up — ensure audit table exists.
	 */
	public function set_up(): void {
		parent::set_up();
		Audit_Table::create_table();
	}

	/**
	 * Test that init registers the expected hooks.
	 */
	public function test_init_registers_hooks(): void {
		Dashboard_Widget::init();

		$this->assertIsInt( has_action( 'wp_dashboard_setup', array( Dashboard_Widget::class, 'register' ) ) );
	}

	/**
	 * Test register adds widget when user has manage_options.
	 *
	 * wp_add_dashboard_widget() requires dashboard screen context.
	 */
	public function test_register_adds_widget(): void {
		if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
			require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		}
		if ( ! function_exists( 'set_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}

		set_current_screen( 'dashboard' );

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		global $wp_meta_boxes;
		$wp_meta_boxes = array();

		Dashboard_Widget::register();

		set_current_screen( 'front' );

		$this->assertArrayHasKey( 'dashboard', $wp_meta_boxes );
		$this->assertArrayHasKey( 'normal', $wp_meta_boxes['dashboard'] );
		$this->assertArrayHasKey( 'core', $wp_meta_boxes['dashboard']['normal'] );
		$this->assertArrayHasKey( 'wp_pinch_activity', $wp_meta_boxes['dashboard']['normal']['core'] );
	}

	/**
	 * Test render with empty audit shows "No activity yet" message.
	 */
	public function test_render_empty_shows_no_activity(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		ob_start();
		Dashboard_Widget::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No activity yet', $output );
		$this->assertStringContainsString( 'Configure WP Pinch', $output );
	}

	/**
	 * Test render with audit items shows activity list.
	 */
	public function test_render_with_items_shows_list(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		Audit_Table::insert( 'ability', 'ability', 'Post "Hello" created via ability.', array( 'post_id' => 1 ) );

		ob_start();
		Dashboard_Widget::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Hello', $output );
		$this->assertStringContainsString( 'created via ability', $output );
		$this->assertStringContainsString( 'wp-pinch-activity-list', $output );
		$this->assertStringContainsString( 'View full audit log', $output );
	}
}
