<?php
/**
 * System admin abilities (transients, rewrite rules, maintenance mode, scoped DB replace, languages).
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

/**
 * System and platform operations with strict guardrails.
 */
class System_Admin_Abilities {

	/**
	 * Register system admin abilities.
	 */
	public static function register(): void {
		Abilities::register_ability(
			'wp-pinch/get-transient',
			__( 'Get Transient', 'wp-pinch' ),
			__( 'Read a transient value by key.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'key' ),
				'properties' => array(
					'key' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_get_transient' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/set-transient',
			__( 'Set Transient', 'wp-pinch' ),
			__( 'Set a transient value with optional expiration.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'key', 'value' ),
				'properties' => array(
					'key'        => array( 'type' => 'string' ),
					'value'      => array(),
					'expiration' => array(
						'type'        => 'integer',
						'default'     => 0,
						'description' => 'Expiration in seconds. 0 means no expiration.',
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_set_transient' )
		);

		Abilities::register_ability(
			'wp-pinch/delete-transient',
			__( 'Delete Transient', 'wp-pinch' ),
			__( 'Delete a transient by key.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'key' ),
				'properties' => array(
					'key' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_delete_transient' )
		);

		Abilities::register_ability(
			'wp-pinch/list-rewrite-rules',
			__( 'List Rewrite Rules', 'wp-pinch' ),
			__( 'List currently registered rewrite rules.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 200,
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_list_rewrite_rules' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/flush-rewrite-rules',
			__( 'Flush Rewrite Rules', 'wp-pinch' ),
			__( 'Flush permalink rewrite rules.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'hard' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Hard flush writes to .htaccess where applicable.',
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_flush_rewrite_rules' )
		);

		Abilities::register_ability(
			'wp-pinch/maintenance-mode-status',
			__( 'Maintenance Mode Status', 'wp-pinch' ),
			__( 'Check whether WordPress maintenance mode is active.', 'wp-pinch' ),
			array( 'type' => 'object' ),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_maintenance_mode_status' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/set-maintenance-mode',
			__( 'Set Maintenance Mode', 'wp-pinch' ),
			__( 'Enable or disable maintenance mode by writing the core maintenance marker file.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'enabled' ),
				'properties' => array(
					'enabled' => array( 'type' => 'boolean' ),
					'confirm' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Must be true when enabling maintenance mode.',
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_set_maintenance_mode' )
		);

		Abilities::register_ability(
			'wp-pinch/search-replace-db-scoped',
			__( 'Search Replace DB Scoped', 'wp-pinch' ),
			__( 'Run a guarded search/replace over allowed database scopes with dry-run enabled by default.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'search', 'replace', 'scope' ),
				'properties' => array(
					'search'  => array( 'type' => 'string' ),
					'replace' => array( 'type' => 'string' ),
					'scope'   => array(
						'type' => 'string',
						'enum' => array( 'posts_content', 'postmeta_value', 'comments_content' ),
					),
					'limit'   => array(
						'type'    => 'integer',
						'default' => 200,
					),
					'dry_run' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'confirm' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Must be true when dry_run is false.',
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_search_replace_db_scoped' )
		);

		Abilities::register_ability(
			'wp-pinch/list-language-packs',
			__( 'List Language Packs', 'wp-pinch' ),
			__( 'List installed language packs and core translation metadata.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 100,
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_list_language_packs' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/install-language-pack',
			__( 'Install Language Pack', 'wp-pinch' ),
			__( 'Install a core language pack by locale.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'locale' ),
				'properties' => array(
					'locale' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_install_language_pack' )
		);

		Abilities::register_ability(
			'wp-pinch/activate-language-pack',
			__( 'Activate Language Pack', 'wp-pinch' ),
			__( 'Activate a locale as the site language.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'locale' ),
				'properties' => array(
					'locale' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_activate_language_pack' )
		);
	}

	/**
	 * Get transient value.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_get_transient( array $input ): array {
		$key = self::sanitize_transient_key( (string) ( $input['key'] ?? '' ) );
		if ( '' === $key ) {
			return array( 'error' => __( 'Invalid transient key.', 'wp-pinch' ) );
		}

		$value = get_transient( $key );
		if ( false === $value ) {
			return array(
				'key'   => $key,
				'found' => false,
				'value' => null,
			);
		}

		return array(
			'key'   => $key,
			'found' => true,
			'value' => $value,
		);
	}

	/**
	 * Set transient value.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_set_transient( array $input ): array {
		$key        = self::sanitize_transient_key( (string) ( $input['key'] ?? '' ) );
		$expiration = max( 0, absint( $input['expiration'] ?? 0 ) );

		if ( '' === $key ) {
			return array( 'error' => __( 'Invalid transient key.', 'wp-pinch' ) );
		}

		$ok = set_transient( $key, $input['value'], $expiration );

		Audit_Table::insert(
			'transient_set',
			'ability',
			sprintf( 'Transient "%s" set via ability.', $key ),
			array(
				'key'        => $key,
				'expiration' => $expiration,
			)
		);

		return array(
			'key'        => $key,
			'set'        => (bool) $ok,
			'expiration' => $expiration,
		);
	}

	/**
	 * Delete transient value.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_delete_transient( array $input ): array {
		$key = self::sanitize_transient_key( (string) ( $input['key'] ?? '' ) );
		if ( '' === $key ) {
			return array( 'error' => __( 'Invalid transient key.', 'wp-pinch' ) );
		}

		$deleted = delete_transient( $key );

		Audit_Table::insert(
			'transient_deleted',
			'ability',
			sprintf( 'Transient "%s" deleted via ability.', $key ),
			array( 'key' => $key )
		);

		return array(
			'key'     => $key,
			'deleted' => (bool) $deleted,
		);
	}

	/**
	 * List rewrite rules.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_rewrite_rules( array $input ): array {
		$limit = max( 1, min( absint( $input['limit'] ?? 200 ), 1000 ) );
		$rules = get_option( 'rewrite_rules', array() );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		$list  = array();
		$count = 0;
		foreach ( $rules as $pattern => $query ) {
			if ( $count >= $limit ) {
				break;
			}
			$list[] = array(
				'pattern' => (string) $pattern,
				'query'   => (string) $query,
			);
			++$count;
		}

		return array(
			'rules'       => $list,
			'total_rules' => count( $rules ),
			'returned'    => count( $list ),
			'truncated'   => count( $rules ) > count( $list ),
		);
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_flush_rewrite_rules( array $input ): array {
		$hard = ! empty( $input['hard'] );
		flush_rewrite_rules( $hard );

		Audit_Table::insert(
			'rewrite_rules_flushed',
			'ability',
			$hard ? 'Rewrite rules hard flushed.' : 'Rewrite rules soft flushed.',
			array( 'hard' => $hard )
		);

		return array(
			'flushed' => true,
			'hard'    => $hard,
		);
	}

	/**
	 * Get maintenance mode status.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_maintenance_mode_status( array $input ): array {
		unset( $input );

		$path    = ABSPATH . '.maintenance';
		$enabled = is_file( $path );
		$mtime   = $enabled ? filemtime( $path ) : false;

		return array(
			'enabled'       => $enabled,
			'marker_exists' => $enabled,
			'updated_at'    => ( false !== $mtime ) ? gmdate( 'c', (int) $mtime ) : null,
		);
	}

	/**
	 * Enable or disable maintenance mode.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_set_maintenance_mode( array $input ): array {
		$enabled = ! empty( $input['enabled'] );
		$confirm = ! empty( $input['confirm'] );
		$path    = ABSPATH . '.maintenance';
		if ( $enabled && ! $confirm ) {
			return array( 'error' => __( 'confirm=true is required to enable maintenance mode.', 'wp-pinch' ) );
		}

		if ( $enabled ) {
			$payload = '<?php $upgrading = ' . time() . ';';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Strictly writes ABSPATH .maintenance marker.
			$written = @file_put_contents( $path, $payload );
			if ( false === $written ) {
				return array( 'error' => __( 'Failed to enable maintenance mode.', 'wp-pinch' ) );
			}
		} elseif ( is_file( $path ) ) {
			$deleted = wp_delete_file( $path );
			if ( ! $deleted ) {
				return array( 'error' => __( 'Failed to disable maintenance mode.', 'wp-pinch' ) );
			}
		}

		Audit_Table::insert(
			'maintenance_mode_toggled',
			'ability',
			$enabled ? 'Maintenance mode enabled.' : 'Maintenance mode disabled.',
			array( 'enabled' => $enabled )
		);

		return array(
			'enabled' => $enabled,
		);
	}

	/**
	 * Scoped database search/replace with dry-run mode.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_search_replace_db_scoped( array $input ): array {
		global $wpdb;

		$search  = (string) ( $input['search'] ?? '' );
		$replace = (string) ( $input['replace'] ?? '' );
		$scope   = sanitize_key( (string) ( $input['scope'] ?? '' ) );
		$dry_run = ! array_key_exists( 'dry_run', $input ) || ! empty( $input['dry_run'] );
		$confirm = ! empty( $input['confirm'] );
		$limit   = max( 1, min( absint( $input['limit'] ?? 200 ), 500 ) );

		if ( '' === $search ) {
			return array( 'error' => __( 'Search string cannot be empty.', 'wp-pinch' ) );
		}
		if ( mb_strlen( $search ) < 2 ) {
			return array( 'error' => __( 'Search string must be at least 2 characters.', 'wp-pinch' ) );
		}
		if ( ! $dry_run && ! $confirm ) {
			return array( 'error' => __( 'confirm=true is required when dry_run is false.', 'wp-pinch' ) );
		}

		$map = self::get_db_scope_map( $wpdb );
		if ( ! isset( $map[ $scope ] ) ) {
			return array( 'error' => __( 'Invalid scope.', 'wp-pinch' ) );
		}

		$target = $map[ $scope ];
		$table  = $target['table'];
		$pk     = $target['pk'];
		$column = $target['column'];

		$like = '%' . $wpdb->esc_like( $search ) . '%';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column names are from strict internal allowlist.
		$sql = $wpdb->prepare( "SELECT {$pk} AS row_id, {$column} AS row_value FROM {$table} WHERE {$column} LIKE %s ORDER BY {$pk} ASC LIMIT %d", $like, $limit );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above.
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$matched            = 0;
		$changed            = 0;
		$sample             = array();
		$skipped_serialized = 0;

		foreach ( (array) $rows as $row ) {
			++$matched;
			$row_id = (int) $row['row_id'];
			$before = (string) $row['row_value'];
			if ( 'postmeta_value' === $scope && is_serialized( $before ) ) {
				++$skipped_serialized;
				continue;
			}
			$after = str_replace( $search, $replace, $before, $occurrences );
			if ( $occurrences < 1 ) {
				continue;
			}

			if ( count( $sample ) < 25 ) {
				$sample[] = array(
					'id'          => $row_id,
					'occurrences' => $occurrences,
				);
			}

			if ( ! $dry_run ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column names are allowlisted.
				$updated = $wpdb->update( $table, array( $column => $after ), array( $pk => $row_id ), array( '%s' ), array( '%d' ) );
				if ( false !== $updated ) {
					++$changed;
				}
			}
		}

		Audit_Table::insert(
			'db_search_replace_scoped',
			'ability',
			$dry_run ? 'Scoped DB search/replace dry run.' : 'Scoped DB search/replace executed.',
			array(
				'scope'              => $scope,
				'dry_run'            => $dry_run,
				'matched'            => $matched,
				'changed'            => $changed,
				'skipped_serialized' => $skipped_serialized,
			)
		);

		return array(
			'scope'                    => $scope,
			'dry_run'                  => $dry_run,
			'matched_count'            => $matched,
			'changed_count'            => $changed,
			'skipped_serialized_count' => $skipped_serialized,
			'sample'                   => $sample,
		);
	}

	/**
	 * List language packs.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_language_packs( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';

		$limit     = max( 1, min( absint( $input['limit'] ?? 100 ), 300 ) );
		$installed = wp_get_installed_translations( 'core' );
		$available = wp_get_available_translations();

		$installed_locales = array_keys( (array) $installed );
		$list              = array();
		$count             = 0;
		foreach ( (array) $available as $locale => $data ) {
			if ( $count >= $limit ) {
				break;
			}
			$list[] = array(
				'locale'       => (string) $locale,
				'native_name'  => (string) ( $data['native_name'] ?? '' ),
				'english_name' => (string) ( $data['english_name'] ?? '' ),
				'version'      => (string) ( $data['version'] ?? '' ),
				'installed'    => in_array( $locale, $installed_locales, true ),
			);
			++$count;
		}

		return array(
			'current_locale'  => get_locale(),
			'languages'       => $list,
			'installed_count' => count( $installed_locales ),
			'returned_count'  => count( $list ),
		);
	}

	/**
	 * Install language pack by locale.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_install_language_pack( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';

		$locale = sanitize_text_field( (string) ( $input['locale'] ?? '' ) );
		if ( '' === $locale ) {
			return array( 'error' => __( 'Locale is required.', 'wp-pinch' ) );
		}
		$locale = strtolower( str_replace( '-', '_', $locale ) );
		if ( ! preg_match( '/^[a-z]{2,3}(_[a-z0-9]{2,8})?$/', $locale ) ) {
			return array( 'error' => __( 'Locale format is invalid.', 'wp-pinch' ) );
		}

		$available = wp_get_available_translations();
		if ( ! isset( $available[ $locale ] ) ) {
			return array( 'error' => __( 'Locale is not available in core language packs.', 'wp-pinch' ) );
		}

		$result = wp_download_language_pack( $locale );
		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		Audit_Table::insert(
			'language_pack_installed',
			'ability',
			sprintf( 'Language pack "%s" installed.', $locale ),
			array( 'locale' => $locale )
		);

		return array(
			'locale'    => $locale,
			'installed' => true,
		);
	}

	/**
	 * Activate language pack as site locale.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_activate_language_pack( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';

		$locale = sanitize_text_field( (string) ( $input['locale'] ?? '' ) );
		if ( '' === $locale ) {
			return array( 'error' => __( 'Locale is required.', 'wp-pinch' ) );
		}
		$locale = strtolower( str_replace( '-', '_', $locale ) );
		if ( ! preg_match( '/^[a-z]{2,3}(_[a-z0-9]{2,8})?$/', $locale ) ) {
			return array( 'error' => __( 'Locale format is invalid.', 'wp-pinch' ) );
		}
		$available = wp_get_available_translations();
		if ( ! isset( $available[ $locale ] ) ) {
			return array( 'error' => __( 'Locale is not available in core language packs.', 'wp-pinch' ) );
		}

		update_option( 'WPLANG', $locale );

		Audit_Table::insert(
			'language_pack_activated',
			'ability',
			sprintf( 'Language pack "%s" activated.', $locale ),
			array( 'locale' => $locale )
		);

		return array(
			'locale'    => $locale,
			'activated' => true,
		);
	}

	/**
	 * Build strict mapping for DB scopes.
	 *
	 * @param \wpdb $wpdb Database object.
	 * @return array<string, array{table: string, pk: string, column: string}>
	 */
	private static function get_db_scope_map( \wpdb $wpdb ): array {
		return array(
			'posts_content'    => array(
				'table'  => $wpdb->posts,
				'pk'     => 'ID',
				'column' => 'post_content',
			),
			'postmeta_value'   => array(
				'table'  => $wpdb->postmeta,
				'pk'     => 'meta_id',
				'column' => 'meta_value',
			),
			'comments_content' => array(
				'table'  => $wpdb->comments,
				'pk'     => 'comment_ID',
				'column' => 'comment_content',
			),
		);
	}

	/**
	 * Sanitize transient key.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private static function sanitize_transient_key( string $key ): string {
		$key = sanitize_key( $key );
		return substr( $key, 0, 172 );
	}
}
