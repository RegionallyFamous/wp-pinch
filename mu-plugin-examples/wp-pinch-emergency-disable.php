<?php
/**
 * Emergency disable drop-in for WP Pinch.
 *
 * Copy this file to wp-content/mu-plugins/wp-pinch-emergency-disable.php to
 * immediately disable all WP Pinch API access. Must-load plugins (mu-plugins)
 * load before regular plugins, so this takes effect as soon as WordPress boots.
 *
 * To re-enable: delete this file or rename it (e.g. to .disabled).
 *
 * @package WP_Pinch
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_PINCH_DISABLED' ) ) {
	define( 'WP_PINCH_DISABLED', true );
}
