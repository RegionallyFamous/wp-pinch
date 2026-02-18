<?php
/**
 * Extended media abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

/**
 * Media helpers beyond basic list/upload/delete.
 */
class Media_Extended_Abilities {

	/**
	 * Register extended media abilities.
	 */
	public static function register(): void {
		Abilities::register_ability(
			'wp-pinch/set-featured-image',
			__( 'Set Featured Image', 'wp-pinch' ),
			__( 'Set or remove a post featured image.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id'       => array( 'type' => 'integer' ),
					'attachment_id' => array(
						'type'        => 'integer',
						'description' => 'Existing attachment ID to set as featured image.',
					),
					'image_url'     => array(
						'type'        => 'string',
						'description' => 'HTTP/HTTPS image URL to upload and set as featured image.',
					),
					'image_base64'  => array(
						'type'        => 'string',
						'description' => 'Base64 image data (or data URL) to upload and set.',
					),
					'image_alt'     => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Optional alt text for uploaded image.',
					),
					'remove'        => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_set_featured_image' )
		);

		Abilities::register_ability(
			'wp-pinch/list-unused-media',
			__( 'List Unused Media', 'wp-pinch' ),
			__( 'List attachment items with no parent and no obvious references in post content.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'per_page'             => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'                 => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'mime_type'            => array(
						'type'    => 'string',
						'default' => '',
					),
					'check_content_refs'   => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'When true, scans post content for URL references.',
					),
					'max_content_ref_scan' => array(
						'type'        => 'integer',
						'default'     => 50,
						'description' => 'Maximum attachments to content-scan per page.',
					),
				),
			),
			array( 'type' => 'object' ),
			'upload_files',
			array( __CLASS__, 'execute_list_unused_media' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/regenerate-media-thumbnails',
			__( 'Regenerate Media Thumbnails', 'wp-pinch' ),
			__( 'Regenerate image metadata and thumbnails for specific attachments.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'attachment_ids' ),
				'properties' => array(
					'attachment_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Attachment IDs to regenerate (max 50).',
					),
				),
			),
			array( 'type' => 'object' ),
			'upload_files',
			array( __CLASS__, 'execute_regenerate_media_thumbnails' )
		);
	}

	/**
	 * Set or remove featured image on a post.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_set_featured_image( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => __( 'Post not found.', 'wp-pinch' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to edit this post.', 'wp-pinch' ) );
		}

		$remove = ! empty( $input['remove'] );
		if ( $remove ) {
			$removed = delete_post_thumbnail( $post_id );
			Audit_Table::insert(
				'featured_image_removed',
				'ability',
				sprintf( 'Featured image removed from post #%d.', $post_id ),
				array( 'post_id' => $post_id )
			);
			return array(
				'post_id' => $post_id,
				'removed' => (bool) $removed,
			);
		}

		$attachment_id = absint( $input['attachment_id'] ?? 0 );
		if ( $attachment_id < 1 ) {
			if ( ! current_user_can( 'upload_files' ) ) {
				return array( 'error' => __( 'You do not have permission to upload files.', 'wp-pinch' ) );
			}
			$image_url    = isset( $input['image_url'] ) ? trim( (string) $input['image_url'] ) : '';
			$image_base64 = isset( $input['image_base64'] ) ? trim( (string) $input['image_base64'] ) : '';
			$image_alt    = sanitize_text_field( (string) ( $input['image_alt'] ?? '' ) );

			if ( '' === $image_url && '' === $image_base64 ) {
				return array( 'error' => __( 'Provide attachment_id, image_url, image_base64, or set remove=true.', 'wp-pinch' ) );
			}

			$created = Media_Abilities::create_attachment_from_url_or_base64( $image_url, $image_base64, $image_alt, $post_id );
			if ( is_wp_error( $created ) ) {
				return array( 'error' => $created->get_error_message() );
			}
			$attachment_id = (int) $created;
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return array( 'error' => __( 'Attachment not found.', 'wp-pinch' ) );
		}

		$result = set_post_thumbnail( $post_id, $attachment_id );
		if ( ! $result ) {
			return array( 'error' => __( 'Failed to set featured image.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'featured_image_set',
			'ability',
			sprintf( 'Featured image #%d set on post #%d.', $attachment_id, $post_id ),
			array(
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
			)
		);

		return array(
			'post_id'            => $post_id,
			'featured_image_id'  => $attachment_id,
			'featured_image_url' => wp_get_attachment_url( $attachment_id ),
		);
	}

	/**
	 * List media likely unused.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_unused_media( array $input ): array {
		$per_page           = max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) );
		$page               = max( 1, absint( $input['page'] ?? 1 ) );
		$mime_type          = sanitize_mime_type( (string) ( $input['mime_type'] ?? '' ) );
		$check_content_refs = ! array_key_exists( 'check_content_refs', $input ) || ! empty( $input['check_content_refs'] );
		$max_ref_scan       = max( 1, min( absint( $input['max_content_ref_scan'] ?? 50 ), 200 ) );

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => 0,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( '' !== $mime_type ) {
			$args['post_mime_type'] = $mime_type;
		}

		$query          = new \WP_Query( $args );
		$items          = array();
		$scanned        = 0;
		$ref_scan_count = 0;

		foreach ( $query->posts as $attachment ) {
			++$scanned;
			$is_referenced = false;
			if ( $check_content_refs && $ref_scan_count < $max_ref_scan ) {
				$is_referenced = self::is_referenced_in_content( (int) $attachment->ID );
				++$ref_scan_count;
			}
			if ( $is_referenced ) {
				continue;
			}

			$items[] = array(
				'id'        => (int) $attachment->ID,
				'title'     => $attachment->post_title,
				'url'       => wp_get_attachment_url( (int) $attachment->ID ),
				'mime_type' => $attachment->post_mime_type,
				'date'      => $attachment->post_date,
			);
		}

		return array(
			'items'                 => $items,
			'page'                  => $page,
			'per_page'              => $per_page,
			'scanned'               => $scanned,
			'unused_count'          => count( $items ),
			'content_ref_scan_used' => $ref_scan_count,
			'content_ref_scan_cap'  => $max_ref_scan,
		);
	}

	/**
	 * Regenerate attachment metadata and thumbnails.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_regenerate_media_thumbnails( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$ids = array_values(
			array_filter(
				array_map( 'absint', is_array( $input['attachment_ids'] ?? null ) ? $input['attachment_ids'] : array() )
			)
		);

		if ( empty( $ids ) ) {
			return array( 'error' => __( 'attachment_ids is required.', 'wp-pinch' ) );
		}
		if ( count( $ids ) > 50 ) {
			return array( 'error' => __( 'Maximum 50 attachments per request.', 'wp-pinch' ) );
		}

		$results = array();
		$success = 0;
		$failed  = 0;

		foreach ( $ids as $id ) {
			if ( 'attachment' !== get_post_type( $id ) ) {
				$results[] = array(
					'id'    => $id,
					'error' => __( 'Attachment not found.', 'wp-pinch' ),
				);
				++$failed;
				continue;
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				$results[] = array(
					'id'    => $id,
					'error' => __( 'You do not have permission to edit this attachment.', 'wp-pinch' ),
				);
				++$failed;
				continue;
			}

			$file = get_attached_file( $id );
			if ( ! is_string( $file ) || '' === $file ) {
				$results[] = array(
					'id'    => $id,
					'error' => __( 'Attachment file path not found.', 'wp-pinch' ),
				);
				++$failed;
				continue;
			}
			if ( ! file_exists( $file ) ) {
				$results[] = array(
					'id'    => $id,
					'error' => __( 'Attachment file does not exist on disk.', 'wp-pinch' ),
				);
				++$failed;
				continue;
			}

			$metadata = wp_generate_attachment_metadata( $id, $file );
			if ( is_wp_error( $metadata ) ) {
				$results[] = array(
					'id'    => $id,
					'error' => $metadata->get_error_message(),
				);
				++$failed;
				continue;
			}
			if ( ! is_array( $metadata ) ) {
				$results[] = array(
					'id'    => $id,
					'error' => __( 'Failed to generate metadata.', 'wp-pinch' ),
				);
				++$failed;
				continue;
			}

			wp_update_attachment_metadata( $id, $metadata );
			$results[] = array(
				'id'          => $id,
				'regenerated' => true,
			);
			++$success;
		}

		Audit_Table::insert(
			'media_thumbnails_regenerated',
			'ability',
			sprintf( 'Regenerated thumbnails for %d attachments (%d failed).', $success, $failed ),
			array(
				'success' => $success,
				'failed'  => $failed,
			)
		);

		return array(
			'success_count' => $success,
			'failed_count'  => $failed,
			'results'       => $results,
		);
	}

	/**
	 * Check whether an attachment URL appears in post content.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private static function is_referenced_in_content( int $attachment_id ): bool {
		global $wpdb;

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$candidates = array( $url );
		$path       = wp_parse_url( $url, PHP_URL_PATH );
		if ( is_string( $path ) && '' !== $path ) {
			$candidates[] = $path;
		}

		$candidates = array_values( array_unique( array_filter( $candidates ) ) );
		foreach ( $candidates as $candidate ) {
			$like = '%' . $wpdb->esc_like( (string) $candidate ) . '%';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb.
			$sql = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type <> 'attachment' AND post_status NOT IN ('trash', 'auto-draft', 'inherit') AND post_content LIKE %s LIMIT 1",
				$like
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above.
			$found = $wpdb->get_var( $sql );
			if ( ! empty( $found ) ) {
				return true;
			}
		}

		return false;
	}
}
