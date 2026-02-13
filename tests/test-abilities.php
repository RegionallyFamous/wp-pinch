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
	 * Test that get_ability_names returns the expected count (38 without WooCommerce).
	 */
	public function test_ability_names_count(): void {
		$names = Abilities::get_ability_names();
		$this->assertCount( 38, $names, 'Expected 38 abilities to be registered (without WooCommerce).' );
		$this->assertContains( 'wp-pinch/pinchdrop-generate', $names );
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
			array( 'per_page' => 0, 'page' => 0 )
		);

		$this->assertEquals( 1, $result['page'], 'page=0 should be clamped to 1.' );
		// per_page=0 → max(1, min(0, 100)) = max(1, 0) = 1
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
		$result = Abilities::execute_update_post( array( 'id' => 99999, 'title' => 'X' ) );
		$this->assertArrayHasKey( 'error', $result );
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

		$result = Abilities::execute_delete_post( array( 'id' => $post_id, 'force' => true ) );

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
		$result = Abilities::execute_list_users( array( 'per_page' => 10, 'page' => 1 ) );

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
			array( 'id' => $subscriber_id, 'role' => 'author' )
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
			array( 'id' => $subscriber_id, 'role' => 'administrator' )
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
			array( 'id' => $this->admin_id, 'role' => 'author' )
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
			array( 'id' => $subscriber_id, 'role' => 'nonexistent_role' )
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
		add_filter( 'wp_pinch_blocked_roles', function ( $roles ) {
			$roles[] = 'editor';
			return $roles;
		} );

		$result = Abilities::execute_update_user_role(
			array( 'id' => $subscriber_id, 'role' => 'editor' )
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
			array( 'per_page' => 2, 'page' => 1, 'post_id' => $post_id )
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
			array( 'id' => $comment_id, 'status' => 'approve' )
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
			array( 'id' => 99999, 'status' => 'approve' )
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
			array( 'id' => $comment_id, 'status' => 'invalid_status' )
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
	 */
	public function test_update_option_disallowed(): void {
		$result = Abilities::execute_update_option(
			array(
				'key'   => 'admin_email',
				'value' => 'hacker@evil.com',
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
		$result = Abilities::execute_export_data( array( 'type' => 'posts', 'per_page' => 10 ) );

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
			array( 'post_id' => $post_id, 'key' => 'test_key' )
		);

		$this->assertEquals( 'test_value', $result['value'] );
	}

	/**
	 * Test get-post-meta filters protected meta.
	 */
	public function test_get_post_meta_protected_key_blocked(): void {
		$post_id = $this->factory->post->create();

		$result = Abilities::execute_get_post_meta(
			array( 'post_id' => $post_id, 'key' => '_edit_lock' )
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
			array( 'post_id' => $post_id, 'key' => 'my_custom_field', 'value' => 'hello' )
		);

		$this->assertTrue( $result['updated'] );
		$this->assertEquals( 'hello', get_post_meta( $post_id, 'my_custom_field', true ) );
	}

	/**
	 * Test update-post-meta for non-existent post returns error.
	 */
	public function test_update_post_meta_post_not_found(): void {
		$result = Abilities::execute_update_post_meta(
			array( 'post_id' => 99999, 'key' => 'test', 'value' => 'x' )
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
	 * Test restore-revision with non-existent revision returns error.
	 */
	public function test_restore_revision_not_found(): void {
		$result = Abilities::execute_restore_revision( array( 'revision_id' => 99999 ) );
		$this->assertArrayHasKey( 'error', $result );
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
