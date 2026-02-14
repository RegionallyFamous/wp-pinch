<?php
/**
 * Abilities API compatibility layer for WordPress 6.9.
 *
 * Provides wp_execute_ability() when wp_get_ability() exists but wp_execute_ability() does not.
 * WordPress 6.9 added the Abilities API with wp_register_ability and wp_get_ability;
 * wp_execute_ability may be added in a later release.
 *
 * @package WP_Pinch
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wp_execute_ability' ) && function_exists( 'wp_get_ability' ) ) {

	/**
	 * Execute a registered ability by name.
	 *
	 * @param string $ability_name The namespaced ability name (e.g. 'wp-pinch/update-post').
	 * @param array  $params       Optional. Input parameters for the ability. Default empty array.
	 * @return mixed The ability result, or WP_Error on failure.
	 */
	function wp_execute_ability( string $ability_name, array $params = array() ) {
		$ability = wp_get_ability( $ability_name );
		if ( ! $ability ) {
			return new \WP_Error(
				'ability_not_found',
				/* translators: %s: ability name */
				sprintf( __( 'Ability "%s" not found.', 'wp-pinch' ), $ability_name ),
				array( 'status' => 404 )
			);
		}
		return $ability->execute( $params );
	}
}
