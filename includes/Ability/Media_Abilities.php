<?php
/**
 * Media abilities: list, upload, delete.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

/**
 * Media library abilities.
 */
class Media_Abilities {

	/**
	 * Register media abilities with the main Abilities registry.
	 */
	public static function register(): void {
		Abilities::register_ability(
			'wp-pinch/list-media',
			__( 'List Media', 'wp-pinch' ),
			__( 'List media library items.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'mime_type' => array(
						'type'    => 'string',
						'default' => '',
					),
					'search'    => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'upload_files',
			array( __CLASS__, 'execute_list_media' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/upload-media',
			__( 'Upload Media', 'wp-pinch' ),
			__( 'Upload media from a URL.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'url' ),
				'properties' => array(
					'url'   => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'title' => array(
						'type'    => 'string',
						'default' => '',
					),
					'alt'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'upload_files',
			array( __CLASS__, 'execute_upload_media' )
		);

		Abilities::register_ability(
			'wp-pinch/delete-media',
			__( 'Delete Media', 'wp-pinch' ),
			__( 'Delete a media attachment.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id'    => array( 'type' => 'integer' ),
					'force' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'delete_posts',
			array( __CLASS__, 'execute_delete_media' )
		);
	}

	/**
	 * List media items.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with items, total, total_pages; or error key.
	 */
	public static function execute_list_media( array $input ): array {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) ),
			'paged'          => max( 1, absint( $input['page'] ?? 1 ) ),
		);

		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = sanitize_mime_type( $input['mime_type'] );
		}
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $attachment ) {
			$items[] = array(
				'id'        => $attachment->ID,
				'title'     => $attachment->post_title,
				'url'       => wp_get_attachment_url( $attachment->ID ),
				'mime_type' => $attachment->post_mime_type,
				'date'      => $attachment->post_date,
			);
		}

		return array(
			'items'       => $items,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
		);
	}

	/**
	 * Upload media from a URL.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with id, url; or error key.
	 */
	public static function execute_upload_media( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url = esc_url_raw( $input['url'] );

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return array( 'error' => __( 'Only HTTP and HTTPS URLs are allowed.', 'wp-pinch' ) );
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return array( 'error' => __( 'URL failed security validation. Use a public HTTP or HTTPS URL.', 'wp-pinch' ) );
		}

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return array( 'error' => $tmp->get_error_message() );
		}

		$file_array = array(
			'name'     => wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return array( 'error' => $attachment_id->get_error_message() );
		}

		if ( ! empty( $input['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $attachment_id,
					'post_title' => sanitize_text_field( $input['title'] ),
				)
			);
		}
		if ( ! empty( $input['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt'] ) );
		}

		Audit_Table::insert( 'media_uploaded', 'ability', sprintf( 'Media #%d uploaded via ability.', $attachment_id ), array( 'attachment_id' => $attachment_id ) );

		return array(
			'id'  => $attachment_id,
			'url' => wp_get_attachment_url( $attachment_id ),
		);
	}

	/**
	 * Delete a media attachment.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Result with id, deleted; or error key.
	 */
	public static function execute_delete_media( array $input ): array {
		$id = absint( $input['id'] );

		if ( ! get_post( $id ) || 'attachment' !== get_post_type( $id ) ) {
			return array( 'error' => __( 'Media attachment not found.', 'wp-pinch' ) );
		}

		if ( ! current_user_can( 'delete_post', $id ) ) {
			return array( 'error' => __( 'You do not have permission to delete this attachment.', 'wp-pinch' ) );
		}

		$force  = ! empty( $input['force'] );
		$result = wp_delete_attachment( $id, $force );

		if ( ! $result ) {
			return array( 'error' => __( 'Failed to delete media.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'media_deleted',
			'ability',
			sprintf( 'Media #%d deleted via ability.', $id ),
			array(
				'attachment_id' => $id,
				'force'         => $force,
			)
		);

		return array(
			'id'      => $id,
			'deleted' => true,
		);
	}

	/**
	 * Create an attachment from a URL or base64 data (for featured image in create-post).
	 * Used by Content_Abilities::execute_create_post.
	 *
	 * @param string $url              HTTP(S) URL (empty to use base64).
	 * @param string $base64           Base64 string or data URL.
	 * @param string $alt              Alt text for the image.
	 * @param int    $parent_post_id   Parent post ID for the attachment.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	public static function create_attachment_from_url_or_base64( string $url, string $base64, string $alt, int $parent_post_id ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( '' !== $url ) {
			$url    = esc_url_raw( $url );
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return new \WP_Error( 'invalid_url', __( 'Only HTTP and HTTPS URLs are allowed.', 'wp-pinch' ) );
			}
			if ( ! wp_http_validate_url( $url ) ) {
				return new \WP_Error( 'invalid_url', __( 'URL failed security validation.', 'wp-pinch' ) );
			}
			$tmp = download_url( $url );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			$path          = wp_parse_url( $url, PHP_URL_PATH );
			$basename      = ( false !== $path && '' !== $path ) ? wp_basename( $path ) : 'image';
			$file_array    = array(
				'name'     => $basename,
				'tmp_name' => $tmp,
			);
			$attachment_id = media_handle_sideload( $file_array, $parent_post_id );
			if ( is_wp_error( $attachment_id ) ) {
				wp_delete_file( $tmp );
				return $attachment_id;
			}
			if ( '' !== $alt ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
			}
			return $attachment_id;
		}

		if ( '' === $base64 ) {
			return new \WP_Error( 'missing_image', __( 'Provide featured_image_url or featured_image_base64.', 'wp-pinch' ) );
		}

		$mime_type = 'image/png';
		$extension = 'png';
		$data      = $base64;

		if ( str_starts_with( $base64, 'data:' ) ) {
			if ( preg_match( '#^data:([^;]+);base64,(.+)$#s', $base64, $m ) ) {
				$mime_type = trim( $m[1] );
				$data      = $m[2];
				$map       = array(
					'image/jpeg' => 'jpg',
					'image/jpg'  => 'jpg',
					'image/png'  => 'png',
					'image/gif'  => 'gif',
					'image/webp' => 'webp',
				);
				$extension = $map[ $mime_type ] ?? 'png';
			}
		}

		$decoded = base64_decode( $data, true );
		if ( false === $decoded || strlen( $decoded ) === 0 ) {
			return new \WP_Error( 'invalid_base64', __( 'Invalid base64 image data.', 'wp-pinch' ) );
		}

		$filename = 'featured-' . wp_unique_id() . '.' . $extension;
		$upload   = wp_upload_bits( $filename, null, $decoded );
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'upload_failed', $upload['error'] );
		}

		$attachment    = array(
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $parent_post_id,
		);
		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $parent_post_id );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $upload['file'] );
			return $attachment_id;
		}
		wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}
		return $attachment_id;
	}
}
