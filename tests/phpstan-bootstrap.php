<?php
/**
 * PHPStan bootstrap file.
 *
 * Defines constants that are normally set by the main plugin file,
 * so PHPStan can resolve them during static analysis.
 *
 * @package WP_Pinch
 */

// Plugin constants (mirrored from wp-pinch.php).
if ( ! defined( 'WP_PINCH_VERSION' ) ) {
	define( 'WP_PINCH_VERSION', '1.0.1' );
}
if ( ! defined( 'WP_PINCH_FILE' ) ) {
	define( 'WP_PINCH_FILE', dirname( __DIR__ ) . '/wp-pinch.php' );
}
if ( ! defined( 'WP_PINCH_DIR' ) ) {
	define( 'WP_PINCH_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WP_PINCH_URL' ) ) {
	define( 'WP_PINCH_URL', 'https://example.com/wp-content/plugins/wp-pinch/' );
}
