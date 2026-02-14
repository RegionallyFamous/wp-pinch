<?php
/**
 * Utility helpers for WP Pinch.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * General utilities.
 */
class Utils {

	/**
	 * Mask a token for safe logging (shows only last 4 chars).
	 *
	 * Use when logging or including tokens in error messages, debug output,
	 * or Audit_Table context. Never log or expose full tokens.
	 *
	 * @param string $token Raw token (e.g. API token, capture token).
	 * @return string Masked form: "****" or "****" + last 4 chars if length >= 4.
	 */
	public static function mask_token( string $token ): string {
		if ( '' === $token ) {
			return '';
		}
		$len = strlen( $token );
		if ( $len < 4 ) {
			return '****';
		}
		return '****' . substr( $token, -4 );
	}

	/**
	 * Get the preferred content format for block-style output (e.g. Molt faq_blocks).
	 *
	 * Returns 'blocks' for Gutenberg block markup, 'html' for classic HTML.
	 * Defaults to 'html' when the Classic Editor plugin is active and set to
	 * replace the block editor with the classic editor.
	 *
	 * @return string 'blocks' or 'html'
	 */
	public static function get_preferred_content_format(): string {
		$default = 'blocks';

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'classic-editor/classic-editor.php' ) ) {
			$replace = get_option( 'classic-editor-replace', 'block' );
			if ( 'classic' === $replace ) {
				$default = 'html';
			}
		}

		return (string) apply_filters( 'wp_pinch_preferred_content_format', $default );
	}
}
