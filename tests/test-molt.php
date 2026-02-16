<?php
/**
 * Tests for the Molt class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Molt;

/**
 * Test Molt content repackager.
 */
class Test_Molt extends WP_UnitTestCase {

	/**
	 * Test get_default_output_types returns array of expected keys.
	 */
	public function test_get_default_output_types_returns_array(): void {
		$types = Molt::get_default_output_types();

		$this->assertIsArray( $types );
		$this->assertContains( 'social', $types );
		$this->assertContains( 'email_snippet', $types );
		$this->assertContains( 'faq_block', $types );
		$this->assertContains( 'faq_blocks', $types );
		$this->assertContains( 'thread', $types );
		$this->assertContains( 'summary', $types );
		$this->assertContains( 'meta_description', $types );
		$this->assertContains( 'pull_quote', $types );
		$this->assertContains( 'key_takeaways', $types );
		$this->assertContains( 'cta_variants', $types );
	}

	/**
	 * Test get_default_output_types is filterable.
	 */
	public function test_get_default_output_types_filter(): void {
		add_filter(
			'wp_pinch_molt_output_types',
			function ( $types ) {
				return array_merge( $types, array( 'custom_format' ) );
			}
		);

		$types = Molt::get_default_output_types();

		remove_all_filters( 'wp_pinch_molt_output_types' );

		$this->assertContains( 'custom_format', $types );
	}

	/**
	 * Test molt returns WP_Error when post not found.
	 */
	public function test_molt_post_not_found_returns_wp_error(): void {
		$result = Molt::molt( 999999, array( 'summary' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	/**
	 * Test molt returns WP_Error when invalid output formats specified.
	 */
	public function test_molt_invalid_formats_returns_wp_error(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$post_id = $this->factory->post->create();

		$result = Molt::molt( $post_id, array( 'nonexistent_format_xyz' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_formats', $result->get_error_code() );
	}

	/**
	 * Test Molt constants.
	 */
	public function test_constants(): void {
		$this->assertSame( 8000, Molt::MAX_CONTENT_CHARS );
		$this->assertSame( 280, Molt::TWITTER_MAX_CHARS );
		$this->assertSame( 155, Molt::META_MAX_CHARS );
	}
}
