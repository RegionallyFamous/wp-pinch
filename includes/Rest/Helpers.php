<?php
/**
 * REST helpers — rate limiting, diagnostics, audit sanitization, chat reply sanitization.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

use WP_Pinch\Feature_Flags;
use WP_Pinch\Prompt_Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Shared REST helper functions.
 */
class Helpers {

	/**
	 * Default rate limit: requests per minute per user.
	 *
	 * @var int
	 */
	const DEFAULT_RATE_LIMIT = 10;

	/**
	 * Maximum keys per level in params (webhook/pinchdrop) to limit DoS.
	 *
	 * @var int
	 */
	const MAX_PARAMS_KEYS_PER_LEVEL = 100;

	/**
	 * Per-user rate limiting. Uses object cache or transients.
	 *
	 * @return bool True if within limit, false if rate limited.
	 */
	public static function check_rate_limit(): bool {
		$user_id = get_current_user_id();
		$key     = 'wp_pinch_rest_rate_' . $user_id;
		$limit   = max( 1, (int) get_option( 'wp_pinch_rate_limit', self::DEFAULT_RATE_LIMIT ) );

		if ( wp_using_ext_object_cache() ) {
			$count = (int) wp_cache_get( $key, 'wp-pinch' );
			if ( $count >= $limit ) {
				return false;
			}
			if ( 0 === $count ) {
				wp_cache_set( $key, 1, 'wp-pinch', 60 );
			} else {
				wp_cache_incr( $key, 1, 'wp-pinch' );
			}
			return true;
		}

		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		if ( 0 === $count ) {
			set_transient( $key, 1, 60 );
		} else {
			$option_key = '_transient_' . $key;
			update_option( $option_key, $count + 1, false );
		}
		return true;
	}

	/**
	 * Per-IP rate limit (transient-based).
	 *
	 * @param string $prefix        Transient key prefix (e.g. 'wp_pinch_health_rate_').
	 * @param int    $limit_per_minute Max requests per minute per IP.
	 * @return bool True if within limit, false if rate limited.
	 */
	public static function check_ip_rate_limit( string $prefix, int $limit_per_minute ): bool {
		$ip   = self::get_client_ip();
		$salt = wp_salt();
		$key  = $prefix . substr( hash_hmac( 'sha256', $ip, $salt ), 0, 16 );

		$count = (int) get_transient( $key );
		if ( $count >= $limit_per_minute ) {
			return false;
		}
		if ( 0 === $count ) {
			set_transient( $key, 1, 60 );
		} else {
			set_transient( $key, $count + 1, 60 );
		}
		return true;
	}

	/**
	 * Get the client IP address, respecting common proxy headers.
	 *
	 * @return string Client IP address.
	 */
	public static function get_client_ip(): string {
		$headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				if ( str_contains( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}

	/**
	 * WordPress-aware diagnostics (manage_options only).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_diagnostics(): array {
		$out = array( 'php_version' => PHP_VERSION );

		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( function_exists( 'wp_get_plugin_data' ) ) {
			wp_clean_plugins_cache();
		}
		$plugin_updates              = function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : array();
		$theme_updates               = function_exists( 'wp_get_themes' ) ? get_theme_updates() : array();
		$out['plugin_updates_count'] = is_array( $plugin_updates ) ? count( $plugin_updates ) : 0;
		$out['theme_updates_count']  = is_array( $theme_updates ) ? count( $theme_updates ) : 0;

		global $wpdb;
		if ( ! empty( $wpdb->dbname ) ) {
			$cache_key = 'db_size_' . $wpdb->dbname;
			$size      = wp_cache_get( $cache_key, 'wp_pinch_diagnostics' );
			if ( false === $size ) {
				$size = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s',
						$wpdb->dbname
					)
				);
				wp_cache_set( $cache_key, $size, 'wp_pinch_diagnostics', 300 );
			}
			$out['db_size_bytes'] = $size ? (int) $size : null;
		}

		$abspath = defined( 'ABSPATH' ) ? ABSPATH : '';
		if ( '' !== $abspath && function_exists( 'disk_free_space' ) && function_exists( 'disk_total_space' ) ) {
			$free  = @disk_free_space( $abspath );
			$total = @disk_total_space( $abspath );
			if ( false !== $free && false !== $total && $total > 0 ) {
				$out['disk_free_bytes']  = $free;
				$out['disk_total_bytes'] = $total;
			}
		}

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$out['cron'] = array(
				'status'  => 'disabled',
				'message' => 'WP-Cron is disabled (DISABLE_WP_CRON).',
			);
		} else {
			$crons = _get_cron_array();
			$next  = null;
			if ( is_array( $crons ) ) {
				foreach ( $crons as $timestamp => $hooks ) {
					if ( $timestamp > time() ) {
						$next = $timestamp;
						break;
					}
				}
			}
			$out['cron'] = array(
				'status'       => 'active',
				'next_run_gmt' => $next ? gmdate( 'c', $next ) : null,
			);
		}

		$out['error_log_tail'] = array();
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && defined( 'WP_CONTENT_DIR' ) ) {
			$log_file = WP_CONTENT_DIR . '/debug.log';
			if ( is_readable( $log_file ) && is_file( $log_file ) ) {
				$lines  = array();
				$handle = @fopen( $log_file, 'r' );
				if ( $handle ) {
					$size  = fstat( $handle )['size'] ?? 0;
					$chunk = min( 8192, max( 512, (int) $size ) );
					if ( $size > $chunk ) {
						fseek( $handle, -$chunk, SEEK_END );
						fgets( $handle );
					}
					$line = fgets( $handle );
					while ( false !== $line ) {
						$lines[] = $line;
						$line    = fgets( $handle );
					}
					fclose( $handle );
					$lines                 = array_slice( $lines, -20 );
					$max_len               = 200;
					$out['error_log_tail'] = array_map(
						function ( $l ) use ( $max_len ) {
							$l = trim( $l );
							return mb_strlen( $l ) > $max_len ? mb_substr( $l, 0, $max_len ) . '…' : $l;
						},
						$lines
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Build the site capability manifest for agent discovery.
	 *
	 * @return array{ post_types: string[], taxonomies: string[], plugins: string[], features: array<string, bool> }
	 */
	public static function get_site_manifest(): array {
		$post_types = array_keys( get_post_types( array( 'public' => true ), 'names' ) );
		$taxonomies = array_keys( get_taxonomies( array( 'public' => true ), 'names' ) );
		$active     = get_option( 'active_plugins', array() );
		$plugins    = array();
		foreach ( $active as $path ) {
			$slug = dirname( $path );
			if ( '.' === $slug ) {
				$slug = pathinfo( $path, PATHINFO_FILENAME );
			}
			$plugins[] = $slug;
		}
		$plugins  = array_values( array_unique( $plugins ) );
		$features = Feature_Flags::get_all();
		$manifest = array(
			'post_types' => $post_types,
			'taxonomies' => $taxonomies,
			'plugins'    => $plugins,
			'features'   => $features,
		);
		return apply_filters( 'wp_pinch_manifest', $manifest );
	}

	/**
	 * Build a sanitized summary of params for audit context.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @return array<string, mixed>
	 */
	public static function sanitize_audit_params( array $params ): array {
		$out = array();
		$max = 5;
		foreach ( $params as $k => $v ) {
			if ( $max-- <= 0 ) {
				break;
			}
			$key = sanitize_key( (string) $k );
			if ( '' === $key ) {
				continue;
			}
			if ( is_scalar( $v ) ) {
				$s           = (string) $v;
				$out[ $key ] = mb_strlen( $s ) > 80 ? mb_substr( $s, 0, 80 ) . '…' : $s;
			} elseif ( is_array( $v ) ) {
				$out[ $key ] = array_keys( $v );
			} else {
				$out[ $key ] = gettype( $v );
			}
		}
		return $out;
	}

	/**
	 * Build a sanitized summary of ability result for audit context.
	 *
	 * @param mixed $result Ability return value.
	 * @return array<string, mixed>
	 */
	public static function sanitize_audit_result( $result ): array {
		if ( ! is_array( $result ) ) {
			return array( 'type' => gettype( $result ) );
		}
		$out = array();
		if ( isset( $result['post_id'] ) ) {
			$out['post_id'] = (int) $result['post_id'];
		}
		if ( isset( $result['id'] ) && ! isset( $out['post_id'] ) ) {
			$out['id'] = (int) $result['id'];
		}
		if ( count( $out ) < 3 && ! empty( $result ) ) {
			$out['keys'] = array_slice( array_keys( $result ), 0, 10 );
		}
		return $out;
	}

	/**
	 * Recursively sanitize ability params from incoming hooks.
	 *
	 * @param array<string, mixed> $params Params to sanitize.
	 * @param int                  $depth  Current recursion depth (max 5).
	 * @return array<string, mixed>
	 */
	public static function sanitize_params_recursive( array $params, int $depth = 0 ): array {
		if ( $depth > 5 ) {
			return array();
		}
		$sanitized = array();
		$count     = 0;
		foreach ( $params as $key => $value ) {
			if ( $count >= self::MAX_PARAMS_KEYS_PER_LEVEL ) {
				break;
			}
			$key = sanitize_key( $key );
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_int( $value ) || is_float( $value ) || is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_params_recursive( $value, $depth + 1 );
			}
			++$count;
		}
		return $sanitized;
	}

	/**
	 * Cap chat reply length to the configured maximum.
	 *
	 * @param string $reply Raw reply from gateway.
	 * @return string
	 */
	public static function cap_chat_reply( string $reply ): string {
		$max = (int) get_option( 'wp_pinch_chat_max_response_length', 200000 );
		if ( $max <= 0 || strlen( $reply ) <= $max ) {
			return $reply;
		}
		return substr( $reply, 0, $max ) . "\n\n[" . __( 'Response truncated for length.', 'wp-pinch' ) . ']';
	}

	/**
	 * Sanitize gateway reply for safe output.
	 *
	 * @param string $reply Raw reply from gateway.
	 * @return string
	 */
	public static function sanitize_gateway_reply( string $reply ): string {
		if ( '' === trim( $reply ) ) {
			return $reply;
		}
		if ( ! (bool) get_option( 'wp_pinch_gateway_reply_strict_sanitize', false ) ) {
			return wp_kses_post( $reply );
		}
		$reply   = preg_replace( '/<!--.*?-->/s', '', $reply );
		$reply   = Prompt_Sanitizer::sanitize( $reply );
		$allowed = wp_kses_allowed_html( 'post' );
		unset( $allowed['iframe'], $allowed['object'], $allowed['embed'], $allowed['form'] );
		return wp_kses( $reply, $allowed );
	}

	/**
	 * Process SSE stream buffer: sanitize gateway "reply" in data lines.
	 *
	 * @param string $buffer Accumulated SSE stream text.
	 * @return array{0: string, 1: string} [ output to echo, remaining buffer ].
	 */
	public static function process_sse_buffer( string $buffer ): array {
		$buffer = str_replace( "\r\n", "\n", $buffer );
		$parts  = explode( "\n", $buffer );
		$carry  = array_pop( $parts );
		$output = array();
		foreach ( $parts as $line ) {
			if ( str_starts_with( $line, 'data:' ) ) {
				$payload = trim( substr( $line, 5 ) );
				if ( '' !== $payload && '{}' !== $payload ) {
					$decoded = json_decode( $payload, true );
					if ( is_array( $decoded ) && array_key_exists( 'reply', $decoded ) && is_string( $decoded['reply'] ) ) {
						$decoded['reply'] = self::sanitize_gateway_reply( $decoded['reply'] );
						$payload          = wp_json_encode( $decoded );
					}
				}
				$output[] = 'data: ' . $payload;
			} else {
				$output[] = $line;
			}
		}
		$out = implode( "\n", $output );
		return array( '' !== $out ? $out . "\n" : '', $carry ?? '' );
	}
}
