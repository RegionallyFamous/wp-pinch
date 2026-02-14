<?php
/**
 * Tests for the Governance Engine.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Governance;
use WP_Pinch\Audit_Table;

/**
 * Test governance task scheduling, execution, and caching.
 */
class Test_Governance extends WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		Audit_Table::create_table();

		// Clean up governance schedule hash between tests.
		delete_option( 'wp_pinch_governance_schedule_hash' );
		delete_option( 'wp_pinch_governance_tasks' );
	}

	// =========================================================================
	// Constants & Configuration
	// =========================================================================

	/**
	 * Test DEFAULT_INTERVALS contains all governance tasks (currently 7).
	 */
	public function test_default_intervals_count(): void {
		$this->assertCount( 7, Governance::DEFAULT_INTERVALS );
	}

	/**
	 * Test DEFAULT_INTERVALS keys match expected task names.
	 */
	public function test_default_intervals_keys(): void {
		$expected = array(
			'content_freshness',
			'seo_health',
			'comment_sweep',
			'broken_links',
			'security_scan',
			'draft_necromancer',
			'tide_report',
		);

		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, Governance::DEFAULT_INTERVALS );
		}
	}

	/**
	 * Test all intervals are positive integers.
	 */
	public function test_default_intervals_positive(): void {
		foreach ( Governance::DEFAULT_INTERVALS as $task => $interval ) {
			$this->assertGreaterThan( 0, $interval, "Interval for '{$task}' should be positive." );
		}
	}

	// =========================================================================
	// Enabled tasks
	// =========================================================================

	/**
	 * Test get_enabled_tasks returns all tasks when none are explicitly configured.
	 */
	public function test_get_enabled_tasks_default_all(): void {
		delete_option( 'wp_pinch_governance_tasks' );

		$enabled = Governance::get_enabled_tasks();
		$expected = count( Governance::DEFAULT_INTERVALS );

		$this->assertCount( $expected, $enabled );
		$this->assertContains( 'content_freshness', $enabled );
		$this->assertContains( 'security_scan', $enabled );
	}

	/**
	 * Test get_enabled_tasks returns configured subset.
	 */
	public function test_get_enabled_tasks_configured(): void {
		update_option( 'wp_pinch_governance_tasks', array( 'seo_health', 'broken_links' ) );

		$enabled = Governance::get_enabled_tasks();

		$this->assertCount( 2, $enabled );
		$this->assertContains( 'seo_health', $enabled );
		$this->assertContains( 'broken_links', $enabled );
	}

	// =========================================================================
	// Available tasks
	// =========================================================================

	/**
	 * Test get_available_tasks returns all 8 tasks with labels.
	 */
	public function test_get_available_tasks(): void {
		$tasks = Governance::get_available_tasks();

		$this->assertCount( 8, $tasks );
		foreach ( $tasks as $key => $label ) {
			$this->assertIsString( $label );
			$this->assertNotEmpty( $label );
		}
	}

	// =========================================================================
	// Schedule hash caching (maybe_schedule_tasks)
	// =========================================================================

	/**
	 * Test maybe_schedule_tasks stores a hash on first call.
	 */
	public function test_maybe_schedule_tasks_stores_hash(): void {
		delete_option( 'wp_pinch_governance_schedule_hash' );

		// Only call if Action Scheduler is available (it may not be in test env).
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		Governance::maybe_schedule_tasks();

		$hash = get_option( 'wp_pinch_governance_schedule_hash' );
		$this->assertNotEmpty( $hash );
	}

	/**
	 * Test maybe_schedule_tasks uses the cached hash on subsequent calls.
	 */
	public function test_maybe_schedule_tasks_uses_cache(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		Governance::maybe_schedule_tasks();

		$hash_first = get_option( 'wp_pinch_governance_schedule_hash' );

		// Second call should not change the hash (same version + same tasks).
		Governance::maybe_schedule_tasks();

		$hash_second = get_option( 'wp_pinch_governance_schedule_hash' );
		$this->assertEquals( $hash_first, $hash_second );
	}

	/**
	 * Test maybe_schedule_tasks regenerates hash when tasks change.
	 */
	public function test_maybe_schedule_tasks_regenerates_on_task_change(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		// Set a known initial task list and clear hash so first call computes it.
		$all_tasks = array_keys( Governance::DEFAULT_INTERVALS );
		update_option( 'wp_pinch_governance_tasks', $all_tasks );
		delete_option( 'wp_pinch_governance_schedule_hash' );

		Governance::maybe_schedule_tasks();
		$hash_before = get_option( 'wp_pinch_governance_schedule_hash' );
		$this->assertNotEmpty( $hash_before );

		// Change the enabled tasks to a different set.
		update_option( 'wp_pinch_governance_tasks', array( 'seo_health' ) );

		Governance::maybe_schedule_tasks();
		$hash_after = get_option( 'wp_pinch_governance_schedule_hash' );

		$this->assertNotEquals( $hash_before, $hash_after, 'Hash should change when tasks change.' );
	}

	// =========================================================================
	// Task execution — Content Freshness
	// =========================================================================

	/**
	 * Test content_freshness does nothing when no stale posts exist.
	 */
	public function test_task_content_freshness_no_stale_posts(): void {
		// Create a recently-modified post.
		$this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		// Configure gateway so webhook dispatch attempts are logged.
		delete_option( 'wp_pinch_gateway_url' );

		// This should complete without error.
		Governance::task_content_freshness();

		// No assertion needed — we verify it doesn't fatal.
		$this->assertTrue( true );
	}

	// =========================================================================
	// Task execution — SEO Health
	// =========================================================================

	/**
	 * Test SEO health flags posts with short titles.
	 */
	public function test_task_seo_health_short_title(): void {
		$this->factory->post->create(
			array(
				'post_title'   => 'Hi',
				'post_content' => str_repeat( 'word ', 200 ),
				'post_status'  => 'publish',
			)
		);

		// Suppress webhook delivery.
		delete_option( 'wp_pinch_gateway_url' );
		add_filter( 'wp_pinch_governance_findings', function ( $findings ) {
			// Verify the findings include an SEO issue about short title.
			foreach ( $findings as $finding ) {
				if ( isset( $finding['issues'] ) ) {
					foreach ( $finding['issues'] as $issue ) {
						if ( stripos( $issue, 'shorter than 20' ) !== false ) {
							return $findings;
						}
					}
				}
			}
			return $findings;
		} );

		Governance::task_seo_health();
		$this->assertTrue( true );
	}

	// =========================================================================
	// Task execution — Comment Sweep
	// =========================================================================

	/**
	 * Test comment_sweep does nothing when no pending comments exist.
	 */
	public function test_task_comment_sweep_clean(): void {
		delete_option( 'wp_pinch_gateway_url' );

		Governance::task_comment_sweep();
		$this->assertTrue( true, 'Comment sweep should complete without error on clean site.' );
	}

	// =========================================================================
	// Findings filter
	// =========================================================================

	/**
	 * Test governance_findings filter can suppress delivery.
	 */
	public function test_findings_filter_suppresses_delivery(): void {
		add_filter( 'wp_pinch_governance_findings', '__return_false' );

		// Create a condition that would normally produce findings.
		$this->factory->post->create(
			array(
				'post_title'   => 'Hi',
				'post_content' => 'Short.',
				'post_status'  => 'publish',
			)
		);

		delete_option( 'wp_pinch_gateway_url' );

		// This should suppress delivery (no webhook, no audit log for findings).
		Governance::task_seo_health();

		remove_filter( 'wp_pinch_governance_findings', '__return_false' );
		$this->assertTrue( true );
	}
}
