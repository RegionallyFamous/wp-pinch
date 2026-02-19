<?php
/**
 * Documentation consistency tests.
 *
 * @package WP_Pinch
 */

/**
 * Test docs/readme messaging stays aligned with shipped capability surface.
 */
class Test_Docs extends WP_UnitTestCase {

	/**
	 * Read a project file.
	 *
	 * @param string $relative_path Relative path from project root.
	 * @return string
	 */
	private function read_project_file( string $relative_path ): string {
		$path = dirname( __DIR__ ) . '/' . ltrim( $relative_path, '/' );
		$this->assertFileExists( $path, "Expected docs file to exist: {$relative_path}" );
		$contents = file_get_contents( $path );
		$this->assertNotFalse( $contents, "Expected docs file to be readable: {$relative_path}" );
		return (string) $contents;
	}

	/**
	 * Ensure current top-level product messaging has aligned capability counts.
	 */
	public function test_docs_count_messaging_is_consistent(): void {
		$readme_md  = $this->read_project_file( 'README.md' );
		$readme_txt = $this->read_project_file( 'readme.txt' );
		$wiki_ref   = $this->read_project_file( 'wiki/Abilities-Reference.md' );

		$this->assertStringContainsString( '88 core abilities', $readme_md );
		$this->assertStringContainsString( '30 WooCommerce', $readme_md );
		$this->assertStringContainsString( '122 total', $readme_md );

		$this->assertStringContainsString( '88 core abilities', $readme_txt );
		$this->assertStringContainsString( '30 WooCommerce', $readme_txt );
		$this->assertStringContainsString( '122 total', $readme_txt );

		$this->assertStringContainsString( '88 core abilities', $wiki_ref );
		$this->assertStringContainsString( '30 WooCommerce', $wiki_ref );
		$this->assertStringContainsString( '122 total', $wiki_ref );
	}

	/**
	 * Ensure README explains why Woo expansion matters, not only what changed.
	 */
	public function test_readme_includes_woo_why_section(): void {
		$readme_md = $this->read_project_file( 'README.md' );

		$this->assertStringContainsString( 'Why the WooCommerce expansion matters', $readme_md );
		$this->assertStringContainsString( 'Fewer handoffs', $readme_md );
		$this->assertStringContainsString( 'Safer store ops', $readme_md );
	}

	/**
	 * Ensure readme.txt includes why-focused Woo messaging near description.
	 */
	public function test_readme_txt_includes_woo_why_messaging(): void {
		$readme_txt = $this->read_project_file( 'readme.txt' );

		$this->assertStringContainsString( '30 WooCommerce abilities when your shop is active', $readme_txt );
		$this->assertStringContainsString( 'Why this matters:', $readme_txt );
		$this->assertStringContainsString( 'Safer commerce operations', $readme_txt );
	}

	/**
	 * Ensure changelog has an unreleased entry for Woo expansion and rationale.
	 */
	public function test_changelog_unreleased_mentions_woo_expansion(): void {
		$changelog = $this->read_project_file( 'CHANGELOG.md' );

		$this->assertStringContainsString( '## [Unreleased]', $changelog );
		$this->assertStringContainsString( 'WooCommerce automation now covers the full day-to-day operator loop', $changelog );
		$this->assertStringContainsString( 'WooCommerce ability expansion', $changelog );
		$this->assertStringContainsString( 'Safer Woo defaults', $changelog );
	}
}
