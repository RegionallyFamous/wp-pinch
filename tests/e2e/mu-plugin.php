<?php
/**
 * MU-plugin for E2E tests.
 *
 * Provides test helpers and fixtures for Playwright tests.
 *
 * @package WP_Pinch
 */

// Only active in E2E test environments.
if ( ! defined( 'WP_TESTS_E2E' ) ) {
	return;
}
