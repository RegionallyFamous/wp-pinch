<?php
/**
 * Feature Flags — simple boolean feature toggle system.
 *
 * Flags are stored in a single option and can be overridden via filter.
 * This allows shipping features behind flags and rolling them out gradually.
 *
 * @package WP_Pinch
 * @since   2.1.0
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Feature flag management.
 */
class Feature_Flags {

	/**
	 * Option key for stored flags.
	 */
	const OPTION_KEY = 'wp_pinch_feature_flags';

	/**
	 * Known flags with their default values.
	 *
	 * @var array<string, bool>
	 */
	const DEFAULTS = array(
		'streaming_chat'    => false,
		'webhook_signatures' => true,
		'circuit_breaker'   => true,
		'ability_toggle'    => true,
		'webhook_dashboard' => true,
		'audit_search'      => true,
		'health_endpoint'   => true,
	);

	/**
	 * Check whether a feature flag is enabled.
	 *
	 * @param string $flag Flag name.
	 * @return bool
	 */
	public static function is_enabled( string $flag ): bool {
		$flags = self::get_all();
		$value = $flags[ $flag ] ?? false;

		/**
		 * Filter a feature flag value.
		 *
		 * Allows code-level overrides (e.g. in wp-config.php or a mu-plugin):
		 *
		 *     add_filter( 'wp_pinch_feature_flag', function( $enabled, $flag ) {
		 *         if ( 'streaming_chat' === $flag ) return true;
		 *         return $enabled;
		 *     }, 10, 2 );
		 *
		 * @since 2.1.0
		 *
		 * @param bool   $value Current flag value.
		 * @param string $flag  Flag name.
		 */
		return (bool) apply_filters( 'wp_pinch_feature_flag', $value, $flag );
	}

	/**
	 * Get all flags with their current values.
	 *
	 * @return array<string, bool>
	 */
	public static function get_all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::DEFAULTS, $stored );
	}

	/**
	 * Enable a feature flag.
	 *
	 * @param string $flag Flag name.
	 */
	public static function enable( string $flag ): void {
		self::set( $flag, true );
	}

	/**
	 * Disable a feature flag.
	 *
	 * @param string $flag Flag name.
	 */
	public static function disable( string $flag ): void {
		self::set( $flag, false );
	}

	/**
	 * Set a flag value.
	 *
	 * @param string $flag  Flag name.
	 * @param bool   $value Enabled state.
	 */
	private static function set( string $flag, bool $value ): void {
		$flags = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $flags ) ) {
			$flags = array();
		}

		$flags[ $flag ] = $value;
		update_option( self::OPTION_KEY, $flags, false );
	}

	/**
	 * Reset all flags to defaults.
	 */
	public static function reset(): void {
		delete_option( self::OPTION_KEY );
	}
}
