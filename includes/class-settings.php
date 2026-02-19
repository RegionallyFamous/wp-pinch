<?php
/**
 * Settings page — tabbed admin UI at top-level WP Pinch menu.
 *
 * Tabs: Connection, Webhooks, Governance, Audit Log.
 * AJAX test-connection endpoint. Nonce verification on all forms.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page.
 */
class Settings {
	use Settings_Admin_Pages_Trait;

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_activation_redirect' ) );
		add_action( 'admin_init', array( \WP_Pinch\Settings\Wizard::class, 'maybe_finish_wizard' ) );
		add_action( 'admin_init', array( \WP_Pinch\Settings\Wizard::class, 'maybe_skip_wizard' ) );
		add_action( 'wp_ajax_wp_pinch_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_wp_pinch_create_openclaw_agent', array( __CLASS__, 'ajax_create_openclaw_agent' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_audit_export' ) );

		add_filter(
			'plugin_action_links_' . plugin_basename( WP_PINCH_FILE ),
			array( __CLASS__, 'add_action_links' )
		);
	}

	/**
	 * Get the API token (decrypted if stored encrypted). Migrates legacy plaintext to encrypted on first read.
	 *
	 * @return string
	 */
	public static function get_api_token(): string {
		$raw = get_option( 'wp_pinch_api_token', '' );
		if ( '' === $raw ) {
			return '';
		}
		if ( str_starts_with( $raw, \WP_Pinch\Settings\Token_Storage::PREFIX ) ) {
			$decrypted = \WP_Pinch\Settings\Token_Storage::decrypt_token( $raw );
			return is_string( $decrypted ) ? $decrypted : '';
		}
		// Legacy plaintext: migrate to encrypted and return plaintext.
		self::set_api_token( $raw );
		return $raw;
	}

	/**
	 * Set the API token (stored encrypted at rest). Use for migration or programmatic set.
	 *
	 * @param string $token Plaintext token.
	 * @return bool
	 */
	public static function set_api_token( string $token ): bool {
		if ( '' === $token ) {
			return update_option( 'wp_pinch_api_token', '' );
		}
		$encrypted = \WP_Pinch\Settings\Token_Storage::encrypt_token( $token );
		return null !== $encrypted && update_option( 'wp_pinch_api_token', $encrypted );
	}

	/**
	 * Get the network-wide API token (multisite only).
	 *
	 * @return string
	 */
	public static function get_network_api_token(): string {
		if ( ! is_multisite() ) {
			return '';
		}
		$raw = get_site_option( 'wp_pinch_network_api_token', '' );
		if ( '' === $raw ) {
			return '';
		}
		if ( str_starts_with( $raw, \WP_Pinch\Settings\Token_Storage::PREFIX ) ) {
			$decrypted = \WP_Pinch\Settings\Token_Storage::decrypt_token( $raw );
			return is_string( $decrypted ) ? $decrypted : '';
		}
		// Legacy plaintext: migrate to encrypted and return plaintext.
		self::set_network_api_token( $raw );
		return $raw;
	}

	/**
	 * Set the network-wide API token (multisite only). Stored encrypted at rest.
	 *
	 * @param string $token Plaintext token.
	 * @return bool
	 */
	public static function set_network_api_token( string $token ): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		if ( '' === $token ) {
			return update_site_option( 'wp_pinch_network_api_token', '' );
		}
		$encrypted = \WP_Pinch\Settings\Token_Storage::encrypt_token( $token );
		return null !== $encrypted && update_site_option( 'wp_pinch_network_api_token', $encrypted );
	}

	/**
	 * Redirect to WP Pinch settings (or wizard) after first activation.
	 */
	public static function maybe_activation_redirect(): void {
		if ( ! get_option( 'wp_pinch_activation_redirect', false ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_option( 'wp_pinch_activation_redirect' );
		wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch' ) );
		exit;
	}

	/**
	 * Add a "Settings" link on the Plugins list page.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public static function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wp-pinch' ) ),
			esc_html__( 'Settings', 'wp-pinch' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Add the top-level admin menu page.
	 */
	public static function add_menu(): void {
		add_menu_page(
			__( 'WP Pinch', 'wp-pinch' ),
			__( 'WP Pinch', 'wp-pinch' ),
			'manage_options',
			'wp-pinch',
			array( __CLASS__, 'render_page' ),
			'dashicons-controls-repeat',
			80
		);
	}

	/**
	 * Option definitions for data-driven registration.
	 *
	 * Each entry: group, option, type, default, sanitize (string callback name or callable).
	 *
	 * @return array<int, array{group: string, option: string, type: string, default: mixed, sanitize?: string|callable}>
	 */
	private static function get_option_definitions(): array {
		$thinking_allowed  = array( '', 'off', 'low', 'medium', 'high' );
		$pinchdrop_outputs = array( 'post', 'product_update', 'changelog', 'social' );

		return array(
			// Connection tab.
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_gateway_url',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'esc_url_raw',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_api_token',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => function ( $value ) {
					if ( str_repeat( "\u{2022}", 8 ) === $value || '' === $value ) {
						return self::get_api_token();
					}
					$plain = sanitize_text_field( $value );
					if ( '' === $plain ) {
						return '';
					}
					$encrypted = \WP_Pinch\Settings\Token_Storage::encrypt_token( $plain );
					return null !== $encrypted ? $encrypted : $plain;
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_api_disabled',
				'type'     => 'boolean',
				'default'  => false,
				'sanitize' => 'rest_sanitize_boolean',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_read_only_mode',
				'type'     => 'boolean',
				'default'  => false,
				'sanitize' => 'rest_sanitize_boolean',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_gateway_reply_strict_sanitize',
				'type'     => 'boolean',
				'default'  => false,
				'sanitize' => 'rest_sanitize_boolean',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_rate_limit',
				'type'     => 'integer',
				'default'  => 30,
				'sanitize' => 'absint',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_daily_write_cap',
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_daily_write_alert_email',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_email',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_daily_write_alert_threshold',
				'type'     => 'integer',
				'default'  => 80,
				'sanitize' => function ( $v ) {
					$v = absint( $v );
					return $v >= 1 && $v <= 100 ? $v : 80;
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_agent_id',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_openclaw_user_id',
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_openclaw_capability_groups',
				'type'     => 'array',
				'default'  => OpenClaw_Role::DEFAULT_GROUPS,
				'sanitize' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return OpenClaw_Role::DEFAULT_GROUPS;
					}
					$allowed  = OpenClaw_Role::get_capability_group_slugs();
					$filtered = array_values( array_intersect( $value, $allowed ) );
					return empty( $filtered ) ? OpenClaw_Role::DEFAULT_GROUPS : $filtered;
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_webhook_deliver',
				'type'     => 'boolean',
				'default'  => true,
				'sanitize' => 'rest_sanitize_boolean',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_webhook_channel',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_key',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_webhook_to',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_webhook_model',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_webhook_thinking',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => function ( $value ) use ( $thinking_allowed ) {
					return in_array( $value, $thinking_allowed, true ) ? $value : '';
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_webhook_timeout',
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_chat_model',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_chat_thinking',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => function ( $value ) use ( $thinking_allowed ) {
					return in_array( $value, $thinking_allowed, true ) ? $value : '';
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_chat_timeout',
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_chat_placeholder',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_session_idle_minutes',
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_public_chat_rate_limit',
				'type'     => 'integer',
				'default'  => 3,
				'sanitize' => function ( $v ) {
					return max( 1, min( 60, absint( $v ) ) );
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_sse_max_connections_per_ip',
				'type'     => 'integer',
				'default'  => 5,
				'sanitize' => function ( $v ) {
					$v = absint( $v );
					return $v <= 0 ? 0 : max( 1, min( 20, $v ) );
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_chat_max_response_length',
				'type'     => 'integer',
				'default'  => 200000,
				'sanitize' => function ( $v ) {
					$v = absint( $v );
					return $v < 0 ? 0 : min( 2000000, $v );
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_ability_cache_ttl',
				'type'     => 'integer',
				'default'  => 300,
				'sanitize' => function ( $v ) {
					$v = absint( $v );
					return $v < 0 ? 0 : min( 86400, $v );
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_pinchdrop_enabled',
				'type'     => 'boolean',
				'default'  => false,
				'sanitize' => 'rest_sanitize_boolean',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_pinchdrop_default_outputs',
				'type'     => 'array',
				'default'  => $pinchdrop_outputs,
				'sanitize' => function ( $value ) use ( $pinchdrop_outputs ) {
					if ( ! is_array( $value ) ) {
						return $pinchdrop_outputs;
					}
					$value = array_values( array_intersect( $pinchdrop_outputs, array_map( 'sanitize_key', $value ) ) );
					return empty( $value ) ? $pinchdrop_outputs : $value;
				},
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_pinchdrop_auto_save_drafts',
				'type'     => 'boolean',
				'default'  => true,
				'sanitize' => 'rest_sanitize_boolean',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_pinchdrop_allowed_sources',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => function ( $value ) {
					$parts = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) $value ) ) ) );
					return implode( ',', $parts );
				},
			),
			// Webhooks tab.
			array(
				'group'    => 'wp_pinch_webhooks',
				'option'   => 'wp_pinch_webhook_events',
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => function ( $value ) {
					return is_array( $value ) ? array_map( 'sanitize_key', $value ) : array();
				},
			),
			array(
				'group'    => 'wp_pinch_webhooks',
				'option'   => 'wp_pinch_webhook_wake_modes',
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					$allowed = array( 'now', 'next-heartbeat' );
					return array_map(
						function ( $v ) use ( $allowed ) {
							return in_array( $v, $allowed, true ) ? $v : 'now';
						},
						$value
					);
				},
			),
			array(
				'group'    => 'wp_pinch_webhooks',
				'option'   => 'wp_pinch_webhook_endpoint_types',
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					$allowed = array( 'agent', 'wake' );
					return array_map(
						function ( $v ) use ( $allowed ) {
							return in_array( $v, $allowed, true ) ? $v : 'agent';
						},
						$value
					);
				},
			),
			// Governance tab.
			array(
				'group'    => 'wp_pinch_governance',
				'option'   => 'wp_pinch_governance_tasks',
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => function ( $value ) {
					return is_array( $value ) ? array_map( 'sanitize_key', $value ) : array();
				},
			),
			array(
				'group'    => 'wp_pinch_governance',
				'option'   => 'wp_pinch_governance_mode',
				'type'     => 'string',
				'default'  => 'webhook',
				'sanitize' => 'sanitize_key',
			),
			array(
				'group'    => 'wp_pinch_governance',
				'option'   => 'wp_pinch_ghost_writer_threshold',
				'type'     => 'integer',
				'default'  => 30,
				'sanitize' => 'absint',
			),
			// Abilities tab.
			array(
				'group'    => 'wp_pinch_abilities',
				'option'   => 'wp_pinch_disabled_abilities',
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => function ( $value ) {
					return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
				},
			),
			// Feature flags.
			array(
				'group'    => 'wp_pinch_features',
				'option'   => 'wp_pinch_feature_flags',
				'type'     => 'object',
				'default'  => Feature_Flags::DEFAULTS,
				'sanitize' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						$value = array();
					}
					$sanitized = array();
					foreach ( Feature_Flags::DEFAULTS as $flag => $default ) {
						$sanitized[ $flag ] = isset( $value[ $flag ] ) && '1' === $value[ $flag ];
					}
					return $sanitized;
				},
			),
			// Wizard + Web Clipper.
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_wizard_completed',
				'type'     => 'boolean',
				'default'  => false,
				'sanitize' => 'rest_sanitize_boolean',
			),
			array(
				'group'    => 'wp_pinch_connection',
				'option'   => 'wp_pinch_capture_token',
				'type'     => 'string',
				'default'  => '',
				'sanitize' => function ( $value ) {
					$value = sanitize_text_field( (string) $value );
					if ( '' === $value || str_repeat( "\u{2022}", 8 ) === $value ) {
						return get_option( 'wp_pinch_capture_token', '' );
					}
					return $value;
				},
			),
		);
	}
}
