<?php
/**
 * Tests for the OpenClaw_Role class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\OpenClaw_Role;

/**
 * Test OpenClaw role and capability management.
 */
class Test_OpenClaw_Role extends WP_UnitTestCase {

	/**
	 * Tear down — remove role if created.
	 */
	public function tear_down(): void {
		$role = get_role( OpenClaw_Role::ROLE_SLUG );
		if ( $role ) {
			remove_role( OpenClaw_Role::ROLE_SLUG );
		}
		parent::tear_down();
	}

	/**
	 * Test ROLE_SLUG constant.
	 */
	public function test_role_slug_constant(): void {
		$this->assertSame( 'openclaw_agent', OpenClaw_Role::ROLE_SLUG );
	}

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks(): void {
		OpenClaw_Role::init();

		$this->assertIsInt( has_action( 'wp_pinch_activated', array( OpenClaw_Role::class, 'ensure_role_exists' ) ) );
		$this->assertIsInt(
			has_filter( 'update_option_wp_pinch_openclaw_capability_groups', array( OpenClaw_Role::class, 'sync_role_on_capability_save' ) )
		);
	}

	/**
	 * Test get_capability_group_slugs returns expected keys.
	 */
	public function test_get_capability_group_slugs(): void {
		$slugs = OpenClaw_Role::get_capability_group_slugs();

		$this->assertIsArray( $slugs );
		$this->assertContains( 'content', $slugs );
		$this->assertContains( 'media', $slugs );
		$this->assertContains( 'settings', $slugs );
	}

	/**
	 * Test ensure_role_exists creates role when missing.
	 */
	public function test_ensure_role_exists_creates_role(): void {
		remove_role( OpenClaw_Role::ROLE_SLUG );

		$role = get_role( OpenClaw_Role::ROLE_SLUG );
		$this->assertNull( $role, 'Role should not exist before ensure_role_exists' );

		OpenClaw_Role::ensure_role_exists();

		$role = get_role( OpenClaw_Role::ROLE_SLUG );
		$this->assertNotNull( $role );
		// WP_Role->name is the slug; display name is in wp_roles->roles.
		$wp_roles = wp_roles();
		$this->assertArrayHasKey( OpenClaw_Role::ROLE_SLUG, $wp_roles->roles );
		$this->assertSame( 'OpenClaw Agent', $wp_roles->roles[ OpenClaw_Role::ROLE_SLUG ]['name'] );
	}

	/**
	 * Test update_role_capabilities grants expected caps for default groups.
	 */
	public function test_update_role_capabilities_grants_caps(): void {
		OpenClaw_Role::ensure_role_exists();

		OpenClaw_Role::update_role_capabilities( OpenClaw_Role::DEFAULT_GROUPS );

		$role = get_role( OpenClaw_Role::ROLE_SLUG );
		$this->assertNotNull( $role );
		$this->assertTrue( $role->has_cap( 'edit_posts' ) );
		$this->assertTrue( $role->has_cap( 'upload_files' ) );
		$this->assertTrue( $role->has_cap( 'moderate_comments' ) );
	}

	/**
	 * Test get_capability_group_labels returns labels for all groups.
	 */
	public function test_get_capability_group_labels(): void {
		$labels = OpenClaw_Role::get_capability_group_labels();

		$this->assertIsArray( $labels );
		$this->assertArrayHasKey( 'content', $labels );
		$this->assertArrayHasKey( 'media', $labels );
		$this->assertNotEmpty( $labels['content'] );
	}
}
