<?php
/**
 * Tests for the Audit Table.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Audit_Table;

/**
 * Test audit log operations.
 */
class Test_Audit_Table extends WP_UnitTestCase {

	/**
	 * Set up — ensure audit table exists.
	 */
	public function set_up(): void {
		parent::set_up();
		Audit_Table::create_table();
	}

	/**
	 * Test table name includes prefix.
	 */
	public function test_table_name(): void {
		global $wpdb;
		$expected = $wpdb->prefix . 'wp_pinch_audit_log';
		$this->assertEquals( $expected, Audit_Table::table_name() );
	}

	/**
	 * Test inserting an audit entry.
	 */
	public function test_insert(): void {
		$id = Audit_Table::insert( 'test_event', 'test', 'Test message', array( 'key' => 'value' ) );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test querying audit entries.
	 */
	public function test_query(): void {
		Audit_Table::insert( 'event_a', 'source_x', 'Message A' );
		Audit_Table::insert( 'event_b', 'source_y', 'Message B' );
		Audit_Table::insert( 'event_a', 'source_x', 'Message C' );

		$result = Audit_Table::query();
		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThanOrEqual( 3, $result['total'] );

		// Filter by event type.
		$filtered = Audit_Table::query( array( 'event_type' => 'event_a' ) );
		$this->assertGreaterThanOrEqual( 2, $filtered['total'] );
	}

	/**
	 * Test the audit entry filter (suppress).
	 */
	public function test_filter_suppress(): void {
		add_filter( 'wp_pinch_audit_entry', '__return_false' );

		$id = Audit_Table::insert( 'suppressed', 'test', 'Should not be stored' );
		$this->assertFalse( $id );

		remove_filter( 'wp_pinch_audit_entry', '__return_false' );
	}

	/**
	 * Test retention constant.
	 */
	public function test_retention_days(): void {
		$this->assertEquals( 90, Audit_Table::RETENTION_DAYS );
	}
}
