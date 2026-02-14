<?php
/**
 * Block Bindings API integration for Pinch Chat block.
 *
 * Enables binding block attributes (agentId, placeholder) to post meta or site options
 * via the WordPress Block Bindings API (6.5+).
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Block Bindings sources and declares Pinch Chat attributes as bindable.
 */
class Block_Bindings {

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		// Block Bindings API is available from WordPress 6.5.
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'register_post_meta' ), 15 );
		add_action( 'init', array( __CLASS__, 'register_sources' ), 20 );
		add_filter( 'block_bindings_supported_attributes_wp-pinch/chat', array( __CLASS__, 'supported_attributes' ) );
	}

	/**
	 * Register post meta for per-post overrides (required for core/post-meta binding).
	 *
	 * Meta keys must not start with underscore and must have show_in_rest => true.
	 */
	public static function register_post_meta(): void {
		register_post_meta(
			'',
			'wp_pinch_chat_agent_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'OpenClaw agent ID override for the Pinch Chat block on this post/page.', 'wp-pinch' ),
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			'',
			'wp_pinch_chat_placeholder',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'Placeholder text override for the Pinch Chat block on this post/page.', 'wp-pinch' ),
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}

	/**
	 * Register custom Block Bindings sources for site-level values.
	 */
	public static function register_sources(): void {
		// Site-level placeholder (reads from option; useful when no post context).
		register_block_bindings_source(
			'wp-pinch/chat-placeholder',
			array(
				'label'              => __( 'WP Pinch Chat Placeholder', 'wp-pinch' ),
				'get_value_callback' => array( __CLASS__, 'get_placeholder_value' ),
			)
		);

		// Site-level agent ID (reads from option).
		register_block_bindings_source(
			'wp-pinch/agent-id',
			array(
				'label'              => __( 'WP Pinch Agent ID', 'wp-pinch' ),
				'get_value_callback' => array( __CLASS__, 'get_agent_id_value' ),
			)
		);
	}

	/**
	 * Get placeholder value from site option.
	 *
	 * @param array    $source_args    Source arguments.
	 * @param \WP_Block $block_instance Block instance.
	 * @param string   $attribute_name Attribute name.
	 * @return string|null
	 */
	public static function get_placeholder_value( array $source_args, $block_instance, string $attribute_name ) {
		$value = get_option( 'wp_pinch_chat_placeholder', '' );
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Get agent ID value from site option.
	 *
	 * @param array    $source_args    Source arguments.
	 * @param \WP_Block $block_instance Block instance.
	 * @param string   $attribute_name Attribute name.
	 * @return string|null
	 */
	public static function get_agent_id_value( array $source_args, $block_instance, string $attribute_name ) {
		$value = get_option( 'wp_pinch_agent_id', '' );
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Declare which Pinch Chat attributes support block bindings.
	 *
	 * @param string[] $supported_attributes List of attribute names.
	 * @return string[]
	 */
	public static function supported_attributes( array $supported_attributes ): array {
		$supported_attributes[] = 'agentId';
		$supported_attributes[] = 'placeholder';
		return $supported_attributes;
	}
}
