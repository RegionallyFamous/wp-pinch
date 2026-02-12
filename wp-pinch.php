<?php
/**
 * Plugin Name:       WP Pinch
 * Plugin URI:        https://github.com/RegionallyFamous/wp-pinch
 * Description:       OpenClaw + WordPress integration — bidirectional MCP, autonomous governance, conversational site management from any messaging app.
 * Version:           2.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Nick Hamze
 * Author URI:        https://github.com/RegionallyFamous
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-pinch
 * Domain Path:       /languages
 * Update URI:        false
 *
 * @package WP_Pinch
 *
 * Copyright (C) 2026 Nick Hamze
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This plugin uses the following open-source projects:
 *
 * - WordPress (https://wordpress.org/) by the WordPress community — GPL-2.0-or-later
 * - Abilities API and MCP Adapter by WordPress/Automattic — GPL-2.0-or-later
 * - Action Scheduler (https://actionscheduler.org/) by Automattic/WooCommerce — GPL-3.0-or-later
 * - Jetpack Autoloader (https://github.com/Automattic/jetpack-autoloader) by Automattic — GPL-2.0-or-later
 * - Gutenberg and the Interactivity API by Automattic and the WordPress community — GPL-2.0-or-later
 * - @wordpress/scripts, @wordpress/env, @wordpress/interactivity by Automattic — GPL-2.0-or-later
 * - WP-CLI (https://wp-cli.org/) — MIT
 * - OpenClaw (https://github.com/nicepkg/openclaw) — Open source
 */

defined( 'ABSPATH' ) || exit;

defined( 'WP_PINCH_VERSION' ) || define( 'WP_PINCH_VERSION', '2.0.0' );
defined( 'WP_PINCH_FILE' ) || define( 'WP_PINCH_FILE', __FILE__ );
defined( 'WP_PINCH_DIR' ) || define( 'WP_PINCH_DIR', plugin_dir_path( __FILE__ ) );
defined( 'WP_PINCH_URL' ) || define( 'WP_PINCH_URL', plugin_dir_url( __FILE__ ) );

// Jetpack Autoloader (prevents version conflicts with other plugins).
if ( file_exists( WP_PINCH_DIR . 'vendor/autoload_packages.php' ) ) {
	require_once WP_PINCH_DIR . 'vendor/autoload_packages.php';
} elseif ( file_exists( WP_PINCH_DIR . 'vendor/autoload.php' ) ) {
	require_once WP_PINCH_DIR . 'vendor/autoload.php';
}

// Core class includes.
require_once WP_PINCH_DIR . 'includes/class-plugin.php';
require_once WP_PINCH_DIR . 'includes/class-audit-table.php';
require_once WP_PINCH_DIR . 'includes/class-mcp-server.php';
require_once WP_PINCH_DIR . 'includes/class-abilities.php';
require_once WP_PINCH_DIR . 'includes/class-webhook-dispatcher.php';
require_once WP_PINCH_DIR . 'includes/class-governance.php';
require_once WP_PINCH_DIR . 'includes/class-settings.php';
require_once WP_PINCH_DIR . 'includes/class-rest-controller.php';
require_once WP_PINCH_DIR . 'includes/class-privacy.php';
require_once WP_PINCH_DIR . 'includes/class-site-health.php';
require_once WP_PINCH_DIR . 'includes/class-circuit-breaker.php';
require_once WP_PINCH_DIR . 'includes/class-feature-flags.php';

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WP_PINCH_DIR . 'includes/class-cli.php';
}

/**
 * Retrieve the list of ability names registered by WP Pinch.
 *
 * Used by Class_MCP_Server to register abilities on the custom server.
 *
 * @return string[]
 */
function wp_pinch_get_ability_names(): array {
	return WP_Pinch\Abilities::get_ability_names();
}

// Boot the plugin.
WP_Pinch\Plugin::instance();
