<?php
/**
 * Tests for the Circuit_Breaker class.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Tests;

use WP_Pinch\Circuit_Breaker;
use WP_UnitTestCase;

/**
 * Circuit breaker test suite.
 */
class Test_Circuit_Breaker extends WP_UnitTestCase {

	/**
	 * Clean up transients before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Circuit_Breaker::reset();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		Circuit_Breaker::reset();
		parent::tear_down();
	}

	// ---- Constants ---------------------------------------------------------

	public function test_failure_threshold_constant(): void {
		$this->assertSame( 3, Circuit_Breaker::FAILURE_THRESHOLD );
	}

	public function test_cooldown_seconds_constant(): void {
		$this->assertSame( 60, Circuit_Breaker::COOLDOWN_SECONDS );
	}

	public function test_state_constants(): void {
		$this->assertSame( 'closed', Circuit_Breaker::STATE_CLOSED );
		$this->assertSame( 'open', Circuit_Breaker::STATE_OPEN );
		$this->assertSame( 'half_open', Circuit_Breaker::STATE_HALF_OPEN );
	}

	// ---- Default state -----------------------------------------------------

	public function test_default_state_is_closed(): void {
		$this->assertSame( 'closed', Circuit_Breaker::get_state() );
	}

	public function test_is_available_when_closed(): void {
		$this->assertTrue( Circuit_Breaker::is_available() );
	}

	public function test_get_retry_after_when_closed(): void {
		$this->assertSame( 0, Circuit_Breaker::get_retry_after() );
	}

	// ---- Failure tracking --------------------------------------------------

	public function test_single_failure_stays_closed(): void {
		Circuit_Breaker::record_failure();
		$this->assertSame( 'closed', Circuit_Breaker::get_state() );
		$this->assertTrue( Circuit_Breaker::is_available() );
	}

	public function test_two_failures_stays_closed(): void {
		Circuit_Breaker::record_failure();
		Circuit_Breaker::record_failure();
		$this->assertSame( 'closed', Circuit_Breaker::get_state() );
		$this->assertTrue( Circuit_Breaker::is_available() );
	}

	public function test_threshold_failures_opens_circuit(): void {
		for ( $i = 0; $i < Circuit_Breaker::FAILURE_THRESHOLD; $i++ ) {
			Circuit_Breaker::record_failure();
		}
		$this->assertSame( 'open', Circuit_Breaker::get_state() );
	}

	public function test_is_not_available_when_open(): void {
		for ( $i = 0; $i < Circuit_Breaker::FAILURE_THRESHOLD; $i++ ) {
			Circuit_Breaker::record_failure();
		}
		$this->assertFalse( Circuit_Breaker::is_available() );
	}

	public function test_retry_after_positive_when_open(): void {
		for ( $i = 0; $i < Circuit_Breaker::FAILURE_THRESHOLD; $i++ ) {
			Circuit_Breaker::record_failure();
		}
		$retry = Circuit_Breaker::get_retry_after();
		$this->assertGreaterThan( 0, $retry );
		$this->assertLessThanOrEqual( Circuit_Breaker::COOLDOWN_SECONDS, $retry );
	}

	// ---- Recovery ----------------------------------------------------------

	public function test_record_success_resets_to_closed(): void {
		for ( $i = 0; $i < Circuit_Breaker::FAILURE_THRESHOLD; $i++ ) {
			Circuit_Breaker::record_failure();
		}
		$this->assertSame( 'open', Circuit_Breaker::get_state() );

		Circuit_Breaker::record_success();
		$this->assertSame( 'closed', Circuit_Breaker::get_state() );
		$this->assertTrue( Circuit_Breaker::is_available() );
		$this->assertSame( 0, Circuit_Breaker::get_retry_after() );
	}

	public function test_reset_clears_all_state(): void {
		for ( $i = 0; $i < Circuit_Breaker::FAILURE_THRESHOLD; $i++ ) {
			Circuit_Breaker::record_failure();
		}
		$this->assertSame( 'open', Circuit_Breaker::get_state() );

		Circuit_Breaker::reset();
		$this->assertSame( 'closed', Circuit_Breaker::get_state() );
		$this->assertTrue( Circuit_Breaker::is_available() );
	}

	// ---- Success resets failure counter ------------------------------------

	public function test_success_resets_failure_counter(): void {
		Circuit_Breaker::record_failure();
		Circuit_Breaker::record_failure();
		// 2 failures, then success should reset counter.
		Circuit_Breaker::record_success();

		// Now 1 more failure should not open (counter was reset).
		Circuit_Breaker::record_failure();
		$this->assertSame( 'closed', Circuit_Breaker::get_state() );
	}

	// ---- Additional failures past threshold --------------------------------

	public function test_extra_failures_keep_circuit_open(): void {
		for ( $i = 0; $i < Circuit_Breaker::FAILURE_THRESHOLD + 2; $i++ ) {
			Circuit_Breaker::record_failure();
		}
		$this->assertSame( 'open', Circuit_Breaker::get_state() );
		$this->assertFalse( Circuit_Breaker::is_available() );
	}
}
