<?php
/**
 * Security scan governance task.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Governance\Tasks;

use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Security scan — outdated software, debug mode, file editing.
 */
class Security_Scan {

	/**
	 * Run the task.
	 */
	public static function run(): void {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$findings = array();

		$core_updates = get_core_updates();
		if ( ! empty( $core_updates ) && 'latest' !== ( $core_updates[0]->response ?? '' ) ) {
			$findings['core_update_available'] = true;
		}

		$plugin_updates = get_plugin_updates();
		if ( ! empty( $plugin_updates ) ) {
			$findings['plugin_updates_count'] = count( $plugin_updates );
			$findings['plugin_updates']       = array();
			foreach ( $plugin_updates as $file => $data ) {
				$findings['plugin_updates'][] = array(
					'name' => $data->Name, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				);
			}
		}

		$theme_updates = get_theme_updates();
		if ( ! empty( $theme_updates ) ) {
			$findings['theme_updates_count'] = count( $theme_updates );
			$findings['theme_updates']       = array();
			foreach ( $theme_updates as $slug => $theme ) {
				$findings['theme_updates'][] = array(
					'name' => $theme->get( 'Name' ),
				);
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$findings['debug_mode'] = true;
		}

		if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
			$findings['file_editing_enabled'] = true;
		}

		if ( empty( $findings ) ) {
			return;
		}

		$summary_parts = array();
		if ( isset( $findings['core_update_available'] ) ) {
			$summary_parts[] = 'WordPress core update available';
		}
		if ( ! empty( $findings['plugin_updates'] ) ) {
			$summary_parts[] = count( $findings['plugin_updates'] ) . ' plugin updates';
		}
		if ( ! empty( $findings['theme_updates'] ) ) {
			$summary_parts[] = count( $findings['theme_updates'] ) . ' theme updates';
		}
		if ( ! empty( $findings['debug_mode'] ) ) {
			$summary_parts[] = 'WP_DEBUG is enabled';
		}
		if ( ! empty( $findings['file_editing_enabled'] ) ) {
			$summary_parts[] = 'File editing is not disabled';
		}

		Governance::deliver_findings( 'security_scan', $findings, implode( '; ', $summary_parts ) . '.' );
	}
}
