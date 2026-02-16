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

	/**
	 * Test context is stored as JSON and returned decoded (audit enhancements: diff, request_summary).
	 */
	public function test_insert_and_query_context(): void {
		$context = array(
			'post_id'         => 42,
			'diff'            => array(
				'title_length_before'   => 10,
				'title_length_after'    => 15,
				'content_length_before' => 100,
				'content_length_after'  => 200,
			),
			'request_summary' => array( 'title' => 'Test' ),
			'result_summary'   => array( 'post_id' => 42 ),
		);

		$id = Audit_Table::insert( 'post_updated', 'ability', 'Post 42 updated.', $context );
		$this->assertGreaterThan( 0, $id );

		$result = Audit_Table::query( array( 'event_type' => 'post_updated' ) );
		$this->assertGreaterThanOrEqual( 1, $result['total'] );

		$found = null;
		foreach ( $result['items'] as $item ) {
			if ( (int) $item['id'] === $id ) {
				$found = $item;
				break;
			}
		}
		$this->assertNotNull( $found );
		$this->assertIsArray( $found['context'] );
		$this->assertArrayHasKey( 'diff', $found['context'] );
		$this->assertArrayHasKey( 'title_length_before', $found['context']['diff'] );
		$this->assertEquals( 10, $found['context']['diff']['title_length_before'] );
		$this->assertArrayHasKey( 'request_summary', $found['context'] );
		$this->assertEquals( array( 'title' => 'Test' ), $found['context']['request_summary'] );
	}
}
