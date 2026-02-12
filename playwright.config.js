/**
 * Playwright E2E configuration for WP Pinch.
 *
 * Uses @wordpress/scripts Playwright test runner.
 * Start wp-env before running: npx wp-env start
 *
 * @package WP_Pinch
 */

const { defineConfig } = require( '@playwright/test' );

const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = defineConfig( {
	...baseConfig,
	testDir: './tests/e2e',
	outputDir: './tests/e2e/results',
} );
