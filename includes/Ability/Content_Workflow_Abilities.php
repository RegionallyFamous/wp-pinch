<?php
/**
 * Content workflow and revision comparison abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

/**
 * Content workflow helpers (duplicate, schedule, replace, reorder, compare revisions).
 */
class Content_Workflow_Abilities {

	/**
	 * Meta keys we intentionally avoid copying when cloning a post.
	 *
	 * @var string[]
	 */
	private const DUPLICATE_META_DENYLIST = array(
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_trash_meta_status',
		'_wp_trash_meta_time',
	);

	/**
	 * Register content workflow abilities.
	 */
	public static function register(): void {
		Abilities::register_ability(
			'wp-pinch/duplicate-post',
			__( 'Duplicate Post', 'wp-pinch' ),
			__( 'Clone an existing post into a new draft.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id'             => array( 'type' => 'integer' ),
					'title'               => array( 'type' => 'string' ),
					'copy_featured_image' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'copy_taxonomies'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'copy_custom_fields'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'append_copy_suffix'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'status'              => array(
						'type'        => 'string',
						'default'     => 'draft',
						'description' => 'Status for the duplicated post. Defaults to draft for safety.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_duplicate_post' )
		);

		Abilities::register_ability(
			'wp-pinch/schedule-post',
			__( 'Schedule Post', 'wp-pinch' ),
			__( 'Schedule a post by setting status to future and assigning a publish date.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id', 'post_date' ),
				'properties' => array(
					'post_id'   => array( 'type' => 'integer' ),
					'post_date' => array(
						'type'        => 'string',
						'description' => 'Date/time in a format supported by strtotime(). Must be in the future.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_schedule_post' )
		);

		Abilities::register_ability(
			'wp-pinch/find-replace-content',
			__( 'Find and Replace Content', 'wp-pinch' ),
			__( 'Bulk replace a string in post content with dry-run support.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'search' ),
				'properties' => array(
					'search'    => array( 'type' => 'string' ),
					'replace'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'statuses'  => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Post statuses to scan.',
					),
					'limit'     => array(
						'type'        => 'integer',
						'default'     => 50,
						'description' => 'Maximum posts to scan (1-200).',
					),
					'dry_run'   => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Preview changes without writing to the database.',
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_find_replace_content' )
		);

		Abilities::register_ability(
			'wp-pinch/reorder-posts',
			__( 'Reorder Posts', 'wp-pinch' ),
			__( 'Set menu_order for multiple posts in one call.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'items' ),
				'properties' => array(
					'items' => array(
						'type'        => 'array',
						'description' => 'Array of objects with post_id and menu_order.',
						'items'       => array(
							'type'       => 'object',
							'required'   => array( 'post_id', 'menu_order' ),
							'properties' => array(
								'post_id'    => array( 'type' => 'integer' ),
								'menu_order' => array( 'type' => 'integer' ),
							),
						),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_others_posts',
			array( __CLASS__, 'execute_reorder_posts' )
		);

		Abilities::register_ability(
			'wp-pinch/compare-revisions',
			__( 'Compare Revisions', 'wp-pinch' ),
			__( 'Diff two revision IDs for the same post.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'from_revision_id', 'to_revision_id' ),
				'properties' => array(
					'from_revision_id' => array( 'type' => 'integer' ),
					'to_revision_id'   => array( 'type' => 'integer' ),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_compare_revisions' ),
			true
		);
	}

	/**
	 * Duplicate a post into a draft.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_duplicate_post( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$source  = get_post( $post_id );
		if ( ! $source ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to duplicate this post.', 'wp-pinch' ) );
		}

		$post_type_obj = get_post_type_object( $source->post_type );
		$create_cap    = is_object( $post_type_obj ) && isset( $post_type_obj->cap->create_posts ) ? (string) $post_type_obj->cap->create_posts : 'edit_posts';
		if ( ! current_user_can( $create_cap ) ) {
			return array( 'error' => __( 'You do not have permission to create this post type.', 'wp-pinch' ) );
		}

		$custom_title = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
		$use_suffix   = ! array_key_exists( 'append_copy_suffix', $input ) || ! empty( $input['append_copy_suffix'] );
		$new_title    = $custom_title;
		if ( '' === $new_title ) {
			$new_title = $source->post_title;
			if ( $use_suffix ) {
				$new_title .= ' (Copy)';
			}
		}

		$status      = sanitize_key( $input['status'] ?? 'draft' );
		$allowed     = array( 'draft', 'pending', 'future', 'private' );
		$post_status = in_array( $status, $allowed, true ) ? $status : 'draft';
		$new_post_id = wp_insert_post(
			array(
				'post_title'     => $new_title,
				'post_content'   => $source->post_content,
				'post_excerpt'   => $source->post_excerpt,
				'post_type'      => $source->post_type,
				'post_status'    => $post_status,
				'post_author'    => get_current_user_id(),
				'comment_status' => $source->comment_status,
				'ping_status'    => $source->ping_status,
				'menu_order'     => (int) $source->menu_order,
			),
			true
		);

		if ( is_wp_error( $new_post_id ) ) {
			return array( 'error' => $new_post_id->get_error_message() );
		}

		if ( ! array_key_exists( 'copy_custom_fields', $input ) || ! empty( $input['copy_custom_fields'] ) ) {
			self::copy_post_meta( $post_id, (int) $new_post_id );
		}

		if ( ! array_key_exists( 'copy_taxonomies', $input ) || ! empty( $input['copy_taxonomies'] ) ) {
			self::copy_taxonomies( $source, (int) $new_post_id );
		}

		if ( ! array_key_exists( 'copy_featured_image', $input ) || ! empty( $input['copy_featured_image'] ) ) {
			$thumb_id = (int) get_post_thumbnail_id( $post_id );
			if ( $thumb_id > 0 ) {
				set_post_thumbnail( (int) $new_post_id, $thumb_id );
			}
		}

		Audit_Table::insert(
			'post_duplicated',
			'ability',
			sprintf( 'Post #%d duplicated to #%d.', $post_id, $new_post_id ),
			array(
				'source_post_id' => $post_id,
				'post_id'        => (int) $new_post_id,
			)
		);

		return array(
			'id'             => (int) $new_post_id,
			'source_post_id' => $post_id,
			'status'         => get_post_status( (int) $new_post_id ),
			'url'            => get_permalink( (int) $new_post_id ),
		);
	}

	/**
	 * Schedule a post for future publishing.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_schedule_post( array $input ): array {
		$post_id  = absint( $input['post_id'] ?? 0 );
		$post     = get_post( $post_id );
		$date_raw = sanitize_text_field( (string) ( $input['post_date'] ?? '' ) );

		if ( ! $post ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to schedule this post.', 'wp-pinch' ) );
		}
		if ( '' === $date_raw ) {
			return array( 'error' => __( 'post_date is required.', 'wp-pinch' ) );
		}

		$timestamp = strtotime( $date_raw );
		if ( false === $timestamp ) {
			return array( 'error' => __( 'Invalid post_date format.', 'wp-pinch' ) );
		}
		if ( $timestamp <= time() ) {
			return array( 'error' => __( 'post_date must be in the future.', 'wp-pinch' ) );
		}

		$post_date     = wp_date( 'Y-m-d H:i:s', $timestamp );
		$post_date_gmt = gmdate( 'Y-m-d H:i:s', $timestamp );
		$result        = wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'future',
				'post_date'     => $post_date,
				'post_date_gmt' => $post_date_gmt,
				'edit_date'     => true,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		clean_post_cache( $post_id );

		Audit_Table::insert(
			'post_scheduled',
			'ability',
			sprintf( 'Post #%d scheduled for %s.', $post_id, $post_date ),
			array(
				'post_id'   => $post_id,
				'post_date' => $post_date,
			)
		);

		return array(
			'post_id'       => $post_id,
			'post_status'   => get_post_status( $post_id ),
			'post_date'     => get_post_field( 'post_date', $post_id ),
			'post_date_gmt' => get_post_field( 'post_date_gmt', $post_id ),
			'url'           => get_permalink( $post_id ),
		);
	}

	/**
	 * Bulk find and replace in post content.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_find_replace_content( array $input ): array {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'error' => __( 'You do not have permission to run find-replace.', 'wp-pinch' ) );
		}

		$search = trim( (string) ( $input['search'] ?? '' ) );
		if ( '' === $search ) {
			return array( 'error' => __( 'Search string cannot be empty.', 'wp-pinch' ) );
		}

		$post_type = sanitize_key( $input['post_type'] ?? 'post' );
		if ( ! post_type_exists( $post_type ) ) {
			return array( 'error' => __( 'Invalid post type.', 'wp-pinch' ) );
		}

		$post_type_obj = get_post_type_object( $post_type );
		$edit_cap      = is_object( $post_type_obj ) && isset( $post_type_obj->cap->edit_posts ) ? (string) $post_type_obj->cap->edit_posts : 'edit_posts';
		if ( ! current_user_can( $edit_cap ) ) {
			return array( 'error' => __( 'You do not have permission for this post type.', 'wp-pinch' ) );
		}

		$replace  = (string) ( $input['replace'] ?? '' );
		$dry_run  = ! array_key_exists( 'dry_run', $input ) || ! empty( $input['dry_run'] );
		$limit    = max( 1, min( absint( $input['limit'] ?? 50 ), 200 ) );
		$statuses = self::sanitize_statuses( $input['statuses'] ?? array() );

		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$sql                 = "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$status_placeholders}) AND post_content LIKE %s ORDER BY ID ASC LIMIT %d";
		$args                = array_merge(
			array( $post_type ),
			$statuses,
			array( '%' . $wpdb->esc_like( $search ) . '%', $limit )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared via $wpdb->prepare with dynamic placeholders.
		$query = $wpdb->prepare( $sql, $args );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared query built above.
		$rows = $wpdb->get_results( $query, ARRAY_A );

		$results     = array();
		$matched     = 0;
		$changed     = 0;
		$failed      = 0;
		$preview_cap = 30;
		$changed_ids = array();

		foreach ( (array) $rows as $row ) {
			$post_id  = (int) $row['ID'];
			$content  = (string) $row['post_content'];
			$replaced = str_replace( $search, $replace, $content, $occurrences );

			if ( $occurrences < 1 ) {
				continue;
			}

			++$matched;

			$entry = array(
				'post_id'      => $post_id,
				'occurrences'  => $occurrences,
				'before_bytes' => strlen( $content ),
				'after_bytes'  => strlen( $replaced ),
			);

			if ( count( $results ) < $preview_cap ) {
				$entry['title']   = get_the_title( $post_id );
				$entry['preview'] = self::build_preview( $replaced );
			}

			if ( ! $dry_run ) {
				$updated = $wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $replaced ),
					array( 'ID' => $post_id ),
					array( '%s' ),
					array( '%d' )
				);
				if ( false === $updated ) {
					$entry['error'] = __( 'Database update failed.', 'wp-pinch' );
					++$failed;
				} else {
					++$changed;
					$changed_ids[]    = $post_id;
					$entry['updated'] = true;
					clean_post_cache( $post_id );
				}
			}

			$results[] = $entry;
		}

		if ( ! $dry_run && ! empty( $changed_ids ) ) {
			Abilities::invalidate_ability_cache();
		}

		Audit_Table::insert(
			'content_find_replace',
			'ability',
			$dry_run ? 'Content find/replace dry run executed.' : 'Content find/replace update executed.',
			array(
				'post_type' => $post_type,
				'dry_run'   => $dry_run,
				'matched'   => $matched,
				'changed'   => $changed,
				'failed'    => $failed,
			)
		);

		return array(
			'post_type'     => $post_type,
			'dry_run'       => $dry_run,
			'matched_count' => $matched,
			'changed_count' => $changed,
			'failed_count'  => $failed,
			'results'       => $results,
			'truncated'     => count( $results ) >= $preview_cap,
		);
	}

	/**
	 * Reorder posts by updating menu_order.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_reorder_posts( array $input ): array {
		$items = $input['items'] ?? array();
		if ( ! is_array( $items ) || empty( $items ) ) {
			return array( 'error' => __( 'items must be a non-empty array.', 'wp-pinch' ) );
		}
		if ( count( $items ) > 100 ) {
			return array( 'error' => __( 'Maximum 100 items per request.', 'wp-pinch' ) );
		}

		$results  = array();
		$success  = 0;
		$failures = 0;

		foreach ( $items as $item ) {
			$post_id    = absint( is_array( $item ) ? ( $item['post_id'] ?? 0 ) : 0 );
			$menu_order = (int) ( is_array( $item ) ? ( $item['menu_order'] ?? 0 ) : 0 );

			if ( $post_id < 1 ) {
				$results[] = array(
					'post_id' => 0,
					'error'   => __( 'Invalid post_id.', 'wp-pinch' ),
				);
				++$failures;
				continue;
			}

			if ( ! get_post( $post_id ) ) {
				$results[] = array(
					'post_id' => $post_id,
					'error'   => __( 'Post not found.', 'wp-pinch' ),
				);
				++$failures;
				continue;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				$results[] = array(
					'post_id' => $post_id,
					'error'   => __( 'You do not have permission to edit this post.', 'wp-pinch' ),
				);
				++$failures;
				continue;
			}

			$updated = wp_update_post(
				array(
					'ID'         => $post_id,
					'menu_order' => $menu_order,
				),
				true
			);
			if ( is_wp_error( $updated ) ) {
				$results[] = array(
					'post_id' => $post_id,
					'error'   => $updated->get_error_message(),
				);
				++$failures;
				continue;
			}

			$results[] = array(
				'post_id'    => $post_id,
				'menu_order' => $menu_order,
			);
			++$success;
		}

		Audit_Table::insert(
			'posts_reordered',
			'ability',
			sprintf( 'Reordered %d posts (%d failures).', $success, $failures ),
			array(
				'success'  => $success,
				'failures' => $failures,
			)
		);

		return array(
			'success_count' => $success,
			'failed_count'  => $failures,
			'results'       => $results,
		);
	}

	/**
	 * Compare two revision IDs.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_compare_revisions( array $input ): array {
		$from_revision_id = absint( $input['from_revision_id'] ?? 0 );
		$to_revision_id   = absint( $input['to_revision_id'] ?? 0 );

		$from = wp_get_post_revision( $from_revision_id );
		$to   = wp_get_post_revision( $to_revision_id );
		if ( ! $from || ! $to ) {
			return array( 'error' => __( 'One or both revisions were not found.', 'wp-pinch' ) );
		}

		$post_id = (int) $from->post_parent;
		if ( $post_id < 1 || $post_id !== (int) $to->post_parent ) {
			return array( 'error' => __( 'Revisions must belong to the same post.', 'wp-pinch' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to compare revisions for this post.', 'wp-pinch' ) );
		}

		if ( ! function_exists( 'wp_get_revision_ui_diff' ) ) {
			require_once ABSPATH . 'wp-admin/includes/revision.php';
		}

		$diffs = wp_get_revision_ui_diff( $post_id, $from_revision_id, $to_revision_id );
		if ( ! is_array( $diffs ) ) {
			$diffs = array();
		}

		$changes = array_map(
			static function ( array $field ): array {
				$name = isset( $field['name'] ) ? wp_strip_all_tags( (string) $field['name'] ) : '';
				$id   = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
				$diff = isset( $field['diff'] ) ? wp_kses_post( (string) $field['diff'] ) : '';

				return array(
					'id'   => $id,
					'name' => $name,
					'diff' => $diff,
				);
			},
			$diffs
		);

		return array(
			'post_id'            => $post_id,
			'from_revision_id'   => $from_revision_id,
			'to_revision_id'     => $to_revision_id,
			'changes'            => $changes,
			'changes_count'      => count( $changes ),
			'from_revision_date' => $from->post_date,
			'to_revision_date'   => $to->post_date,
		);
	}

	/**
	 * Copy taxonomy terms to a duplicated post.
	 *
	 * @param \WP_Post $source Source post.
	 * @param int      $new_id New post ID.
	 */
	private static function copy_taxonomies( \WP_Post $source, int $new_id ): void {
		$taxonomies = get_object_taxonomies( $source->post_type, 'names' );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms(
				(int) $source->ID,
				$taxonomy,
				array( 'fields' => 'ids' )
			);
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			wp_set_object_terms( $new_id, array_map( 'absint', $terms ), $taxonomy );
		}
	}

	/**
	 * Copy post meta with a small denylist for volatile keys.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $new_id    New post ID.
	 */
	private static function copy_post_meta( int $source_id, int $new_id ): void {
		$all_meta = get_post_meta( $source_id );
		foreach ( $all_meta as $key => $values ) {
			if ( ! self::should_copy_meta_key( (string) $key ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				add_post_meta( $new_id, (string) $key, maybe_unserialize( $value ) );
			}
		}
	}

	/**
	 * Decide if a meta key should be copied during duplication.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	private static function should_copy_meta_key( string $key ): bool {
		if ( in_array( $key, self::DUPLICATE_META_DENYLIST, true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Sanitize status filters for find/replace.
	 *
	 * @param mixed $raw Raw input value.
	 * @return string[]
	 */
	private static function sanitize_statuses( $raw ): array {
		$default = array( 'publish', 'draft', 'pending', 'future', 'private' );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return $default;
		}

		$statuses = array();
		foreach ( $raw as $status ) {
			$key = sanitize_key( (string) $status );
			if ( '' === $key ) {
				continue;
			}
			$statuses[] = $key;
		}

		$statuses = array_values( array_unique( $statuses ) );
		if ( empty( $statuses ) ) {
			return $default;
		}

		return array_slice( $statuses, 0, 10 );
	}

	/**
	 * Build a compact text preview for dry-run output.
	 *
	 * @param string $content Content string.
	 * @return string
	 */
	private static function build_preview( string $content ): string {
		$plain = trim( wp_strip_all_tags( $content ) );
		if ( '' === $plain ) {
			return '';
		}
		$max = 180;
		if ( mb_strlen( $plain ) > $max ) {
			return mb_substr( $plain, 0, $max ) . '…';
		}
		return $plain;
	}
}
