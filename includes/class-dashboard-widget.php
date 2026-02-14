<?php
/**
 * WP Pinch Activity Feed dashboard widget.
 *
 * Displays recent audit log entries (ability runs, webhooks, governance findings)
 * on the WordPress dashboard.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard widget for WP Pinch activity.
 */
class Dashboard_Widget {

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the dashboard widget.
	 */
	public static function register(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wp_pinch_activity',
			__( 'WP Pinch Activity', 'wp-pinch' ),
			array( __CLASS__, 'render' ),
			null,
			null,
			'normal'
		);
	}

	/**
	 * Render the widget content.
	 */
	public static function render(): void {
		$result = Audit_Table::query(
			array(
				'per_page' => 10,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		$items = $result['items'];
		$total = $result['total'];

		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No activity yet.', 'wp-pinch' ) . '</p>';
			$settings_url = admin_url( 'admin.php?page=wp-pinch' );
			/* translators: %s: link to WP Pinch settings */
			printf(
				'<p><a href="%s">%s</a></p>',
				esc_url( $settings_url ),
				esc_html__( 'Configure WP Pinch &rarr;', 'wp-pinch' )
			);
			return;
		}

		echo '<ul class="wp-pinch-activity-list" style="margin:0;padding-left:1.2em;">';
		foreach ( $items as $item ) {
			$source  = $item['source'] ?? '';
			$message = $item['message'] ?? '';
			$created = $item['created_at'] ?? '';
			$context = $item['context'] ?? array();

			$post_id   = $context['post_id'] ?? null;
			$post_link = '';
			if ( $post_id ) {
				$edit_url = get_edit_post_link( (int) $post_id, 'raw' );
				if ( $edit_url ) {
					$post_link = sprintf( ' <a href="%s">#%d</a>', esc_url( $edit_url ), (int) $post_id );
				}
			}

			$time_ago     = $created ? human_time_diff( strtotime( $created ), time() ) . ' ' . __( 'ago', 'wp-pinch' ) : '';
			$source_label = self::source_label( $source );

			printf(
				'<li style="margin-bottom:0.5em;"><span style="color:#646970;">[%s]</span> %s%s <span style="color:#787c82;">(%s)</span></li>',
				esc_html( $source_label ),
				esc_html( $message ),
				wp_kses_post( $post_link ),
				esc_html( $time_ago )
			);
		}
		echo '</ul>';

		$audit_url = add_query_arg(
			array(
				'page' => 'wp-pinch',
				'tab'  => 'audit',
			),
			admin_url( 'admin.php' )
		);
		printf(
			'<p style="margin-top:0.75em;"><a href="%s">%s</a></p>',
			esc_url( $audit_url ),
			esc_html__( 'View full audit log &rarr;', 'wp-pinch' )
		);
	}

	/**
	 * Human-readable source label.
	 *
	 * @param string $source Source key.
	 * @return string
	 */
	private static function source_label( string $source ): string {
		$labels = array(
			'ability'    => __( 'Ability', 'wp-pinch' ),
			'webhook'    => __( 'Webhook', 'wp-pinch' ),
			'governance' => __( 'Governance', 'wp-pinch' ),
			'molt'       => __( 'Molt', 'wp-pinch' ),
		);
		return $labels[ $source ] ?? $source;
	}
}
