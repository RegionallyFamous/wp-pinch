<?php
/**
 * PHPUnit bootstrap for WP Pinch tests.
 *
 * Loads the WordPress test suite and the plugin.
 *
 * @package WP_Pinch
 */

// Suppress Action Scheduler "called before data store initialized" notices for a clean run.
set_error_handler(
	function ( $severity, $message, $file, $line ) {
		if ( E_USER_NOTICE === $severity && (
			strpos( $message, 'Action Scheduler' ) !== false ||
			strpos( $message, 'data store was initialized' ) !== false
		) ) {
			return true;
		}
		return false;
	},
	E_USER_NOTICE
);

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Have you run bin/install-wp-tests.sh?" . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Load Action Scheduler before the plugin (Governance tests need as_has_scheduled_action).
 * Prefer vendor copy (composer dev dependency) so tests never skip for missing AS.
 */
function _manually_load_action_scheduler() {
	$paths = array(
		dirname( __DIR__ ) . '/vendor/woocommerce/action-scheduler/action-scheduler.php',
		WP_CONTENT_DIR . '/plugins/action-scheduler/action-scheduler.php',
		WP_CONTENT_DIR . '/plugins/action-scheduler.latest-stable/action-scheduler.php',
	);
	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
}
tests_add_filter( 'muplugins_loaded', '_manually_load_action_scheduler', 5 );

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/wp-pinch.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
