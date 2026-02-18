<?php
/**
 * Site operations, diagnostics, and governance-style abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

/**
 * Health, performance, and governance support abilities.
 */
class Site_Ops_Abilities {

	/**
	 * Register site ops abilities.
	 */
	public static function register(): void {
		Abilities::register_ability(
			'wp-pinch/flush-cache',
			__( 'Flush Cache', 'wp-pinch' ),
			__( 'Flush the active WordPress object cache.', 'wp-pinch' ),
			array(
				'type' => 'object',
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_flush_cache' )
		);

		Abilities::register_ability(
			'wp-pinch/check-broken-links',
			__( 'Check Broken Links', 'wp-pinch' ),
			__( 'Scan URLs in post content and report links with HTTP errors.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_id'     => array(
						'type'        => 'integer',
						'description' => 'Optional: scan only this post.',
					),
					'post_types'  => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Post types to scan when post_id is not provided.',
					),
					'posts_limit' => array(
						'type'        => 'integer',
						'default'     => 10,
						'description' => 'Maximum number of posts to scan when post_id is omitted.',
					),
					'max_links'   => array(
						'type'        => 'integer',
						'default'     => 50,
						'description' => 'Maximum links to check across selected posts.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_check_broken_links' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/get-php-error-log',
			__( 'Get PHP Error Log', 'wp-pinch' ),
			__( 'Return a bounded tail of the PHP debug log.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'lines'              => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'max_chars_per_line' => array(
						'type'    => 'integer',
						'default' => 240,
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_get_php_error_log' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/list-posts-missing-meta',
			__( 'List Posts Missing Meta', 'wp-pinch' ),
			__( 'Find posts missing excerpts, featured images, or with long titles.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_type'            => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'status'               => array(
						'type'    => 'string',
						'default' => 'publish',
					),
					'per_page'             => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'                 => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'check_excerpt'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'check_featured_image' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'title_max_length'     => array(
						'type'        => 'integer',
						'default'     => 80,
						'description' => 'Report posts whose title length is greater than this value.',
					),
					'check_title_too_long' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_list_posts_missing_meta' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/list-custom-post-types',
			__( 'List Custom Post Types', 'wp-pinch' ),
			__( 'List available post types and capabilities for content discovery.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'public_only'     => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'include_builtin' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_list_custom_post_types' ),
			true
		);
	}

	/**
	 * Flush object cache.
	 *
	 * @param array<string, mixed> $input Ability input (unused).
	 * @return array<string, mixed>
	 */
	public static function execute_flush_cache( array $input ): array {
		unset( $input );

		$flushed = wp_cache_flush();

		Audit_Table::insert(
			'cache_flushed',
			'ability',
			$flushed ? 'Object cache flushed.' : 'Object cache flush attempted and failed.',
			array( 'success' => (bool) $flushed )
		);

		return array(
			'flushed' => (bool) $flushed,
		);
	}

	/**
	 * Check links in selected posts for HTTP failures.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_check_broken_links( array $input ): array {
		$post_id     = absint( $input['post_id'] ?? 0 );
		$posts_limit = max( 1, min( absint( $input['posts_limit'] ?? 10 ), 50 ) );
		$max_links   = max( 1, min( absint( $input['max_links'] ?? 50 ), 200 ) );
		$post_types  = self::sanitize_post_types( $input['post_types'] ?? array() );

		$posts = array();
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return array( 'error' => __( 'You do not have permission to scan this post.', 'wp-pinch' ) );
			}
			$posts[] = $post;
		} else {
			$posts = get_posts(
				array(
					'post_type'      => $post_types,
					'post_status'    => 'publish',
					'posts_per_page' => $posts_limit,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);
		}

		$checked = 0;
		$broken  = array();

		foreach ( $posts as $post ) {
			if ( $checked >= $max_links ) {
				break;
			}

			$urls = self::extract_urls_from_content( (string) $post->post_content );
			if ( empty( $urls ) ) {
				continue;
			}

			foreach ( $urls as $url ) {
				if ( $checked >= $max_links ) {
					break;
				}
				$normalized = self::normalize_http_url( $url );
				if ( '' === $normalized || ! self::is_url_public( $normalized ) ) {
					continue;
				}

				$response = wp_remote_head(
					$normalized,
					array(
						'timeout'     => 5,
						'redirection' => 3,
						'sslverify'   => true,
					)
				);
				++$checked;

				$status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
				if ( 0 === $status || $status >= 400 ) {
					$broken[] = array(
						'post_id'    => (int) $post->ID,
						'post_title' => $post->post_title,
						'url'        => $normalized,
						'status'     => $status,
						'error'      => is_wp_error( $response ) ? $response->get_error_message() : sprintf( 'HTTP %d', $status ),
					);
				}
			}
		}

		return array(
			'posts_scanned' => count( $posts ),
			'links_checked' => $checked,
			'broken_count'  => count( $broken ),
			'broken_links'  => $broken,
		);
	}

	/**
	 * Return a bounded tail from debug log.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_get_php_error_log( array $input ): array {
		$line_limit = max( 1, min( absint( $input['lines'] ?? 20 ), 100 ) );
		$max_chars  = max( 40, min( absint( $input['max_chars_per_line'] ?? 240 ), 1000 ) );
		$debug_log  = self::resolve_debug_log_path();

		if ( '' === $debug_log ) {
			return array( 'error' => __( 'WP_DEBUG_LOG is not enabled.', 'wp-pinch' ) );
		}
		if ( ! is_file( $debug_log ) || ! is_readable( $debug_log ) ) {
			return array( 'error' => __( 'Debug log is not readable.', 'wp-pinch' ) );
		}

		$lines = self::read_log_tail( $debug_log, $line_limit );
		$lines = array_map(
			static function ( string $line ) use ( $max_chars ): string {
				$line = trim( $line );
				if ( mb_strlen( $line ) > $max_chars ) {
					return mb_substr( $line, 0, $max_chars ) . '…';
				}
				return $line;
			},
			$lines
		);

		return array(
			'log_path' => $debug_log,
			'lines'    => $lines,
			'total'    => count( $lines ),
		);
	}

	/**
	 * List posts missing excerpt/image or with long titles.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_posts_missing_meta( array $input ): array {
		$post_type        = sanitize_key( (string) ( $input['post_type'] ?? 'post' ) );
		$post_status      = sanitize_key( (string) ( $input['status'] ?? 'publish' ) );
		$per_page         = max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) );
		$page             = max( 1, absint( $input['page'] ?? 1 ) );
		$check_excerpt    = ! array_key_exists( 'check_excerpt', $input ) || ! empty( $input['check_excerpt'] );
		$check_featured   = ! array_key_exists( 'check_featured_image', $input ) || ! empty( $input['check_featured_image'] );
		$check_title_long = ! array_key_exists( 'check_title_too_long', $input ) || ! empty( $input['check_title_too_long'] );
		$title_max_length = max( 1, min( absint( $input['title_max_length'] ?? 80 ), 500 ) );

		if ( ! post_type_exists( $post_type ) ) {
			return array( 'error' => __( 'Invalid post type.', 'wp-pinch' ) );
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => $post_status,
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$reasons = array();
			if ( $check_excerpt && '' === trim( (string) $post->post_excerpt ) ) {
				$reasons[] = 'missing_excerpt';
			}
			if ( $check_featured && (int) get_post_thumbnail_id( (int) $post->ID ) < 1 ) {
				$reasons[] = 'missing_featured_image';
			}
			if ( $check_title_long && mb_strlen( (string) $post->post_title ) > $title_max_length ) {
				$reasons[] = 'title_too_long';
			}
			if ( empty( $reasons ) ) {
				continue;
			}

			$items[] = array(
				'id'            => (int) $post->ID,
				'title'         => $post->post_title,
				'title_length'  => mb_strlen( (string) $post->post_title ),
				'excerpt_empty' => '' === trim( (string) $post->post_excerpt ),
				'has_thumbnail' => (int) get_post_thumbnail_id( (int) $post->ID ) > 0,
				'url'           => get_permalink( (int) $post->ID ),
				'reasons'       => $reasons,
			);
		}

		return array(
			'post_type'     => $post_type,
			'post_status'   => $post_status,
			'page'          => $page,
			'per_page'      => $per_page,
			'scanned_count' => count( $query->posts ),
			'matched_count' => count( $items ),
			'items'         => $items,
		);
	}

	/**
	 * List custom post types for capability discovery.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_custom_post_types( array $input ): array {
		$public_only     = ! empty( $input['public_only'] );
		$include_builtin = ! empty( $input['include_builtin'] );

		$args = array();
		if ( $public_only ) {
			$args['public'] = true;
		}

		$post_types = get_post_types( $args, 'objects' );
		$list       = array();

		foreach ( $post_types as $name => $object ) {
			if ( ! $include_builtin && ! empty( $object->_builtin ) ) {
				continue;
			}

			$list[] = array(
				'name'         => (string) $name,
				'label'        => is_object( $object->labels ) && isset( $object->labels->name ) ? (string) $object->labels->name : (string) $name,
				'public'       => (bool) $object->public,
				'hierarchical' => (bool) $object->hierarchical,
				'show_ui'      => (bool) $object->show_ui,
				'supports'     => array_keys( get_all_post_type_supports( (string) $name ) ),
				'capabilities' => array(
					'edit_posts'    => isset( $object->cap->edit_posts ) ? (string) $object->cap->edit_posts : '',
					'publish_posts' => isset( $object->cap->publish_posts ) ? (string) $object->cap->publish_posts : '',
					'delete_posts'  => isset( $object->cap->delete_posts ) ? (string) $object->cap->delete_posts : '',
				),
			);
		}

		usort(
			$list,
			static function ( array $a, array $b ): int {
				return strcmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		return array(
			'post_types' => $list,
			'total'      => count( $list ),
		);
	}

	/**
	 * Extract href URLs from HTML content.
	 *
	 * @param string $content HTML content.
	 * @return string[]
	 */
	private static function extract_urls_from_content( string $content ): array {
		preg_match_all( '/href=["\']([^"\']+)["\']/i', $content, $matches );
		if ( empty( $matches[1] ) ) {
			return array();
		}
		return array_values( array_unique( array_map( 'trim', $matches[1] ) ) );
	}

	/**
	 * Normalize to an HTTP/HTTPS URL.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private static function normalize_http_url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		if ( preg_match( '/^(#|mailto:|tel:|javascript:|ftp:|data:|gopher:|dict:)/i', $url ) ) {
			return '';
		}

		if ( ! preg_match( '/^https?:\/\//i', $url ) ) {
			$url = home_url( $url );
		}

		$url    = esc_url_raw( $url );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Block local/private hosts before remote checks.
	 *
	 * @param string $url URL to validate.
	 * @return bool
	 */
	private static function is_url_public( string $url ): bool {
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$ip = gethostbyname( $host );
		if ( $ip === $host ) {
			// DNS failed to resolve, let request layer report this.
			return true;
		}

		return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Resolve debug log path from WP_DEBUG_LOG.
	 *
	 * @return string
	 */
	private static function resolve_debug_log_path(): string {
		if ( ! defined( 'WP_DEBUG_LOG' ) ) {
			return '';
		}
		$debug_log = constant( 'WP_DEBUG_LOG' );
		if ( ! $debug_log ) {
			return '';
		}
		if ( true === $debug_log && defined( 'WP_CONTENT_DIR' ) ) {
			return WP_CONTENT_DIR . '/debug.log';
		}
		if ( is_string( $debug_log ) ) {
			return $debug_log;
		}
		return '';
	}

	/**
	 * Read a tail of a text log file.
	 *
	 * @param string $path       Log path.
	 * @param int    $line_limit Maximum lines to return.
	 * @return string[]
	 */
	private static function read_log_tail( string $path, int $line_limit ): array {
		$lines = array();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads admin debug log only.
		$handle = @fopen( $path, 'r' );
		if ( ! $handle ) {
			return $lines;
		}

		$size  = fstat( $handle )['size'] ?? 0;
		$chunk = min( 32768, max( 2048, (int) $size ) );
		if ( $size > $chunk ) {
			fseek( $handle, -$chunk, SEEK_END );
			fgets( $handle );
		}

		$line = fgets( $handle );
		while ( false !== $line ) {
			$lines[] = (string) $line;
			$line    = fgets( $handle );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Paired with fopen.
		fclose( $handle );

		return array_slice( $lines, -$line_limit );
	}

	/**
	 * Sanitize selected post types for link checks.
	 *
	 * @param mixed $raw Raw post_types input.
	 * @return string[]
	 */
	private static function sanitize_post_types( $raw ): array {
		$default = array( 'post', 'page' );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return $default;
		}

		$out = array();
		foreach ( $raw as $post_type ) {
			$key = sanitize_key( (string) $post_type );
			if ( '' === $key || ! post_type_exists( $key ) ) {
				continue;
			}
			$out[] = $key;
		}

		$out = array_values( array_unique( $out ) );
		if ( empty( $out ) ) {
			return $default;
		}

		return array_slice( $out, 0, 20 );
	}
}
