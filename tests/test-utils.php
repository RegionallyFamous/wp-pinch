<?php
/**
 * Tests for the Utils class.
 *
 * @package WP_Pinch
 */

use WP_Pinch\Utils;

/**
 * Test Utils helpers.
 */
class Test_Utils extends WP_UnitTestCase {

	/**
	 * Test mask_token short string.
	 */
	public function test_mask_token_short(): void {
		$this->assertSame( '****', Utils::mask_token( 'ab' ) );
	}

	/**
	 * Test mask_token shows last four chars when length >= 4.
	 */
	public function test_mask_token_masks_with_last_four(): void {
		$this->assertSame( '****xyz1', Utils::mask_token( 'secretxyz1' ) );
	}

	/**
	 * Test mask_token empty string.
	 */
	public function test_mask_token_empty(): void {
		$this->assertSame( '', Utils::mask_token( '' ) );
	}

	/**
	 * Test mask_token with exactly 4 chars shows last four.
	 */
	public function test_mask_token_four_chars(): void {
		$this->assertSame( '****abcd', Utils::mask_token( 'abcd' ) );
	}

	/**
	 * Test mask_token with 3 chars returns asterisks only.
	 */
	public function test_mask_token_three_chars(): void {
		$this->assertSame( '****', Utils::mask_token( 'abc' ) );
	}

	/**
	 * Test get_preferred_content_format returns blocks or html.
	 */
	public function test_get_preferred_content_format_returns_valid(): void {
		$format = Utils::get_preferred_content_format();
		$this->assertContains( $format, array( 'blocks', 'html' ), 'Preferred content format should be blocks or html.' );
	}

	/**
	 * Test get_preferred_content_format is filterable.
	 */
	public function test_get_preferred_content_format_filter(): void {
		add_filter( 'wp_pinch_preferred_content_format', function () {
			return 'html';
		} );

		$this->assertSame( 'html', Utils::get_preferred_content_format() );

		remove_all_filters( 'wp_pinch_preferred_content_format' );
	}
}
