<?php
/**
 * Tests for the Feature_Flags class.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Tests;

use WP_Pinch\Feature_Flags;
use WP_UnitTestCase;

/**
 * Feature flags test suite.
 */
class Test_Feature_Flags extends WP_UnitTestCase {

	/**
	 * Clean up before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Feature_Flags::reset();
		remove_all_filters( 'wp_pinch_feature_flag' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		Feature_Flags::reset();
		remove_all_filters( 'wp_pinch_feature_flag' );
		parent::tear_down();
	}

	// ---- Constants ---------------------------------------------------------

	public function test_option_key_constant(): void {
		$this->assertSame( 'wp_pinch_feature_flags', Feature_Flags::OPTION_KEY );
	}

	public function test_defaults_constant_is_array(): void {
		$this->assertIsArray( Feature_Flags::DEFAULTS );
		$this->assertNotEmpty( Feature_Flags::DEFAULTS );
	}

	public function test_defaults_contains_expected_flags(): void {
		$expected = array(
			'streaming_chat',
			'webhook_signatures',
			'circuit_breaker',
			'ability_toggle',
			'webhook_dashboard',
			'audit_search',
			'health_endpoint',
			'pinchdrop_engine',
		);

		foreach ( $expected as $flag ) {
			$this->assertArrayHasKey( $flag, Feature_Flags::DEFAULTS, "Missing default flag: {$flag}" );
		}
	}

	public function test_defaults_are_boolean(): void {
		foreach ( Feature_Flags::DEFAULTS as $flag => $value ) {
			$this->assertIsBool( $value, "Default for '{$flag}' should be boolean." );
		}
	}

	// ---- get_all -----------------------------------------------------------

	public function test_get_all_returns_defaults_when_no_option(): void {
		$flags = Feature_Flags::get_all();
		$this->assertSame( Feature_Flags::DEFAULTS, $flags );
	}

	public function test_get_all_merges_stored_with_defaults(): void {
		update_option( Feature_Flags::OPTION_KEY, array( 'streaming_chat' => true ) );
		$flags = Feature_Flags::get_all();
		$this->assertTrue( $flags['streaming_chat'] );
		// Other flags should still be at defaults.
		$this->assertSame( Feature_Flags::DEFAULTS['circuit_breaker'], $flags['circuit_breaker'] );
	}

	public function test_get_all_handles_non_array_option(): void {
		update_option( Feature_Flags::OPTION_KEY, 'invalid' );
		$flags = Feature_Flags::get_all();
		$this->assertSame( Feature_Flags::DEFAULTS, $flags );
	}

	// ---- is_enabled --------------------------------------------------------

	public function test_is_enabled_returns_default_for_known_flag(): void {
		$this->assertFalse( Feature_Flags::is_enabled( 'streaming_chat' ) );
		$this->assertTrue( Feature_Flags::is_enabled( 'circuit_breaker' ) );
	}

	public function test_is_enabled_returns_false_for_unknown_flag(): void {
		$this->assertFalse( Feature_Flags::is_enabled( 'nonexistent_flag' ) );
	}

	public function test_is_enabled_respects_stored_override(): void {
		Feature_Flags::enable( 'streaming_chat' );
		$this->assertTrue( Feature_Flags::is_enabled( 'streaming_chat' ) );
	}

	// ---- enable / disable --------------------------------------------------

	public function test_enable_sets_flag_true(): void {
		Feature_Flags::enable( 'streaming_chat' );
		$this->assertTrue( Feature_Flags::is_enabled( 'streaming_chat' ) );
	}

	public function test_disable_sets_flag_false(): void {
		Feature_Flags::enable( 'circuit_breaker' );
		Feature_Flags::disable( 'circuit_breaker' );
		$this->assertFalse( Feature_Flags::is_enabled( 'circuit_breaker' ) );
	}

	public function test_enable_disable_persists_in_option(): void {
		Feature_Flags::enable( 'streaming_chat' );
		$stored = get_option( Feature_Flags::OPTION_KEY );
		$this->assertIsArray( $stored );
		$this->assertTrue( $stored['streaming_chat'] );

		Feature_Flags::disable( 'streaming_chat' );
		$stored = get_option( Feature_Flags::OPTION_KEY );
		$this->assertFalse( $stored['streaming_chat'] );
	}

	// ---- reset -------------------------------------------------------------

	public function test_reset_clears_all_flags(): void {
		Feature_Flags::enable( 'streaming_chat' );
		Feature_Flags::disable( 'circuit_breaker' );
		Feature_Flags::reset();

		$this->assertFalse( get_option( Feature_Flags::OPTION_KEY ) );
		$this->assertSame( Feature_Flags::DEFAULTS, Feature_Flags::get_all() );
	}

	// ---- Filter override ---------------------------------------------------

	public function test_filter_can_override_flag(): void {
		$this->assertFalse( Feature_Flags::is_enabled( 'streaming_chat' ) );

		add_filter(
			'wp_pinch_feature_flag',
			function ( $value, $flag ) {
				if ( 'streaming_chat' === $flag ) {
					return true;
				}
				return $value;
			},
			10,
			2
		);

		$this->assertTrue( Feature_Flags::is_enabled( 'streaming_chat' ) );
	}

	public function test_filter_receives_correct_flag_name(): void {
		$received_flag = null;

		add_filter(
			'wp_pinch_feature_flag',
			function ( $value, $flag ) use ( &$received_flag ) {
				$received_flag = $flag;
				return $value;
			},
			10,
			2
		);

		Feature_Flags::is_enabled( 'webhook_signatures' );
		$this->assertSame( 'webhook_signatures', $received_flag );
	}

	public function test_filter_overrides_stored_value(): void {
		Feature_Flags::enable( 'streaming_chat' );

		add_filter(
			'wp_pinch_feature_flag',
			function ( $value, $flag ) {
				if ( 'streaming_chat' === $flag ) {
					return false; // Override enabled to disabled.
				}
				return $value;
			},
			10,
			2
		);

		$this->assertFalse( Feature_Flags::is_enabled( 'streaming_chat' ) );
	}
}
