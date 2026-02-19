<?php
/**
 * Maintainability guardrails for long-term health.
 *
 * @package WP_Pinch
 */

/**
 * Prevent unbounded file growth in core PHP surfaces.
 */
class Test_Maintainability extends WP_UnitTestCase {

	/**
	 * Per-file line budgets for known high-surface classes.
	 *
	 * Keep these explicit so growing a hotspot requires a conscious decision.
	 *
	 * @var array<string, int>
	 */
	private const FILE_LINE_BUDGETS = array(
		'includes/Ability/Woo_Abilities.php'               => 200,
		'includes/Ability/Woo/Woo_Products_Orders_Execute_Trait.php' => 600,
		'includes/Ability/Woo/Woo_Inventory_Execute_Trait.php' => 450,
		'includes/Ability/Woo/Woo_Operations_Insights_Execute_Trait.php' => 700,
		'includes/Ability/Woo/Woo_Register_Trait.php'      => 80,
		'includes/Ability/Woo/Woo_Register_Products_Orders_Trait.php' => 400,
		'includes/Ability/Woo/Woo_Register_Inventory_Operations_Trait.php' => 360,
		'includes/Ability/Woo/Woo_Register_Commercial_Intelligence_Trait.php' => 300,
		'includes/Ability/Analytics/Analytics_Execute_Trait.php' => 750,
		'includes/Ability/Menu_Meta_Revisions_Abilities.php' => 400,
		'includes/Ability/MenuMeta/Menu_Meta_Revisions_Execute_Trait.php' => 800,
		'includes/Ability/QuickWin_Abilities.php'          => 500,
		'includes/Ability/QuickWin/QuickWin_Execute_Trait.php' => 700,
		'includes/class-settings.php'                      => 650,
		'includes/Settings/Settings_Admin_Pages_Trait.php' => 500,
		'includes/Ability/Analytics_Abilities.php'         => 300,
		'includes/class-abilities.php'                     => 500,
		'includes/Ability/Core_Passthrough_Trait.php'      => 600,
		'includes/Ability/GEO_SEO_Abilities.php'           => 300,
		'includes/Ability/GEO/GEO_SEO_Execute_Trait.php'   => 800,
	);

	/**
	 * Default limit for all other PHP files in includes/.
	 *
	 * @var int
	 */
	private const DEFAULT_LINE_BUDGET = 900;

	/**
	 * Includes PHP files should stay within declared budgets.
	 */
	public function test_includes_php_files_stay_within_line_budgets(): void {
		$root  = dirname( __DIR__ );
		$files = $this->collect_php_files( $root . '/includes' );

		$this->assertNotEmpty( $files );

		foreach ( $files as $absolute_path ) {
			$relative = ltrim( str_replace( $root, '', $absolute_path ), '/' );
			$lines    = $this->count_lines( $absolute_path );
			$budget   = self::FILE_LINE_BUDGETS[ $relative ] ?? self::DEFAULT_LINE_BUDGET;

			$this->assertLessThanOrEqual(
				$budget,
				$lines,
				sprintf(
					'%s has %d lines (budget: %d). Split by domain/helpers/traits before growing further.',
					$relative,
					$lines,
					$budget
				)
			);
		}
	}

	/**
	 * Collect PHP files recursively.
	 *
	 * @param string $directory Directory.
	 * @return array<int, string>
	 */
	private function collect_php_files( string $directory ): array {
		$results  = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo ) {
				continue;
			}
			if ( 'php' !== strtolower( (string) $file->getExtension() ) ) {
				continue;
			}
			$results[] = $file->getPathname();
		}

		sort( $results );
		return $results;
	}

	/**
	 * Count lines in a file.
	 *
	 * @param string $path File path.
	 * @return int
	 */
	private function count_lines( string $path ): int {
		$content = file_get_contents( $path );
		$this->assertNotFalse( $content );
		return substr_count( (string) $content, "\n" ) + 1;
	}
}
