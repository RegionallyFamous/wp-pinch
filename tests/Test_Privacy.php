<?php
/**
 * Tests for the Privacy class (GDPR).
 *
 * @package WP_Pinch
 */

use WP_Pinch\Privacy;
use WP_Pinch\Audit_Table;

/**
 * Test GDPR data export and erasure.
 */
class Test_Privacy extends WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * Test user email.
	 *
	 * @var string
	 */
	private string $user_email;

	/**
	 * Set up — create audit table and test user.
	 */
	public function set_up(): void {
		parent::set_up();

		Audit_Table::create_table();

		$this->user_id    = $this->factory->user->create( array( 'role' => 'editor' ) );
		$this->user_email = get_userdata( $this->user_id )->user_email;
	}

	// =========================================================================
	// Hook registration
	// =========================================================================

	/**
	 * Test that init registers the expected hooks.
	 */
	public function test_init_registers_hooks(): void {
		Privacy::init();

		$this->assertIsInt( has_action( 'admin_init', array( Privacy::class, 'add_privacy_policy_content' ) ) );
		$this->assertIsInt( has_filter( 'wp_privacy_personal_data_exporters', array( Privacy::class, 'register_exporter' ) ) );
		$this->assertIsInt( has_filter( 'wp_privacy_personal_data_erasers', array( Privacy::class, 'register_eraser' ) ) );
	}

	// =========================================================================
	// Exporter registration
	// =========================================================================

	/**
	 * Test that the exporter is registered correctly.
	 */
	public function test_register_exporter(): void {
		$exporters = Privacy::register_exporter( array() );

		$this->assertArrayHasKey( 'wp-pinch', $exporters );
		$this->assertSame( array( Privacy::class, 'export_personal_data' ), $exporters['wp-pinch']['callback'] );
	}

	/**
	 * Test that the eraser is registered correctly.
	 */
	public function test_register_eraser(): void {
		$erasers = Privacy::register_eraser( array() );

		$this->assertArrayHasKey( 'wp-pinch', $erasers );
		$this->assertSame( array( Privacy::class, 'erase_personal_data' ), $erasers['wp-pinch']['callback'] );
	}

	// =========================================================================
	// Data export
	// =========================================================================

	/**
	 * Test export returns empty when no data exists for the user.
	 */
	public function test_export_empty_for_unknown_user(): void {
		$result = Privacy::export_personal_data( 'nobody@example.com' );

		$this->assertEmpty( $result['data'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Test export returns matching audit entries for a user.
	 */
	public function test_export_returns_user_audit_entries(): void {
		// Insert audit entries for the user.
		Audit_Table::insert(
			'chat_message',
			'chat',
			'User sent a chat message.',
			array( 'user_id' => $this->user_id, 'action' => 'chat' )
		);

		Audit_Table::insert(
			'ability_executed',
			'ability',
			'User ran list-posts.',
			array( 'user_id' => $this->user_id, 'ability' => 'list-posts' )
		);

		$result = Privacy::export_personal_data( $this->user_email );

		$this->assertCount( 2, $result['data'] );
		$this->assertTrue( $result['done'] );

		// Verify structure.
		$item = $result['data'][0];
		$this->assertSame( 'wp-pinch-audit', $item['group_id'] );
		$this->assertStringStartsWith( 'wp-pinch-audit-', $item['item_id'] );

		// Verify all expected fields are present.
		$field_names = array_column( $item['data'], 'name' );
		$this->assertContains( 'Event Type', $field_names );
		$this->assertContains( 'Source', $field_names );
		$this->assertContains( 'Description', $field_names );
		$this->assertContains( 'Context', $field_names );
		$this->assertContains( 'Date', $field_names );
	}

	/**
	 * Test export does not return entries for other users.
	 */
	public function test_export_excludes_other_users(): void {
		$other_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		Audit_Table::insert(
			'chat_message',
			'chat',
			'Other user message.',
			array( 'user_id' => $other_user_id )
		);

		$result = Privacy::export_personal_data( $this->user_email );

		$this->assertEmpty( $result['data'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Test export pagination signals done=false when batch is full.
	 */
	public function test_export_pagination(): void {
		// Insert BATCH_SIZE + 1 entries.
		for ( $i = 0; $i <= Privacy::BATCH_SIZE; $i++ ) {
			Audit_Table::insert(
				'test_event',
				'test',
				"Entry {$i}",
				array( 'user_id' => $this->user_id )
			);
		}

		// Page 1 should return BATCH_SIZE items and done=false.
		$page1 = Privacy::export_personal_data( $this->user_email, 1 );
		$this->assertCount( Privacy::BATCH_SIZE, $page1['data'] );
		$this->assertFalse( $page1['done'] );

		// Page 2 should return the remaining item and done=true.
		$page2 = Privacy::export_personal_data( $this->user_email, 2 );
		$this->assertCount( 1, $page2['data'] );
		$this->assertTrue( $page2['done'] );
	}

	/**
	 * Test export does not match partial user IDs (e.g. user 1 vs user 10).
	 */
	public function test_export_no_partial_user_id_match(): void {
		// Create user with a higher ID to test partial matching.
		$user_10 = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$user_10_email = get_userdata( $user_10 )->user_email;

		// Insert for both users.
		Audit_Table::insert( 'test', 'test', 'User A', array( 'user_id' => $this->user_id ) );
		Audit_Table::insert( 'test', 'test', 'User B', array( 'user_id' => $user_10 ) );

		// Export for user A should only get user A entries.
		$result = Privacy::export_personal_data( $this->user_email );
		foreach ( $result['data'] as $item ) {
			$desc = '';
			foreach ( $item['data'] as $field ) {
				if ( 'Description' === $field['name'] ) {
					$desc = $field['value'];
				}
			}
			$this->assertSame( 'User A', $desc );
		}
	}

	// =========================================================================
	// Data erasure
	// =========================================================================

	/**
	 * Test erasure returns zero for unknown user.
	 */
	public function test_erase_empty_for_unknown_user(): void {
		$result = Privacy::erase_personal_data( 'nobody@example.com' );

		$this->assertSame( 0, $result['items_removed'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Test erasure deletes user's audit entries.
	 */
	public function test_erase_deletes_user_entries(): void {
		Audit_Table::insert( 'test', 'test', 'To delete', array( 'user_id' => $this->user_id ) );
		Audit_Table::insert( 'test', 'test', 'To delete 2', array( 'user_id' => $this->user_id ) );

		$result = Privacy::erase_personal_data( $this->user_email );

		$this->assertSame( 2, $result['items_removed'] );
		$this->assertTrue( $result['done'] );

		// Verify entries are gone.
		$export = Privacy::export_personal_data( $this->user_email );
		$this->assertEmpty( $export['data'] );
	}

	/**
	 * Test erasure does not delete other users' entries.
	 */
	public function test_erase_preserves_other_users(): void {
		$other_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		Audit_Table::insert( 'test', 'test', 'My entry', array( 'user_id' => $this->user_id ) );
		Audit_Table::insert( 'test', 'test', 'Other entry', array( 'user_id' => $other_user_id ) );

		Privacy::erase_personal_data( $this->user_email );

		// Other user's entry should still exist.
		$other_email = get_userdata( $other_user_id )->user_email;
		$export = Privacy::export_personal_data( $other_email );
		$this->assertCount( 1, $export['data'] );
	}

	/**
	 * Test erasure handles batch deletion (done=false when more remain).
	 */
	public function test_erase_pagination(): void {
		// Insert more than BATCH_SIZE entries.
		for ( $i = 0; $i <= Privacy::BATCH_SIZE; $i++ ) {
			Audit_Table::insert( 'test', 'test', "Entry {$i}", array( 'user_id' => $this->user_id ) );
		}

		$result = Privacy::erase_personal_data( $this->user_email );

		$this->assertSame( Privacy::BATCH_SIZE, $result['items_removed'] );
		$this->assertFalse( $result['done'] );

		// Second pass should clean up the rest.
		$result2 = Privacy::erase_personal_data( $this->user_email );
		$this->assertSame( 1, $result2['items_removed'] );
		$this->assertTrue( $result2['done'] );
	}
}
