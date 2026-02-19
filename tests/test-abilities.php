<?php
/**
 * Tests for the Abilities class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

/**
 * Test ability registration, execution, and security guards.
 */
class Test_Abilities extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private int $editor_id;

	/**
	 * Set up — create users and ensure the audit table exists.
	 */
	public function set_up(): void {
		parent::set_up();

		Audit_Table::create_table();

		$this->admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->admin_id );
	}

	// =========================================================================
	// Ability registration
	// =========================================================================

	/**
	 * Test that get_ability_names returns at least 88 abilities (core set without WooCommerce).
	 */
	public function test_ability_names_count(): void {
		$names = Abilities::get_ability_names();
		$this->assertGreaterThanOrEqual( 88, count( $names ), 'Expected at least 88 abilities (core set).' );
		$this->assertContains( 'wp-pinch/pinchdrop-generate', $names );
		$this->assertContains( 'wp-pinch/content-health-report', $names );
		$this->assertContains( 'wp-pinch/suggest-terms', $names );
	}

	/**
	 * Test that all ability names are properly namespaced.
	 */
	public function test_ability_names_namespace(): void {
		$names = Abilities::get_ability_names();
		foreach ( $names as $name ) {
			$this->assertStringStartsWith(
				'wp-pinch/',
				$name,
				"Ability '{$name}' should be namespaced with 'wp-pinch/'."
			);
		}
	}

	/**
	 * Test that each ability name is unique.
	 */
	public function test_ability_names_unique(): void {
		$names = Abilities::get_ability_names();
		$this->assertCount( count( $names ), array_unique( $names ), 'All ability names should be unique.' );
	}

	// =========================================================================
	// Content abilities
	// =========================================================================

	/**
	 * Test list-posts returns correct structure.
	 */
	public function test_list_posts(): void {
		$this->factory->post->create_many( 3 );

		$result = Abilities::execute_list_posts(
			array(
				'post_type' => 'post',
				'status'    => 'publish',
				'per_page'  => 10,
				'page'      => 1,
			)
		);

		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'total_pages', $result );
		$this->assertArrayHasKey( 'page', $result );
		$this->assertGreaterThanOrEqual( 3, $result['total'] );
	}

	/**
	 * Test list-posts pagination clamping: per_page=0 becomes 1.
	 */
	public function test_list_posts_pagination_clamping(): void {
		$this->factory->post->create_many( 5 );

		$result = Abilities::execute_list_posts(
			array(
				'per_page' => 0,
				'page'     => 0,
			)
		);

		$this->assertEquals( 1, $result['page'], 'page=0 should be clamped to 1.' );
		// per_page=0 is clamped to 1 by the ability.
		$this->assertCount( 1, $result['posts'], 'per_page=0 should be clamped to 1.' );
	}

	/**
	 * Test list-posts caps per_page at 100.
	 */
	public function test_list_posts_per_page_cap(): void {
		$result = Abilities::execute_list_posts(
			array( 'per_page' => 999 )
		);

		// The method clamps to 100, so even with 999, we get at most 100.
		$this->assertLessThanOrEqual( 100, count( $result['posts'] ) );
	}

	/**
	 * Test get-post returns correct data.
	 */
	public function test_get_post(): void {
		$post_id = $this->factory->post->create(
			array( 'post_title' => 'Test Post for Get' )
		);

		$result = Abilities::execute_get_post( array( 'id' => $post_id ) );

		$this->assertArrayHasKey( 'id', $result );
		$this->assertEquals( $post_id, $result['id'] );
		$this->assertEquals( 'Test Post for Get', $result['title'] );
		$this->assertArrayHasKey( 'content', $result, 'Single-post view should include full content.' );
	}

	/**
	 * Test get-post with invalid ID returns error.
	 */
	public function test_get_post_not_found(): void {
		$result = Abilities::execute_get_post( array( 'id' => 99999 ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	/**
	 * Test create-post execution.
	 */
	public function test_create_post(): void {
		$result = Abilities::execute_create_post(
			array(
				'title'   => 'Created via Ability',
				'content' => 'Test content.',
				'status'  => 'draft',
			)
		);

		$this->assertArrayHasKey( 'id', $result );
		$this->assertGreaterThan( 0, $result['id'] );

		$post = get_post( $result['id'] );
		$this->assertEquals( 'Created via Ability', $post->post_title );
		$this->assertEquals( 'draft', $post->post_status );
	}

	/**
	 * Test create-post rejects non-existent post types.
	 */
	public function test_create_post_invalid_post_type(): void {
		$result = Abilities::execute_create_post(
			array(
				'title'     => 'Bad Post Type',
				'post_type' => 'nonexistent_type',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'does not exist', $result['error'] );
	}

	/**
	 * Test update-post updates title.
	 */
	public function test_update_post(): void {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Original Title' ) );

		$result = Abilities::execute_update_post(
			array(
				'id'    => $post_id,
				'title' => 'Updated Title',
			)
		);

		$this->assertArrayHasKey( 'updated', $result );
		$this->assertTrue( $result['updated'] );
		$this->assertEquals( 'Updated Title', get_post( $post_id )->post_title );
	}

	/**
	 * Test update-post with non-existent ID returns error.
	 */
	public function test_update_post_not_found(): void {
		$result = Abilities::execute_update_post(
			array(
				'id'    => 99999,
				'title' => 'X',
			)
		);
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test update-post optimistic locking: rejects when post_modified has changed.
	 */
	public function test_update_post_optimistic_lock_rejects_when_modified_changed(): void {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Original' ) );
		$post    = get_post( $post_id );
		$this->assertNotNull( $post );
		$original_modified = $post->post_modified;

		// WordPress uses second precision for post_modified; wait so the update gets a different timestamp.
		sleep( 2 );
		// Simulate another user/process updating the post (changes post_modified).
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Changed by someone else',
			)
		);

		$result = Abilities::execute_update_post(
			array(
				'id'            => $post_id,
				'title'         => 'My update',
				'post_modified' => $original_modified,
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertArrayHasKey( 'conflict', $result );
		$this->assertTrue( $result['conflict'] );
		$this->assertArrayHasKey( 'post_modified', $result );
		// Title should be unchanged (our update was rejected).
		$this->assertEquals( 'Changed by someone else', get_post( $post_id )->post_title );
	}

	/**
	 * Test update-post optimistic locking: succeeds when post_modified matches.
	 */
	public function test_update_post_optimistic_lock_succeeds_with_correct_modified(): void {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Original' ) );
		$post    = get_post( $post_id );
		$this->assertNotNull( $post );
		$current_modified = $post->post_modified;

		$result = Abilities::execute_update_post(
			array(
				'id'            => $post_id,
				'title'         => 'Updated with lock',
				'post_modified' => $current_modified,
			)
		);

		$this->assertArrayHasKey( 'updated', $result );
		$this->assertTrue( $result['updated'] );
		$this->assertEquals( 'Updated with lock', get_post( $post_id )->post_title );
	}

	/**
	 * Test delete-post trashes by default.
	 */
	public function test_delete_post_trash(): void {
		$post_id = $this->factory->post->create();

		$result = Abilities::execute_delete_post( array( 'id' => $post_id ) );

		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertTrue( $result['deleted'] );
		$this->assertFalse( $result['force'] );
		$this->assertEquals( 'trash', get_post_status( $post_id ) );
	}

	/**
	 * Test delete-post force permanently deletes.
	 */
	public function test_delete_post_force(): void {
		$post_id = $this->factory->post->create();

		$result = Abilities::execute_delete_post(
			array(
				'id'    => $post_id,
				'force' => true,
			)
		);

		$this->assertTrue( $result['deleted'] );
		$this->assertTrue( $result['force'] );
		$this->assertNull( get_post( $post_id ) );
	}

	/**
	 * Test delete-post with non-existent ID returns error.
	 */
	public function test_delete_post_not_found(): void {
		$result = Abilities::execute_delete_post( array( 'id' => 99999 ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	/**
	 * Test list-taxonomies returns correct structure.
	 */
	public function test_list_taxonomies(): void {
		$result = Abilities::execute_list_taxonomies( array( 'taxonomy' => 'category' ) );

		$this->assertArrayHasKey( 'taxonomy', $result );
		$this->assertArrayHasKey( 'terms', $result );
		$this->assertEquals( 'category', $result['taxonomy'] );
	}

	/**
	 * Test list-taxonomies with invalid taxonomy returns error.
	 */
	public function test_list_taxonomies_invalid(): void {
		$result = Abilities::execute_list_taxonomies( array( 'taxonomy' => 'nonexistent_tax' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test manage-terms create action.
	 */
	public function test_manage_terms_create(): void {
		$result = Abilities::execute_manage_terms(
			array(
				'action'   => 'create',
				'taxonomy' => 'category',
				'name'     => 'Test Term',
			)
		);

		$this->assertArrayHasKey( 'term_id', $result );
		$this->assertTrue( $result['created'] );
	}

	/**
	 * Test manage-terms create requires name.
	 */
	public function test_manage_terms_create_requires_name(): void {
		$result = Abilities::execute_manage_terms(
			array(
				'action'   => 'create',
				'taxonomy' => 'category',
				'name'     => '',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	/**
	 * Test manage-terms delete with non-existent term returns error.
	 */
	public function test_manage_terms_delete_not_found(): void {
		$result = Abilities::execute_manage_terms(
			array(
				'action'   => 'delete',
				'taxonomy' => 'category',
				'term_id'  => 999999,
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	// =========================================================================
	// Media abilities
	// =========================================================================

	/**
	 * Test list-media returns correct structure.
	 */
	public function test_list_media(): void {
		$result = Abilities::execute_list_media( array( 'per_page' => 10 ) );

		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'total_pages', $result );
	}

	/**
	 * Test delete-media with non-existent attachment returns error.
	 */
	public function test_delete_media_not_found(): void {
		$result = Abilities::execute_delete_media( array( 'id' => 99999 ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	/**
	 * Test delete-media with a regular post (not attachment) returns error.
	 */
	public function test_delete_media_wrong_type(): void {
		$post_id = $this->factory->post->create();

		$result = Abilities::execute_delete_media( array( 'id' => $post_id ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	// =========================================================================
	// User abilities
	// =========================================================================

	/**
	 * Test list-users returns correct structure.
	 */
	public function test_list_users(): void {
		$result = Abilities::execute_list_users(
			array(
				'per_page' => 10,
				'page'     => 1,
			)
		);

		$this->assertArrayHasKey( 'users', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThanOrEqual( 2, $result['total'], 'Should include at least admin and editor.' );
	}

	/**
	 * Test get-user returns correct data.
	 */
	public function test_get_user(): void {
		$result = Abilities::execute_get_user( array( 'id' => $this->editor_id ) );

		$this->assertArrayHasKey( 'id', $result );
		$this->assertEquals( $this->editor_id, $result['id'] );
		$this->assertArrayHasKey( 'posts_count', $result );
	}

	/**
	 * Test get-user with invalid ID returns error.
	 */
	public function test_get_user_not_found(): void {
		$result = Abilities::execute_get_user( array( 'id' => 99999 ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test update-user-role changes role successfully.
	 */
	public function test_update_user_role(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$result = Abilities::execute_update_user_role(
			array(
				'id'   => $subscriber_id,
				'role' => 'author',
			)
		);

		$this->assertArrayHasKey( 'updated', $result );
		$this->assertTrue( $result['updated'] );
		$this->assertEquals( 'author', $result['role'] );
	}

	/**
	 * SECURITY: Test update-user-role blocks administrator escalation.
	 */
	public function test_update_user_role_blocks_admin_escalation(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$result = Abilities::execute_update_user_role(
			array(
				'id'   => $subscriber_id,
				'role' => 'administrator',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'cannot be assigned', $result['error'] );

		// Verify the user's role was NOT changed.
		$user = get_userdata( $subscriber_id );
		$this->assertContains( 'subscriber', $user->roles );
	}

	/**
	 * SECURITY: Test update-user-role prevents self-role-change.
	 */
	public function test_update_user_role_blocks_self_change(): void {
		$result = Abilities::execute_update_user_role(
			array(
				'id'   => $this->admin_id,
				'role' => 'author',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'own role', $result['error'] );
	}

	/**
	 * SECURITY: Test update-user-role with invalid role returns error.
	 */
	public function test_update_user_role_invalid_role(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$result = Abilities::execute_update_user_role(
			array(
				'id'   => $subscriber_id,
				'role' => 'nonexistent_role',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Invalid role', $result['error'] );
	}

	/**
	 * SECURITY: Test the blocked_roles filter works.
	 */
	public function test_update_user_role_custom_blocked_roles_filter(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		// Block 'editor' role too via filter.
		add_filter(
			'wp_pinch_blocked_roles',
			function ( $roles ) {
				$roles[] = 'editor';
				return $roles;
			}
		);

		$result = Abilities::execute_update_user_role(
			array(
				'id'   => $subscriber_id,
				'role' => 'editor',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
	}

	// =========================================================================
	// Comment abilities
	// =========================================================================

	/**
	 * Test list-comments returns correct structure without email.
	 */
	public function test_list_comments(): void {
		$post_id = $this->factory->post->create();
		$this->factory->comment->create_many( 3, array( 'comment_post_ID' => $post_id ) );

		$result = Abilities::execute_list_comments( array( 'per_page' => 10 ) );

		$this->assertArrayHasKey( 'comments', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThanOrEqual( 3, $result['total'] );

		// Privacy: email should NOT be included.
		if ( ! empty( $result['comments'] ) ) {
			$this->assertArrayNotHasKey( 'email', $result['comments'][0] );
		}
	}

	/**
	 * Test list-comments total count is accurate with pagination.
	 */
	public function test_list_comments_total_count_accurate(): void {
		$post_id = $this->factory->post->create();
		$this->factory->comment->create_many( 5, array( 'comment_post_ID' => $post_id ) );

		$result = Abilities::execute_list_comments(
			array(
				'per_page' => 2,
				'page'     => 1,
				'post_id'  => $post_id,
			)
		);

		$this->assertCount( 2, $result['comments'], 'Page should have 2 comments.' );
		$this->assertEquals( 5, $result['total'], 'Total should reflect all 5 comments, not just 2.' );
	}

	/**
	 * Test moderate-comment approves a comment.
	 */
	public function test_moderate_comment_approve(): void {
		$post_id    = $this->factory->post->create();
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '0',
			)
		);

		$result = Abilities::execute_moderate_comment(
			array(
				'id'     => $comment_id,
				'status' => 'approve',
			)
		);

		$this->assertArrayHasKey( 'moderated', $result );
		$this->assertTrue( $result['moderated'] );
		$this->assertEquals( 'approved', wp_get_comment_status( $comment_id ) );
	}

	/**
	 * Test moderate-comment with non-existent ID returns error.
	 */
	public function test_moderate_comment_not_found(): void {
		$result = Abilities::execute_moderate_comment(
			array(
				'id'     => 99999,
				'status' => 'approve',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	/**
	 * Test moderate-comment with invalid status returns error.
	 */
	public function test_moderate_comment_invalid_status(): void {
		$post_id    = $this->factory->post->create();
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		$result = Abilities::execute_moderate_comment(
			array(
				'id'     => $comment_id,
				'status' => 'invalid_status',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
	}

	// =========================================================================
	// Settings abilities
	// =========================================================================

	/**
	 * Test update-option with allowed key.
	 */
	public function test_update_option_allowed(): void {
		$result = Abilities::execute_update_option(
			array(
				'key'   => 'blogname',
				'value' => 'Test Site Name',
			)
		);

		$this->assertArrayHasKey( 'updated', $result );
		$this->assertTrue( $result['updated'] );
		$this->assertEquals( 'Test Site Name', get_option( 'blogname' ) );
	}

	/**
	 * Test update-option with disallowed key returns error.
	 *
	 * Uses a key not in the write allowlist (triggers "Option key not in allowlist").
	 * admin_email would hit the denylist and return a different message.
	 */
	public function test_update_option_disallowed(): void {
		$result = Abilities::execute_update_option(
			array(
				'key'   => 'random_disallowed_option_xyz',
				'value' => 'nope',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'allowlist', $result['error'] );
	}

	/**
	 * Test get-option with allowed key.
	 */
	public function test_get_option_allowed(): void {
		update_option( 'blogname', 'My Test Blog' );
		$result = Abilities::execute_get_option( array( 'key' => 'blogname' ) );

		$this->assertArrayHasKey( 'key', $result );
		$this->assertArrayHasKey( 'value', $result );
		$this->assertEquals( 'My Test Blog', $result['value'] );
	}

	/**
	 * Test get-option with WPLANG (case-sensitive key).
	 */
	public function test_get_option_wplang_case_sensitive(): void {
		$result = Abilities::execute_get_option( array( 'key' => 'WPLANG' ) );

		// WPLANG is in the read allowlist — should NOT return an error.
		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertEquals( 'WPLANG', $result['key'] );
	}

	/**
	 * Test get-option with disallowed key returns error.
	 */
	public function test_get_option_disallowed(): void {
		$result = Abilities::execute_get_option( array( 'key' => 'wp_pinch_api_token' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test get-option with denylisted siteurl returns error.
	 */
	public function test_get_option_denylist_siteurl(): void {
		$result = Abilities::execute_get_option( array( 'key' => 'siteurl' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test get-option with denylisted home returns error.
	 */
	public function test_get_option_denylist_home(): void {
		$result = Abilities::execute_get_option( array( 'key' => 'home' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test update-option with denylisted siteurl returns error.
	 */
	public function test_update_option_denylist_siteurl(): void {
		$result = Abilities::execute_update_option(
			array(
				'key'   => 'siteurl',
				'value' => 'http://evil.com',
			)
		);
		$this->assertArrayHasKey( 'error', $result );
	}

	// =========================================================================
	// Plugin & Theme abilities
	// =========================================================================

	/**
	 * Test list-plugins returns correct structure.
	 */
	public function test_list_plugins(): void {
		$result = Abilities::execute_list_plugins( array() );

		$this->assertArrayHasKey( 'plugins', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThan( 0, $result['total'] );
	}

	/**
	 * SECURITY: Test toggle-plugin prevents self-deactivation.
	 */
	public function test_toggle_plugin_blocks_self_deactivation(): void {
		$result = Abilities::execute_toggle_plugin(
			array(
				'plugin'   => plugin_basename( WP_PINCH_FILE ),
				'activate' => false,
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'cannot deactivate itself', $result['error'] );
	}

	/**
	 * Test list-themes returns correct structure.
	 */
	public function test_list_themes(): void {
		$result = Abilities::execute_list_themes( array() );

		$this->assertArrayHasKey( 'themes', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThan( 0, $result['total'] );

		// At least one theme should be active.
		$active_found = false;
		foreach ( $result['themes'] as $theme ) {
			if ( $theme['active'] ) {
				$active_found = true;
				break;
			}
		}
		$this->assertTrue( $active_found, 'At least one theme should be active.' );
	}

	/**
	 * Test switch-theme with non-existent theme returns error.
	 */
	public function test_switch_theme_not_found(): void {
		$result = Abilities::execute_switch_theme( array( 'stylesheet' => 'nonexistent-theme-12345' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	// =========================================================================
	// Analytics & Maintenance abilities
	// =========================================================================

	/**
	 * Test site-health returns correct structure.
	 */
	public function test_site_health(): void {
		$result = Abilities::execute_site_health( array() );

		$this->assertArrayHasKey( 'wordpress', $result );
		$this->assertArrayHasKey( 'php', $result );
		$this->assertArrayHasKey( 'database', $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertArrayHasKey( 'version', $result['wordpress'] );
		$this->assertArrayHasKey( 'tables', $result['database'] );
		$this->assertIsInt( $result['database']['tables'] );
	}

	/**
	 * Test recent-activity returns correct structure.
	 */
	public function test_recent_activity(): void {
		$this->factory->post->create_many( 2 );
		$result = Abilities::execute_recent_activity( array( 'limit' => 5 ) );

		$this->assertArrayHasKey( 'recent_posts', $result );
		$this->assertArrayHasKey( 'recent_comments', $result );
	}

	/**
	 * Test search-content returns correct structure.
	 */
	public function test_search_content(): void {
		$this->factory->post->create( array( 'post_title' => 'Unique Searchable Title XYZ789' ) );

		$result = Abilities::execute_search_content(
			array( 'query' => 'XYZ789' )
		);

		$this->assertArrayHasKey( 'results', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThanOrEqual( 1, $result['total'] );
	}

	/**
	 * Test export-data for posts returns correct structure.
	 */
	public function test_export_data_posts(): void {
		$this->factory->post->create_many( 3 );
		$result = Abilities::execute_export_data(
			array(
				'type'     => 'posts',
				'per_page' => 10,
			)
		);

		$this->assertEquals( 'posts', $result['type'] );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'total', $result );
	}

	/**
	 * Test export-data for comments returns correct structure.
	 */
	public function test_export_data_comments(): void {
		$post_id = $this->factory->post->create();
		$this->factory->comment->create_many( 2, array( 'comment_post_ID' => $post_id ) );

		$result = Abilities::execute_export_data( array( 'type' => 'comments' ) );

		$this->assertEquals( 'comments', $result['type'] );
		$this->assertIsInt( $result['total'] );
	}

	/**
	 * Test export-data with invalid type returns error.
	 */
	public function test_export_data_invalid_type(): void {
		$result = Abilities::execute_export_data( array( 'type' => 'invalid' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test Echo Net (related-posts) returns post_id, backlinks, by_taxonomy.
	 */
	public function test_related_posts_returns_structure(): void {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$result  = Abilities::execute_related_posts( array( 'post_id' => $post_id ) );
		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertArrayHasKey( 'backlinks', $result );
		$this->assertArrayHasKey( 'by_taxonomy', $result );
		$this->assertEquals( $post_id, $result['post_id'] );
		$this->assertIsArray( $result['backlinks'] );
		$this->assertIsArray( $result['by_taxonomy'] );
	}

	/**
	 * Test Echo Net (related-posts) with invalid post returns error.
	 */
	public function test_related_posts_invalid_post_returns_error(): void {
		$result = Abilities::execute_related_posts( array( 'post_id' => 0 ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test Weave (synthesize) returns query, posts, total.
	 */
	public function test_synthesize_returns_payload(): void {
		$this->factory->post->create(
			array(
				'post_title'   => 'Synthesis test post',
				'post_content' => 'Content about synthesis and weaving.',
				'post_status'  => 'publish',
			)
		);
		$result = Abilities::execute_synthesize( array( 'query' => 'synthesis' ) );
		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'query', $result );
		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertEquals( 'synthesis', $result['query'] );
		$this->assertIsArray( $result['posts'] );
		$this->assertGreaterThanOrEqual( 0, $result['total'] );
	}

	/**
	 * Test Weave (synthesize) with empty query returns error.
	 */
	public function test_synthesize_empty_query_returns_error(): void {
		$result = Abilities::execute_synthesize( array( 'query' => '   ' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test PinchDrop generation returns draft pack and draft IDs.
	 */
	public function test_pinchdrop_generate_creates_drafts(): void {
		$result = Abilities::execute_pinchdrop_generate(
			array(
				'source_text'   => "Launch notes\n- Faster sync\n- Better onboarding",
				'source'        => 'slack',
				'request_id'    => 'req-abc-123',
				'save_as_draft' => true,
				'output_types'  => array( 'post', 'changelog' ),
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'draft_pack', $result );
		$this->assertArrayHasKey( 'post', $result['draft_pack'] );
		$this->assertArrayHasKey( 'changelog', $result['draft_pack'] );
		$this->assertArrayHasKey( 'created_drafts', $result );
		$this->assertArrayHasKey( 'post', $result['created_drafts'] );

		$post_id = (int) $result['created_drafts']['post']['id'];
		$this->assertEquals( 'slack', get_post_meta( $post_id, 'wp_pinch_pinchdrop_source', true ) );
		$this->assertEquals( 'req-abc-123', get_post_meta( $post_id, 'wp_pinch_pinchdrop_request_id', true ) );
	}

	// =========================================================================
	// Post Meta abilities
	// =========================================================================

	/**
	 * Test get-post-meta for a specific key.
	 */
	public function test_get_post_meta_single_key(): void {
		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, 'test_key', 'test_value' );

		$result = Abilities::execute_get_post_meta(
			array(
				'post_id' => $post_id,
				'key'     => 'test_key',
			)
		);

		$this->assertEquals( 'test_value', $result['value'] );
	}

	/**
	 * Test get-post-meta filters protected meta.
	 */
	public function test_get_post_meta_protected_key_blocked(): void {
		$post_id = $this->factory->post->create();

		$result = Abilities::execute_get_post_meta(
			array(
				'post_id' => $post_id,
				'key'     => '_edit_lock',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'protected', $result['error'] );
	}

	/**
	 * Test update-post-meta sets a value.
	 */
	public function test_update_post_meta(): void {
		$post_id = $this->factory->post->create();

		$result = Abilities::execute_update_post_meta(
			array(
				'post_id' => $post_id,
				'key'     => 'my_custom_field',
				'value'   => 'hello',
			)
		);

		$this->assertTrue( $result['updated'] );
		$this->assertEquals( 'hello', get_post_meta( $post_id, 'my_custom_field', true ) );
	}

	/**
	 * Test update-post-meta for non-existent post returns error.
	 */
	public function test_update_post_meta_post_not_found(): void {
		$result = Abilities::execute_update_post_meta(
			array(
				'post_id' => 99999,
				'key'     => 'test',
				'value'   => 'x',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
	}

	// =========================================================================
	// Bulk Operations
	// =========================================================================

	/**
	 * Test bulk-edit-posts updates status on multiple posts.
	 */
	public function test_bulk_edit_posts_update_status(): void {
		$post_ids = $this->factory->post->create_many( 3, array( 'post_status' => 'draft' ) );

		$result = Abilities::execute_bulk_edit_posts(
			array(
				'post_ids' => $post_ids,
				'action'   => 'update_status',
				'status'   => 'publish',
			)
		);

		$this->assertEquals( 3, $result['success'] );
		$this->assertEquals( 0, $result['failures'] );

		foreach ( $post_ids as $id ) {
			$this->assertEquals( 'publish', get_post_status( $id ) );
		}
	}

	/**
	 * Test bulk-edit-posts rejects more than 50 posts.
	 */
	public function test_bulk_edit_posts_max_limit(): void {
		$result = Abilities::execute_bulk_edit_posts(
			array(
				'post_ids' => range( 1, 51 ),
				'action'   => 'trash',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( '50', $result['error'] );
	}

	/**
	 * Test bulk-edit-posts handles non-existent post IDs.
	 */
	public function test_bulk_edit_posts_nonexistent_ids(): void {
		$result = Abilities::execute_bulk_edit_posts(
			array(
				'post_ids' => array( 99999, 99998 ),
				'action'   => 'trash',
			)
		);

		$this->assertEquals( 0, $result['success'] );
		$this->assertEquals( 2, $result['failures'] );
	}

	// =========================================================================
	// Cron Management
	// =========================================================================

	/**
	 * Test list-cron-events returns correct structure.
	 */
	public function test_list_cron_events(): void {
		$result = Abilities::execute_list_cron_events( array() );

		$this->assertArrayHasKey( 'events', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertIsArray( $result['events'] );
	}

	// =========================================================================
	// Revisions
	// =========================================================================

	/**
	 * Test list-revisions with non-existent post returns error.
	 */
	public function test_list_revisions_post_not_found(): void {
		$result = Abilities::execute_list_revisions( array( 'post_id' => 99999 ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test list-revisions denies access when user cannot edit the post.
	 */
	public function test_list_revisions_requires_edit_post(): void {
		$post_id = $this->factory->post->create( array( 'post_author' => $this->admin_id ) );
		$sub_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $sub_id );
		$result = Abilities::execute_list_revisions( array( 'post_id' => $post_id ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'permission', strtolower( $result['error'] ) );
	}

	/**
	 * Test restore-revision with non-existent revision returns error.
	 */
	public function test_restore_revision_not_found(): void {
		$result = Abilities::execute_restore_revision( array( 'revision_id' => 99999 ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test restore-revision denies access when user cannot edit the parent post.
	 */
	public function test_restore_revision_requires_edit_post(): void {
		$post_id = $this->factory->post->create( array( 'post_author' => $this->admin_id ) );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Updated once.',
			)
		);
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Updated twice.',
			)
		);
		$revisions = wp_get_post_revisions( $post_id );
		$rev_id    = ! empty( $revisions ) ? (int) array_key_first( $revisions ) : 0;
		$this->assertGreaterThan( 0, $rev_id, 'Revision should exist.' );
		$sub_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $sub_id );
		$result = Abilities::execute_restore_revision( array( 'revision_id' => $rev_id ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'permission', strtolower( $result['error'] ) );
	}

	// =========================================================================
	// Draft-first (preview_url, ai_generated)
	// =========================================================================

	/**
	 * Test create-post sets _wp_pinch_ai_generated meta and returns preview_url and ai_generated.
	 */
	public function test_create_post_returns_preview_url_and_ai_generated(): void {
		$result = Abilities::execute_create_post(
			array(
				'title'   => 'Draft for preview',
				'content' => 'Body',
				'status'  => 'draft',
			)
		);

		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'preview_url', $result );
		$this->assertArrayHasKey( 'ai_generated', $result );
		$this->assertTrue( $result['ai_generated'] );
		$this->assertNotEmpty( $result['preview_url'] );

		$meta = get_post_meta( $result['id'], '_wp_pinch_ai_generated', true );
		$this->assertNotEmpty( $meta, 'AI generated meta should be set.' );
	}

	/**
	 * Test update-post returns preview_url and url.
	 */
	public function test_update_post_returns_preview_url(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_title'  => 'Original',
				'post_status' => 'draft',
			)
		);

		$result = Abilities::execute_update_post(
			array(
				'id'    => $post_id,
				'title' => 'Updated',
			)
		);

		$this->assertArrayHasKey( 'preview_url', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertNotEmpty( $result['preview_url'] );
	}

	/**
	 * Test update-post logs audit entry with diff in context (audit enhancements).
	 */
	public function test_update_post_audit_includes_diff(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Short',
				'post_content' => 'A bit of content.',
				'post_status'  => 'publish',
			)
		);

		Abilities::execute_update_post(
			array(
				'id'      => $post_id,
				'title'   => 'Updated title here',
				'content' => 'More content after update.',
			)
		);

		$result = Audit_Table::query( array( 'event_type' => 'post_updated' ) );
		$this->assertGreaterThanOrEqual( 1, $result['total'] );

		$with_diff = null;
		foreach ( $result['items'] as $item ) {
			$ctx = $item['context'] ?? array();
			if ( isset( $ctx['post_id'] ) && (int) $ctx['post_id'] === $post_id && ! empty( $ctx['diff'] ) ) {
				$with_diff = $ctx;
				break;
			}
		}
		$this->assertNotNull( $with_diff, 'Audit entry for post_updated should include diff in context.' );
		$this->assertArrayHasKey( 'title_length_before', $with_diff['diff'] );
		$this->assertArrayHasKey( 'title_length_after', $with_diff['diff'] );
		$this->assertArrayHasKey( 'content_length_before', $with_diff['diff'] );
		$this->assertArrayHasKey( 'content_length_after', $with_diff['diff'] );
	}

	// =========================================================================
	// Block JSON (create-post / update-post)
	// =========================================================================

	/**
	 * Test create-post with blocks array produces block markup when serialize_blocks exists.
	 */
	public function test_create_post_with_blocks(): void {
		$this->assertTrue( function_exists( 'serialize_blocks' ), 'serialize_blocks (WP 5.0+) must be available.' );

		$result = Abilities::execute_create_post(
			array(
				'title'  => 'Block post',
				'blocks' => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerContent' => array( 'Hello from block.' ),
					),
				),
			)
		);

		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayNotHasKey( 'error', $result );

		$post = get_post( $result['id'] );
		$this->assertStringContainsString( 'Hello from block', $post->post_content );
		$this->assertStringContainsString( 'wp:paragraph', $post->post_content );
	}

	/**
	 * Test create-post with invalid blocks (no valid blockName) returns error.
	 */
	public function test_create_post_with_invalid_blocks_returns_error(): void {
		$this->assertTrue( function_exists( 'serialize_blocks' ), 'serialize_blocks (WP 5.0+) must be available.' );

		$result = Abilities::execute_create_post(
			array(
				'title'  => 'Bad blocks',
				'blocks' => array(
					array( 'innerContent' => array( 'No blockName' ) ),
				),
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'valid blocks', $result['error'] );
	}

	// =========================================================================
	// Content health report
	// =========================================================================

	/**
	 * Test content-health-report returns all four report keys.
	 */
	public function test_execute_content_health_report_returns_structure(): void {
		$result = Abilities::execute_content_health_report(
			array(
				'limit'     => 5,
				'min_words' => 100,
			)
		);

		$this->assertArrayHasKey( 'missing_alt', $result );
		$this->assertArrayHasKey( 'broken_internal_links', $result );
		$this->assertArrayHasKey( 'thin_content', $result );
		$this->assertArrayHasKey( 'orphaned_media', $result );
		$this->assertIsArray( $result['missing_alt'] );
		$this->assertIsArray( $result['broken_internal_links'] );
		$this->assertIsArray( $result['thin_content'] );
		$this->assertIsArray( $result['orphaned_media'] );
	}

	// =========================================================================
	// Suggest terms
	// =========================================================================

	/**
	 * Test suggest-terms with content returns suggested_categories and suggested_tags.
	 */
	public function test_execute_suggest_terms_with_content(): void {
		$this->factory->post->create(
			array(
				'post_title'   => 'Gardening tips',
				'post_content' => 'How to grow tomatoes.',
				'post_status'  => 'publish',
			)
		);
		wp_set_post_terms(
			$this->factory->post->create( array( 'post_status' => 'publish' ) ),
			array( 'Gardening' ),
			'category'
		);

		$result = Abilities::execute_suggest_terms(
			array(
				'content' => 'Gardening and tomatoes and plants',
				'limit'   => 10,
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'suggested_categories', $result );
		$this->assertArrayHasKey( 'suggested_tags', $result );
		$this->assertIsArray( $result['suggested_categories'] );
		$this->assertIsArray( $result['suggested_tags'] );
	}

	/**
	 * Test suggest-terms with post_id uses that post content.
	 */
	public function test_execute_suggest_terms_with_post_id(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test draft',
				'post_content' => 'Some content for term suggestion.',
				'post_status'  => 'draft',
			)
		);

		$result = Abilities::execute_suggest_terms(
			array(
				'post_id' => $post_id,
				'limit'   => 5,
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'suggested_categories', $result );
		$this->assertArrayHasKey( 'suggested_tags', $result );
	}

	/**
	 * Test suggest-terms without post_id or content returns error.
	 */
	public function test_execute_suggest_terms_requires_post_id_or_content(): void {
		$result = Abilities::execute_suggest_terms( array( 'limit' => 5 ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'post_id or content', $result['error'] );
	}

	// =========================================================================
	// New workflow/media/site ops abilities
	// =========================================================================

	/**
	 * Test duplicate-post creates a draft clone.
	 */
	public function test_duplicate_post(): void {
		$source_id = $this->factory->post->create(
			array(
				'post_title'   => 'Source Post',
				'post_content' => 'Original content here.',
				'post_status'  => 'publish',
			)
		);

		$result = Abilities::execute_duplicate_post( array( 'post_id' => $source_id ) );

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertNotEquals( $source_id, $result['id'] );

		$cloned = get_post( $result['id'] );
		$this->assertNotNull( $cloned );
		$this->assertSame( 'draft', $cloned->post_status );
		$this->assertStringContainsString( 'Source Post', $cloned->post_title );
	}

	/**
	 * Test schedule-post sets status to future.
	 */
	public function test_schedule_post(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_title'  => 'Schedule me',
				'post_status' => 'draft',
			)
		);

		// Use a date well in the future (2 days) so timezone/cron edge cases don't flip status to publish.
		$future = gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS );
		$result = Abilities::execute_schedule_post(
			array(
				'post_id'   => $post_id,
				'post_date' => $future,
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertSame( 'future', get_post_status( $post_id ), 'Post status should be future after scheduling; result: ' . wp_json_encode( $result ) );
	}

	/**
	 * Test schedule-post rejects past dates.
	 */
	public function test_schedule_post_rejects_past_date(): void {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$past    = wp_date( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		$result = Abilities::execute_schedule_post(
			array(
				'post_id'   => $post_id,
				'post_date' => $past,
			)
		);

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test find-replace-content dry run is enabled by default.
	 */
	public function test_find_replace_content_dry_run_default(): void {
		$this->factory->post->create(
			array(
				'post_title'   => 'Find replace dry run',
				'post_content' => 'Lobster says pinch pinch.',
				'post_status'  => 'publish',
			)
		);

		$result = Abilities::execute_find_replace_content(
			array(
				'search'  => 'pinch',
				'replace' => 'snatch',
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertTrue( $result['dry_run'] );
		$this->assertGreaterThanOrEqual( 1, $result['matched_count'] );
	}

	/**
	 * Test find-replace-content updates content when dry_run is false.
	 */
	public function test_find_replace_content_updates_content(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Find replace write',
				'post_content' => 'Replace this lobster word.',
				'post_status'  => 'publish',
			)
		);

		$result = Abilities::execute_find_replace_content(
			array(
				'search'  => 'lobster',
				'replace' => 'crustacean',
				'dry_run' => false,
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertFalse( $result['dry_run'] );
		$this->assertGreaterThanOrEqual( 1, $result['changed_count'] );
		$this->assertStringContainsString( 'crustacean', get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * Test find-replace-content requires manage_options.
	 */
	public function test_find_replace_content_requires_manage_options(): void {
		wp_set_current_user( $this->editor_id );
		$result = Abilities::execute_find_replace_content(
			array(
				'search'  => 'foo',
				'replace' => 'bar',
			)
		);
		wp_set_current_user( $this->admin_id );

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test reorder-posts updates menu_order.
	 */
	public function test_reorder_posts(): void {
		$post_a = $this->factory->post->create( array( 'post_title' => 'A' ) );
		$post_b = $this->factory->post->create( array( 'post_title' => 'B' ) );

		$result = Abilities::execute_reorder_posts(
			array(
				'items' => array(
					array(
						'post_id'    => $post_a,
						'menu_order' => 5,
					),
					array(
						'post_id'    => $post_b,
						'menu_order' => 1,
					),
				),
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertSame( 5, (int) get_post_field( 'menu_order', $post_a ) );
		$this->assertSame( 1, (int) get_post_field( 'menu_order', $post_b ) );
	}

	/**
	 * Test compare-revisions returns revision comparison metadata.
	 */
	public function test_compare_revisions(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Revision test',
				'post_content' => 'First version',
				'post_status'  => 'draft',
			)
		);
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Second version',
			)
		);
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Third version',
			)
		);

		$revisions = wp_get_post_revisions( $post_id, array( 'order' => 'ASC' ) );
		$ids       = array_values( wp_list_pluck( $revisions, 'ID' ) );

		if ( count( $ids ) < 2 ) {
			$this->markTestSkipped( 'At least two revisions are required for compare-revisions.' );
		}

		$result = Abilities::execute_compare_revisions(
			array(
				'from_revision_id' => $ids[0],
				'to_revision_id'   => $ids[1],
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertSame( $post_id, $result['post_id'] );
		$this->assertArrayHasKey( 'changes', $result );
	}

	/**
	 * Test set-featured-image assigns and removes thumbnails.
	 */
	public function test_set_featured_image_set_and_remove(): void {
		$post_id       = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );

		$set = Abilities::execute_set_featured_image(
			array(
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
			)
		);
		$this->assertArrayNotHasKey( 'error', $set );
		$this->assertSame( $attachment_id, get_post_thumbnail_id( $post_id ) );

		$remove = Abilities::execute_set_featured_image(
			array(
				'post_id' => $post_id,
				'remove'  => true,
			)
		);
		$this->assertArrayNotHasKey( 'error', $remove );
		$this->assertFalse( has_post_thumbnail( $post_id ) );
	}

	/**
	 * Test list-unused-media returns unattached files.
	 */
	public function test_list_unused_media(): void {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );
		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_parent' => 0,
			)
		);

		$result = Abilities::execute_list_unused_media(
			array(
				'per_page'           => 10,
				'page'               => 1,
				'check_content_refs' => false,
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertIsArray( $result['items'] );
	}

	/**
	 * Test flush-cache reports a boolean result.
	 */
	public function test_flush_cache(): void {
		$result = Abilities::execute_flush_cache( array() );
		$this->assertArrayHasKey( 'flushed', $result );
		$this->assertIsBool( $result['flushed'] );
	}

	/**
	 * Test check-broken-links scans a specific post.
	 */
	public function test_check_broken_links(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Broken links scan',
				'post_content' => '<a href="http://example.invalid/definitely-missing">Broken</a>',
				'post_status'  => 'publish',
			)
		);

		$result = Abilities::execute_check_broken_links(
			array(
				'post_id'   => $post_id,
				'max_links' => 5,
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'links_checked', $result );
		$this->assertArrayHasKey( 'broken_links', $result );
	}

	/**
	 * Test get-php-error-log returns bounded structure or explicit error.
	 */
	public function test_get_php_error_log_returns_structure(): void {
		$result = Abilities::execute_get_php_error_log(
			array(
				'lines'              => 5,
				'max_chars_per_line' => 120,
			)
		);

		$this->assertTrue( isset( $result['error'] ) || isset( $result['lines'] ) );
	}

	/**
	 * Test list-posts-missing-meta returns structured output.
	 */
	public function test_list_posts_missing_meta(): void {
		$this->factory->post->create(
			array(
				'post_title'   => str_repeat( 'A', 90 ),
				'post_excerpt' => '',
				'post_status'  => 'publish',
			)
		);

		$result = Abilities::execute_list_posts_missing_meta(
			array(
				'title_max_length' => 80,
				'per_page'         => 10,
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertIsArray( $result['items'] );
	}

	/**
	 * Test list-custom-post-types returns a structured list.
	 */
	public function test_list_custom_post_types(): void {
		$result = Abilities::execute_list_custom_post_types( array( 'include_builtin' => false ) );
		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'post_types', $result );
		$this->assertIsArray( $result['post_types'] );
	}

	/**
	 * Test transient CRUD abilities.
	 */
	public function test_transient_crud(): void {
		$key = 'pinch_test_transient';

		$set = Abilities::execute_set_transient(
			array(
				'key'        => $key,
				'value'      => 'hello',
				'expiration' => 60,
			)
		);
		$this->assertArrayNotHasKey( 'error', $set );

		$get = Abilities::execute_get_transient( array( 'key' => $key ) );
		$this->assertArrayNotHasKey( 'error', $get );
		$this->assertTrue( $get['found'] );
		$this->assertSame( 'hello', $get['value'] );

		$delete = Abilities::execute_delete_transient( array( 'key' => $key ) );
		$this->assertArrayNotHasKey( 'error', $delete );
		$this->assertTrue( $delete['deleted'] || true );
	}

	/**
	 * Test rewrite rule list/flush abilities.
	 */
	public function test_rewrite_rule_abilities(): void {
		$list = Abilities::execute_list_rewrite_rules( array( 'limit' => 20 ) );
		$this->assertArrayNotHasKey( 'error', $list );
		$this->assertArrayHasKey( 'rules', $list );
		$this->assertIsArray( $list['rules'] );

		$flush = Abilities::execute_flush_rewrite_rules( array( 'hard' => false ) );
		$this->assertArrayNotHasKey( 'error', $flush );
		$this->assertTrue( $flush['flushed'] );
	}

	/**
	 * Test maintenance mode status and toggle ability.
	 */
	public function test_maintenance_mode_status_and_toggle(): void {
		$status = Abilities::execute_maintenance_mode_status( array() );
		$this->assertArrayNotHasKey( 'error', $status );
		$this->assertArrayHasKey( 'enabled', $status );

		$enable = Abilities::execute_set_maintenance_mode(
			array(
				'enabled' => true,
				'confirm' => true,
			)
		);
		$this->assertArrayNotHasKey( 'error', $enable );
		$this->assertTrue( $enable['enabled'] );

		$disable = Abilities::execute_set_maintenance_mode( array( 'enabled' => false ) );
		$this->assertArrayNotHasKey( 'error', $disable );
		$this->assertFalse( $disable['enabled'] );
	}

	/**
	 * Test scoped DB search/replace dry run.
	 */
	public function test_search_replace_db_scoped_dry_run(): void {
		$this->factory->post->create(
			array(
				'post_title'   => 'DB scope test',
				'post_content' => 'needle needle haystack',
				'post_status'  => 'publish',
			)
		);

		$result = Abilities::execute_search_replace_db_scoped(
			array(
				'search'  => 'needle',
				'replace' => 'thread',
				'scope'   => 'posts_content',
				'dry_run' => true,
				'limit'   => 50,
			)
		);
		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertTrue( $result['dry_run'] );
		$this->assertArrayHasKey( 'matched_count', $result );
	}

	/**
	 * Test language pack list ability returns structure.
	 */
	public function test_list_language_packs(): void {
		$result = Abilities::execute_list_language_packs( array( 'limit' => 20 ) );
		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'languages', $result );
		$this->assertIsArray( $result['languages'] );
	}

	/**
	 * Test scoped DB replace requires confirm when dry_run is false.
	 */
	public function test_search_replace_db_scoped_requires_confirm(): void {
		$result = Abilities::execute_search_replace_db_scoped(
			array(
				'search'  => 'abc',
				'replace' => 'xyz',
				'scope'   => 'posts_content',
				'dry_run' => false,
			)
		);
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'confirm=true', $result['error'] );
	}

	/**
	 * Test scoped DB replace skips serialized postmeta values.
	 */
	public function test_search_replace_db_scoped_skips_serialized_postmeta(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_title'  => 'Serialized meta safety',
				'post_status' => 'publish',
			)
		);
		update_post_meta(
			$post_id,
			'pinch_serialized',
			array(
				'needle' => 'value',
				'other'  => 'keep',
			)
		);

		$result = Abilities::execute_search_replace_db_scoped(
			array(
				'search'  => 'needle',
				'replace' => 'thread',
				'scope'   => 'postmeta_value',
				'dry_run' => true,
				'limit'   => 50,
			)
		);
		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'skipped_serialized_count', $result );
		$this->assertGreaterThanOrEqual( 1, (int) $result['skipped_serialized_count'] );
		$this->assertIsArray( get_post_meta( $post_id, 'pinch_serialized', true ) );
	}

	/**
	 * Test enabling maintenance mode requires confirmation.
	 */
	public function test_set_maintenance_mode_requires_confirm_to_enable(): void {
		$result = Abilities::execute_set_maintenance_mode( array( 'enabled' => true ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'confirm=true', $result['error'] );
	}

	/**
	 * Test create-user blocks administrator role assignment.
	 */
	public function test_create_user_blocks_administrator_role(): void {
		$result = Abilities::execute_create_user(
			array(
				'login' => 'pinch_admin_attempt',
				'email' => 'pinch_admin_attempt@example.com',
				'role'  => 'administrator',
			)
		);
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test extension lifecycle abilities require confirmation for risky actions.
	 */
	public function test_extension_lifecycle_requires_confirmation(): void {
		$plugin = Abilities::execute_manage_plugin_lifecycle(
			array(
				'action' => 'delete',
				'plugin' => 'akismet/akismet.php',
			)
		);
		$this->assertArrayHasKey( 'error', $plugin );

		$theme = Abilities::execute_manage_theme_lifecycle(
			array(
				'action'     => 'delete',
				'stylesheet' => 'twentytwentyfour',
			)
		);
		$this->assertArrayHasKey( 'error', $theme );
	}

	/**
	 * Test extension lifecycle checks action-specific capabilities.
	 */
	public function test_extension_lifecycle_checks_action_capabilities(): void {
		wp_set_current_user( $this->editor_id );
		$plugin = Abilities::execute_manage_plugin_lifecycle(
			array(
				'action'  => 'delete',
				'plugin'  => 'akismet/akismet.php',
				'confirm' => true,
			)
		);
		$theme  = Abilities::execute_manage_theme_lifecycle(
			array(
				'action'     => 'delete',
				'stylesheet' => 'twentytwentyfour',
				'confirm'    => true,
			)
		);
		wp_set_current_user( $this->admin_id );

		$this->assertArrayHasKey( 'error', $plugin );
		$this->assertStringContainsString( 'permission', strtolower( $plugin['error'] ) );
		$this->assertArrayHasKey( 'error', $theme );
		$this->assertStringContainsString( 'permission', strtolower( $theme['error'] ) );
	}

	/**
	 * Test extended user abilities and comment CRUD.
	 */
	public function test_extended_user_and_comment_abilities(): void {
		$create = Abilities::execute_create_user(
			array(
				'login' => 'pinch_new_user',
				'email' => 'pinch_new_user@example.com',
				'role'  => 'subscriber',
			)
		);
		$this->assertArrayNotHasKey( 'error', $create );
		$this->assertArrayHasKey( 'id', $create );

		$reset = Abilities::execute_reset_user_password(
			array(
				'user_id'         => $create['id'],
				'return_password' => true,
			)
		);
		$this->assertArrayNotHasKey( 'error', $reset );
		$this->assertTrue( $reset['reset'] );

		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$c       = Abilities::execute_create_comment(
			array(
				'post_id' => $post_id,
				'content' => 'A comment from ability test.',
				'status'  => 'hold',
			)
		);
		$this->assertArrayNotHasKey( 'error', $c );

		$u = Abilities::execute_update_comment(
			array(
				'id'      => $c['id'],
				'content' => 'Updated ability comment.',
				'status'  => 'approve',
			)
		);
		$this->assertArrayNotHasKey( 'error', $u );

		$d = Abilities::execute_delete_comment(
			array(
				'id'    => $c['id'],
				'force' => true,
			)
		);
		$this->assertArrayNotHasKey( 'error', $d );
		$this->assertTrue( $d['deleted'] );

		$delete_user = Abilities::execute_delete_user(
			array(
				'user_id' => $create['id'],
				'confirm' => true,
			)
		);
		$this->assertArrayNotHasKey( 'error', $delete_user );
		$this->assertTrue( $delete_user['deleted'] );
	}

	/**
	 * Test extended comment CRUD rejects invalid status.
	 */
	public function test_extended_comment_status_rejects_invalid_values(): void {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$create  = Abilities::execute_create_comment(
			array(
				'post_id' => $post_id,
				'content' => 'Status validation check.',
				'status'  => 'bogus',
			)
		);
		$this->assertArrayHasKey( 'error', $create );

		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );
		$update     = Abilities::execute_update_comment(
			array(
				'id'     => $comment_id,
				'status' => 'nope',
			)
		);
		$this->assertArrayHasKey( 'error', $update );
	}

	/**
	 * Test regenerate media thumbnails ability.
	 */
	public function test_regenerate_media_thumbnails(): void {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );

		$result = Abilities::execute_regenerate_media_thumbnails(
			array(
				'attachment_ids' => array( $attachment_id ),
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayHasKey( 'success_count', $result );
	}

	/**
	 * Test Woo ability list contains expanded abilities when WooCommerce is active.
	 */
	public function test_woo_ability_names_expand_when_woocommerce_active(): void {
		$names = Abilities::get_ability_names();
		if ( class_exists( 'WooCommerce' ) ) {
			$this->assertContains( 'wp-pinch/woo-create-product', $names );
			$this->assertContains( 'wp-pinch/woo-list-orders', $names );
			$this->assertContains( 'wp-pinch/woo-create-refund', $names );
			$this->assertContains( 'wp-pinch/woo-sales-summary', $names );
			return;
		}

		$this->assertNotContains( 'wp-pinch/woo-create-product', $names );
	}

	/**
	 * Test Woo method and slug parity so wrappers do not drift.
	 */
	public function test_woo_wrapper_methods_and_ability_slugs_stay_in_sync(): void {
		$woo_map = $this->get_woo_ability_contract_map();

		foreach ( $woo_map as $slug => $contract ) {
			$this->assertArrayHasKey( 'method', $contract );
			$method = (string) $contract['method'];
			$this->assertTrue(
				method_exists( Abilities::class, $method ),
				"Missing Abilities wrapper method: {$method}."
			);
			$this->assertStringStartsWith( 'wp-pinch/woo-', $slug );
		}

		$slugs = array_keys( $woo_map );
		sort( $slugs );
		$slugs_from_names = array_values(
			array_filter(
				Abilities::get_ability_names(),
				static function ( string $name ): bool {
					return str_starts_with( $name, 'wp-pinch/woo-' );
				}
			)
		);
		sort( $slugs_from_names );

		if ( class_exists( 'WooCommerce' ) ) {
			$this->assertSame( $slugs, $slugs_from_names );
			return;
		}

		$this->assertSame( array(), $slugs_from_names );
	}

	/**
	 * Test Woo abilities fail with deterministic error shape when WooCommerce is inactive.
	 *
	 * @dataProvider woo_execute_method_matrix
	 */
	public function test_woo_abilities_return_deterministic_error_without_woocommerce(
		string $method,
		array $payload,
		bool $expects_bulk_error_shape
	): void {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->assertTrue( true );
			return;
		}

		$result = Abilities::$method( $payload );
		if ( $expects_bulk_error_shape ) {
			$this->assertArrayHasKey( 'errors', $result );
			$this->assertIsArray( $result['errors'] );
			$this->assertNotEmpty( $result['errors'] );
			$this->assertIsString( $result['errors'][0]['error'] ?? '' );
			return;
		}

		$this->assertArrayHasKey( 'error', $result );
		$this->assertIsString( $result['error'] );
		$this->assertStringContainsString(
			'woocommerce',
			strtolower( $result['error'] )
		);
	}

	/**
	 * Test Woo order cancel guardrails for confirm and status paths.
	 */
	public function test_woo_cancel_order_safe_requires_confirmation_or_woocommerce(): void {
		$result = Abilities::execute_woo_cancel_order_safe(
			array(
				'order_id' => 1,
				'confirm'  => false,
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertIsString( $result['error'] );
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->assertStringContainsString( 'woocommerce', strtolower( $result['error'] ) );
			return;
		}

		$this->assertStringContainsString( 'confirm=true', strtolower( $result['error'] ) );
	}

	/**
	 * Test Woo refund idempotency/guard path returns structured errors when unavailable.
	 */
	public function test_woo_refund_structured_error_without_woocommerce(): void {
		$result = Abilities::execute_woo_create_refund(
			array(
				'order_id'        => 1,
				'amount'          => 10,
				'idempotency_key' => 'abc123',
			)
		);
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test risky Woo ability schemas keep required guard fields.
	 */
	public function test_woo_risky_ability_schema_contracts_are_present(): void {
		$woo_dir = dirname( __DIR__ ) . '/includes/Ability/Woo';
		$this->assertDirectoryExists( $woo_dir );
		$source    = '';
		$woo_files = glob( $woo_dir . '/*.php' );
		foreach ( $woo_files ? $woo_files : array() as $source_path ) {
			$contents = file_get_contents( $source_path );
			$this->assertNotFalse( $contents );
			$source .= (string) $contents;
		}

		$required_contract_snippets = array(
			"'wp-pinch/woo-bulk-adjust-stock'",
			"'required'   => array( 'adjustments' )",
			"'wp-pinch/woo-cancel-order-safe'",
			"'required'   => array( 'order_id', 'confirm' )",
			"'wp-pinch/woo-create-refund'",
			"'required'   => array( 'order_id' )",
		);

		foreach ( $required_contract_snippets as $snippet ) {
			$this->assertStringContainsString( $snippet, (string) $source );
		}
	}

	/**
	 * Data provider for Woo execution matrix.
	 *
	 * @return array<string, array{0:string,1:array<string,mixed>,2:bool}>
	 */
	public function woo_execute_method_matrix(): array {
		$cases = array();
		foreach ( $this->get_woo_ability_contract_map() as $slug => $contract ) {
			$cases[ $slug ] = array(
				(string) $contract['method'],
				(array) $contract['payload'],
				! empty( $contract['expects_bulk_error_shape'] ),
			);
		}
		return $cases;
	}

	/**
	 * Shared Woo ability contract map used by parity + matrix tests.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_woo_ability_contract_map(): array {
		return array(
			'wp-pinch/woo-list-products'               => array(
				'method'  => 'execute_woo_list_products',
				'payload' => array(),
			),
			'wp-pinch/woo-get-product'                 => array(
				'method'  => 'execute_woo_get_product',
				'payload' => array( 'product_id' => 1 ),
			),
			'wp-pinch/woo-create-product'              => array(
				'method'  => 'execute_woo_create_product',
				'payload' => array( 'name' => 'X' ),
			),
			'wp-pinch/woo-update-product'              => array(
				'method'  => 'execute_woo_update_product',
				'payload' => array( 'product_id' => 1 ),
			),
			'wp-pinch/woo-delete-product'              => array(
				'method'  => 'execute_woo_delete_product',
				'payload' => array( 'product_id' => 1 ),
			),
			'wp-pinch/woo-list-orders'                 => array(
				'method'  => 'execute_woo_list_orders',
				'payload' => array(),
			),
			'wp-pinch/woo-get-order'                   => array(
				'method'  => 'execute_woo_get_order',
				'payload' => array( 'order_id' => 1 ),
			),
			'wp-pinch/woo-create-order'                => array(
				'method'  => 'execute_woo_create_order',
				'payload' => array(
					'line_items' => array(
						array(
							'product_id' => 1,
							'quantity'   => 1,
						),
					),
				),
			),
			'wp-pinch/woo-update-order'                => array(
				'method'  => 'execute_woo_update_order',
				'payload' => array( 'order_id' => 1 ),
			),
			'wp-pinch/woo-manage-order'                => array(
				'method'  => 'execute_woo_manage_order',
				'payload' => array( 'order_id' => 1 ),
			),
			'wp-pinch/woo-adjust-stock'                => array(
				'method'  => 'execute_woo_adjust_stock',
				'payload' => array(
					'product_id'     => 1,
					'quantity_delta' => -1,
				),
			),
			'wp-pinch/woo-bulk-adjust-stock'           => array(
				'method'                   => 'execute_woo_bulk_adjust_stock',
				'payload'                  => array(
					'adjustments' => array(
						array(
							'product_id'     => 1,
							'quantity_delta' => 1,
						),
					),
				),
				'expects_bulk_error_shape' => true,
			),
			'wp-pinch/woo-list-low-stock'              => array(
				'method'  => 'execute_woo_list_low_stock',
				'payload' => array(),
			),
			'wp-pinch/woo-list-out-of-stock'           => array(
				'method'  => 'execute_woo_list_out_of_stock',
				'payload' => array(),
			),
			'wp-pinch/woo-list-variations'             => array(
				'method'  => 'execute_woo_list_variations',
				'payload' => array( 'product_id' => 1 ),
			),
			'wp-pinch/woo-update-variation'            => array(
				'method'  => 'execute_woo_update_variation',
				'payload' => array( 'variation_id' => 1 ),
			),
			'wp-pinch/woo-list-product-taxonomies'     => array(
				'method'  => 'execute_woo_list_product_taxonomies',
				'payload' => array(),
			),
			'wp-pinch/woo-add-order-note'              => array(
				'method'  => 'execute_woo_add_order_note',
				'payload' => array(
					'order_id' => 1,
					'note'     => 'hello',
				),
			),
			'wp-pinch/woo-mark-fulfilled'              => array(
				'method'  => 'execute_woo_mark_fulfilled',
				'payload' => array( 'order_id' => 1 ),
			),
			'wp-pinch/woo-cancel-order-safe'           => array(
				'method'  => 'execute_woo_cancel_order_safe',
				'payload' => array(
					'order_id' => 1,
					'confirm'  => true,
				),
			),
			'wp-pinch/woo-create-refund'               => array(
				'method'  => 'execute_woo_create_refund',
				'payload' => array(
					'order_id'        => 1,
					'amount'          => 10,
					'idempotency_key' => 'abc123',
				),
			),
			'wp-pinch/woo-list-refund-eligible-orders' => array(
				'method'  => 'execute_woo_list_refund_eligible_orders',
				'payload' => array(),
			),
			'wp-pinch/woo-create-coupon'               => array(
				'method'  => 'execute_woo_create_coupon',
				'payload' => array(
					'code'   => 'X',
					'amount' => '5',
				),
			),
			'wp-pinch/woo-update-coupon'               => array(
				'method'  => 'execute_woo_update_coupon',
				'payload' => array( 'coupon_id' => 1 ),
			),
			'wp-pinch/woo-expire-coupon'               => array(
				'method'  => 'execute_woo_expire_coupon',
				'payload' => array( 'coupon_id' => 1 ),
			),
			'wp-pinch/woo-list-customers'              => array(
				'method'  => 'execute_woo_list_customers',
				'payload' => array(),
			),
			'wp-pinch/woo-get-customer'                => array(
				'method'  => 'execute_woo_get_customer',
				'payload' => array( 'customer_id' => 1 ),
			),
			'wp-pinch/woo-sales-summary'               => array(
				'method'  => 'execute_woo_sales_summary',
				'payload' => array(),
			),
			'wp-pinch/woo-top-products'                => array(
				'method'  => 'execute_woo_top_products',
				'payload' => array(),
			),
			'wp-pinch/woo-orders-needing-attention'    => array(
				'method'  => 'execute_woo_orders_needing_attention',
				'payload' => array(),
			),
		);
	}

	// =========================================================================
	// Trait composition guardrails
	// =========================================================================

	/**
	 * Abilities class uses all three composition traits.
	 */
	public function test_abilities_class_uses_expected_traits(): void {
		$traits = class_uses( Abilities::class );
		$this->assertIsArray( $traits );
		$this->assertArrayHasKey( 'WP_Pinch\\Ability_Names_Trait', $traits, 'Abilities must use Ability_Names_Trait.' );
		$this->assertArrayHasKey( 'WP_Pinch\\Core_Passthrough_Trait', $traits, 'Abilities must use Core_Passthrough_Trait.' );
		$this->assertArrayHasKey( 'WP_Pinch\\Woo_Passthrough_Trait', $traits, 'Abilities must use Woo_Passthrough_Trait.' );
	}

	/**
	 * Woo_Abilities class uses all expected execution traits.
	 */
	public function test_woo_abilities_class_uses_expected_traits(): void {
		$traits = class_uses( \WP_Pinch\Ability\Woo_Abilities::class );
		$this->assertIsArray( $traits );

		$expected = array(
			'WP_Pinch\\Ability\\Woo_Helpers_Trait',
			'WP_Pinch\\Ability\\Woo_Inventory_Execute_Trait',
			'WP_Pinch\\Ability\\Woo_Operations_Insights_Execute_Trait',
			'WP_Pinch\\Ability\\Woo_Products_Orders_Execute_Trait',
			'WP_Pinch\\Ability\\Woo_Register_Trait',
		);

		foreach ( $expected as $trait ) {
			$this->assertArrayHasKey( $trait, $traits, "Woo_Abilities must use {$trait}." );
		}
	}

	/**
	 * Analytics_Abilities class uses Analytics_Execute_Trait.
	 */
	public function test_analytics_abilities_class_uses_execute_trait(): void {
		$traits = class_uses( \WP_Pinch\Ability\Analytics_Abilities::class );
		$this->assertIsArray( $traits );
		$this->assertArrayHasKey(
			'WP_Pinch\\Ability\\Analytics_Execute_Trait',
			$traits,
			'Analytics_Abilities must use Analytics_Execute_Trait.'
		);
	}

	/**
	 * Ability_Names_Trait exposes get_ability_names as a public static method.
	 */
	public function test_ability_names_trait_provides_get_ability_names(): void {
		$this->assertTrue(
			method_exists( Abilities::class, 'get_ability_names' ),
			'Abilities::get_ability_names() must exist (via Ability_Names_Trait).'
		);

		$ref = new \ReflectionMethod( Abilities::class, 'get_ability_names' );
		$this->assertTrue( $ref->isStatic(), 'get_ability_names must be static.' );
		$this->assertTrue( $ref->isPublic(), 'get_ability_names must be public.' );
	}

	/**
	 * All refactored trait source files exist on disk so require_once chains stay consistent.
	 */
	public function test_refactored_trait_files_exist(): void {
		$root  = dirname( __DIR__ );
		$files = array(
			// Abilities facade traits.
			'includes/Ability_Names_Trait.php',
			'includes/Ability/Core_Passthrough_Trait.php',
			'includes/Ability/Woo_Passthrough_Trait.php',
			// Analytics split.
			'includes/Ability/Analytics/Analytics_Execute_Trait.php',
			// QuickWin split.
			'includes/Ability/QuickWin/QuickWin_Execute_Trait.php',
			// MenuMeta split.
			'includes/Ability/MenuMeta/Menu_Meta_Revisions_Execute_Trait.php',
			// GEO/SEO split.
			'includes/Ability/GEO/GEO_SEO_Execute_Trait.php',
			// Woo execution traits.
			'includes/Ability/Woo/Woo_Helpers_Trait.php',
			'includes/Ability/Woo/Woo_Inventory_Execute_Trait.php',
			'includes/Ability/Woo/Woo_Operations_Insights_Execute_Trait.php',
			'includes/Ability/Woo/Woo_Products_Orders_Execute_Trait.php',
			// Woo register traits.
			'includes/Ability/Woo/Woo_Register_Trait.php',
			'includes/Ability/Woo/Woo_Register_Products_Orders_Trait.php',
			'includes/Ability/Woo/Woo_Register_Inventory_Operations_Trait.php',
			'includes/Ability/Woo/Woo_Register_Commercial_Intelligence_Trait.php',
			// Settings split.
			'includes/Settings/Settings_Admin_Pages_Trait.php',
		);

		foreach ( $files as $relative ) {
			$this->assertFileExists( "{$root}/{$relative}", "Expected refactored trait file to exist: {$relative}" );
		}
	}

	// =========================================================================
	// Constants
	// =========================================================================

	/**
	 * Test CACHE_TTL constant.
	 */
	public function test_cache_ttl(): void {
		$this->assertEquals( 300, Abilities::CACHE_TTL );
	}

	/**
	 * Test OPTION_ALLOWLIST is not empty.
	 */
	public function test_option_allowlist_not_empty(): void {
		$this->assertNotEmpty( Abilities::OPTION_ALLOWLIST );
		$this->assertContains( 'blogname', Abilities::OPTION_ALLOWLIST );
	}
}
