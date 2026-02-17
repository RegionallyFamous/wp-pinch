<?php
/**
 * Plugin Name:       WP Pinch
 * Plugin URI:        https://wp-pinch.com
 * Description:       OpenClaw + WordPress integration — bidirectional MCP, autonomous governance, conversational site management from any messaging app.
 * Version:           3.0.1
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Nick Hamze
 * Author URI:        https://github.com/RegionallyFamous
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-pinch
 * Domain Path:       /languages
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
 * - OpenClaw (https://github.com/openclaw/openclaw) — Personal AI assistant framework
 */

defined( 'ABSPATH' ) || exit;

defined( 'WP_PINCH_VERSION' ) || define( 'WP_PINCH_VERSION', '3.0.1' );
defined( 'WP_PINCH_FILE' ) || define( 'WP_PINCH_FILE', __FILE__ );
defined( 'WP_PINCH_DIR' ) || define( 'WP_PINCH_DIR', plugin_dir_path( __FILE__ ) );
defined( 'WP_PINCH_URL' ) || define( 'WP_PINCH_URL', plugin_dir_url( __FILE__ ) );

// Polyfill wp_execute_ability for WP 6.9 when it provides wp_get_ability but not wp_execute_ability.
if ( ! function_exists( 'wp_execute_ability' ) && function_exists( 'wp_get_ability' ) ) {
	require_once WP_PINCH_DIR . 'includes/abilities-compat.php';
}

// Jetpack Autoloader (prevents version conflicts with other plugins).
if ( file_exists( WP_PINCH_DIR . 'vendor/autoload_packages.php' ) ) {
	require_once WP_PINCH_DIR . 'vendor/autoload_packages.php';
} elseif ( file_exists( WP_PINCH_DIR . 'vendor/autoload.php' ) ) {
	require_once WP_PINCH_DIR . 'vendor/autoload.php';
}

// Core class includes.
require_once WP_PINCH_DIR . 'includes/class-plugin.php';
require_once WP_PINCH_DIR . 'includes/class-utils.php';
require_once WP_PINCH_DIR . 'includes/class-audit-table.php';
require_once WP_PINCH_DIR . 'includes/class-mcp-server.php';
require_once WP_PINCH_DIR . 'includes/class-abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/Content_Abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/Media_Abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/User_Comment_Abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/Settings_Abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/Analytics_Abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/QuickWin_Abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/PinchDrop_Abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/Menu_Meta_Revisions_Abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/Woo_Abilities.php';
require_once WP_PINCH_DIR . 'includes/Ability/GhostWriter_Molt_Abilities.php';
require_once WP_PINCH_DIR . 'includes/class-webhook-dispatcher.php';
require_once WP_PINCH_DIR . 'includes/class-governance.php';
require_once WP_PINCH_DIR . 'includes/Governance/Tasks/Content_Freshness.php';
require_once WP_PINCH_DIR . 'includes/Governance/Tasks/SEO_Health.php';
require_once WP_PINCH_DIR . 'includes/Governance/Tasks/Comment_Sweep.php';
require_once WP_PINCH_DIR . 'includes/Governance/Tasks/Draft_Necromancer.php';
require_once WP_PINCH_DIR . 'includes/Governance/Tasks/Spaced_Resurfacing.php';
require_once WP_PINCH_DIR . 'includes/Governance/Tasks/Tide_Report.php';
require_once WP_PINCH_DIR . 'includes/Governance/Tasks/Broken_Links.php';
require_once WP_PINCH_DIR . 'includes/Governance/Tasks/Security_Scan.php';
require_once WP_PINCH_DIR . 'includes/class-settings.php';
require_once WP_PINCH_DIR . 'includes/Settings/Token_Storage.php';
require_once WP_PINCH_DIR . 'includes/Settings/Wizard.php';
require_once WP_PINCH_DIR . 'includes/Settings/Tabs/What_Can_I_Do_Tab.php';
require_once WP_PINCH_DIR . 'includes/Settings/Tabs/Connection_Tab.php';
require_once WP_PINCH_DIR . 'includes/Settings/Tabs/Webhooks_Tab.php';
require_once WP_PINCH_DIR . 'includes/Settings/Tabs/Governance_Tab.php';
require_once WP_PINCH_DIR . 'includes/Settings/Tabs/Abilities_Tab.php';
require_once WP_PINCH_DIR . 'includes/Settings/Tabs/Features_Tab.php';
require_once WP_PINCH_DIR . 'includes/Settings/Tabs/Usage_Tab.php';
require_once WP_PINCH_DIR . 'includes/Settings/Tabs/Audit_Tab.php';
require_once WP_PINCH_DIR . 'includes/class-rest-controller.php';
require_once WP_PINCH_DIR . 'includes/Rest/Auth.php';
require_once WP_PINCH_DIR . 'includes/Rest/Helpers.php';
require_once WP_PINCH_DIR . 'includes/Rest/Write_Budget.php';
require_once WP_PINCH_DIR . 'includes/Rest/Schemas.php';
require_once WP_PINCH_DIR . 'includes/Rest/Status.php';
require_once WP_PINCH_DIR . 'includes/Rest/Preview_Approve.php';
require_once WP_PINCH_DIR . 'includes/Rest/Ghostwrite.php';
require_once WP_PINCH_DIR . 'includes/Rest/Molt.php';
require_once WP_PINCH_DIR . 'includes/Rest/Incoming_Hook.php';
require_once WP_PINCH_DIR . 'includes/Rest/Chat.php';
require_once WP_PINCH_DIR . 'includes/Rest/Capture.php';
require_once WP_PINCH_DIR . 'includes/class-privacy.php';
require_once WP_PINCH_DIR . 'includes/class-site-health.php';
require_once WP_PINCH_DIR . 'includes/class-circuit-breaker.php';
require_once WP_PINCH_DIR . 'includes/class-feature-flags.php';
require_once WP_PINCH_DIR . 'includes/class-block-bindings.php';
require_once WP_PINCH_DIR . 'includes/class-openclaw-role.php';
require_once WP_PINCH_DIR . 'includes/class-prompt-sanitizer.php';
require_once WP_PINCH_DIR . 'includes/class-approval-queue.php';
require_once WP_PINCH_DIR . 'includes/class-ghost-writer.php';
require_once WP_PINCH_DIR . 'includes/class-molt.php';
require_once WP_PINCH_DIR . 'includes/class-rest-availability.php';
require_once WP_PINCH_DIR . 'includes/class-dashboard-widget.php';

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

/**
 * Show an in-plugin-list upgrade notice for major versions.
 *
 * Fires on the Plugins page between the plugin row and the update row.
 * Use this to warn about breaking changes before the user updates.
 *
 * @param array  $plugin_data Plugin metadata.
 * @param object $response    Update response object from the API.
 */
function wp_pinch_upgrade_notice( $plugin_data, $response ): void {
	if ( ! isset( $response->new_version ) ) {
		return;
	}

	$current_major = (int) explode( '.', WP_PINCH_VERSION )[0];
	$new_major     = (int) explode( '.', $response->new_version )[0];

	// Only show notice for major version bumps.
	if ( $new_major <= $current_major ) {
		return;
	}

	printf(
		'<div class="update-message notice inline notice-warning notice-alt"><p><strong>%s</strong> %s</p></div>',
		esc_html__( 'Important:', 'wp-pinch' ),
		esc_html(
			sprintf(
				/* translators: %s: new version number */
				__( 'WP Pinch %s is a major release with breaking changes. Please review the changelog and back up your site before updating.', 'wp-pinch' ),
				$response->new_version
			)
		)
	);
}
add_action( 'in_plugin_update_message-' . plugin_basename( __FILE__ ), 'wp_pinch_upgrade_notice', 10, 2 );

// Boot the plugin.
WP_Pinch\Plugin::instance();
