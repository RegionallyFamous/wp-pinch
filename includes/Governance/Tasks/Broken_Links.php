<?php
/**
 * Broken links governance task.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Governance\Tasks;

use WP_Pinch\Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Broken links — check for dead links in content.
 */
class Broken_Links {

	/**
	 * Run the task.
	 */
	public static function run(): void {
		/** This filter is documented in class-governance.php */
		$batch_size = min( absint( apply_filters( 'wp_pinch_broken_links_batch_size', 50 ) ), 200 );

		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$links_checked = 0;
		$broken        = array();

		foreach ( $posts as $post ) {
			if ( $links_checked >= $batch_size ) {
				break;
			}
			preg_match_all( '/href=["\']([^"\']+)["\']/i', $post->post_content, $matches );
			if ( empty( $matches[1] ) ) {
				continue;
			}
			$urls = array_unique( $matches[1] );
			foreach ( $urls as $url ) {
				if ( $links_checked >= $batch_size ) {
					break;
				}
				if ( preg_match( '/^(#|mailto:|tel:|javascript:|ftp:|data:|gopher:|dict:)/i', $url ) ) {
					continue;
				}
				if ( ! preg_match( '/^https?:\/\//', $url ) ) {
					$url = home_url( $url );
				}
				$host = wp_parse_url( $url, PHP_URL_HOST );
				if ( $host ) {
					$ip = gethostbyname( $host );
					if ( $ip !== $host && false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						continue;
					}
				}
				$response = wp_remote_head(
					$url,
					array(
						'timeout'     => 5,
						'redirection' => 3,
						'sslverify'   => true,
					)
				);
				++$links_checked;
				$status = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
				if ( 0 === $status || $status >= 400 ) {
					$broken[] = array(
						'post_id'    => $post->ID,
						'post_title' => $post->post_title,
						'url'        => $url,
						'status'     => $status,
						'error'      => is_wp_error( $response ) ? $response->get_error_message() : "HTTP {$status}",
					);
				}
			}
		}

		if ( empty( $broken ) ) {
			return;
		}

		Governance::deliver_findings(
			'broken_links',
			$broken,
			sprintf(
				'%d broken links found across %d posts (checked %d links).',
				count( $broken ),
				count( $posts ),
				$links_checked
			)
		);
	}
}
