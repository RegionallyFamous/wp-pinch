<?php
/**
 * GDPR / Privacy compliance.
 *
 * - Suggests privacy policy content via wp_add_privacy_policy_content().
 * - Exports personal data (audit log entries) via the WordPress data exporter.
 * - Erases personal data (audit log entries) via the WordPress data eraser.
 *
 * @package WP_Pinch
 * @since   1.1.0
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy and GDPR integration.
 */
class Privacy {

	/**
	 * Number of audit log rows to process per batch.
	 */
	const BATCH_SIZE = 100;

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	// =========================================================================
	// Privacy Policy
	// =========================================================================

	/**
	 * Suggest privacy policy content for site administrators.
	 */
	public static function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<h2>%s</h2>' .
			'<p class="privacy-policy-tutorial">%s</p>' .
			'<p>%s</p>' .
			'<p>%s</p>' .
			'<p>%s</p>' .
			'<p>%s</p>',
			__( 'WP Pinch', 'wp-pinch' ),
			__( 'This section should be customised based on your specific use of WP Pinch.', 'wp-pinch' ),
			__( 'When you use the AI chat feature, we log the time and your user ID (not the message content) in an internal audit log. This log is retained for 90 days and then automatically deleted.', 'wp-pinch' ),
			__( 'Chat messages are forwarded to an external AI gateway (OpenClaw) for processing. The AI gateway may retain conversation data according to its own privacy policy.', 'wp-pinch' ),
			__( 'Site governance checks run automatically and may generate reports about site content. These reports do not contain personal data.', 'wp-pinch' ),
			__( 'Webhook events may include limited user information (such as user ID and display name) when forwarded to the AI gateway for user registration or comment events.', 'wp-pinch' )
		);

		wp_add_privacy_policy_content( 'WP Pinch', wp_kses_post( $content ) );
	}

	// =========================================================================
	// Personal Data Exporter
	// =========================================================================

	/**
	 * Register the personal data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['wp-pinch'] = array(
			'exporter_friendly_name' => __( 'WP Pinch Audit Log', 'wp-pinch' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Export personal data from the audit log.
	 *
	 * Searches the JSON context column for the user's email or user ID.
	 *
	 * @param string $email_address User email.
	 * @param int    $page          Current batch page.
	 * @return array{data: array, done: bool}
	 */
	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$table  = Audit_Table::table_name();
		$offset = ( $page - 1 ) * self::BATCH_SIZE;

		// Search for user_id in the JSON context column.
		// Match exactly "user_id":N followed by , or } to avoid partial matches (e.g. 1 matching 10).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table comes from Audit_Table::table_name().
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ( context LIKE %s OR context LIKE %s ) ORDER BY id ASC LIMIT %d OFFSET %d",
				'%' . $wpdb->esc_like( '"user_id":' . $user->ID . ',' ) . '%',
				'%' . $wpdb->esc_like( '"user_id":' . $user->ID . '}' ) . '%',
				self::BATCH_SIZE,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$export_items = array();

		foreach ( $entries as $entry ) {
			$export_items[] = array(
				'group_id'          => 'wp-pinch-audit',
				'group_label'       => __( 'WP Pinch Activity Log', 'wp-pinch' ),
				'group_description' => __( 'Records of your interactions with the WP Pinch AI assistant.', 'wp-pinch' ),
				'item_id'           => 'wp-pinch-audit-' . $entry['id'],
				'data'              => array(
					array(
						'name'  => __( 'Event Type', 'wp-pinch' ),
						'value' => $entry['event_type'],
					),
					array(
						'name'  => __( 'Source', 'wp-pinch' ),
						'value' => $entry['source'],
					),
					array(
						'name'  => __( 'Description', 'wp-pinch' ),
						'value' => $entry['message'],
					),
					array(
						'name'  => __( 'Context', 'wp-pinch' ),
						'value' => $entry['context'] ?? '',
					),
					array(
						'name'  => __( 'Date', 'wp-pinch' ),
						'value' => $entry['created_at'],
					),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => count( $entries ) < self::BATCH_SIZE,
		);
	}

	// =========================================================================
	// Personal Data Eraser
	// =========================================================================

	/**
	 * Register the personal data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['wp-pinch'] = array(
			'eraser_friendly_name' => __( 'WP Pinch Audit Log', 'wp-pinch' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Erase personal data from the audit log.
	 *
	 * Removes all audit log entries associated with the user.
	 *
	 * @param string $email_address User email.
	 * @param int    $page          Current batch page.
	 * @return array{items_removed: int, items_retained: int, messages: array, done: bool}
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$table = Audit_Table::table_name();

		// Build precise LIKE patterns that won't match user_id:1 when looking for user_id:10.
		$like_comma = '%' . $wpdb->esc_like( '"user_id":' . $user->ID . ',' ) . '%';
		$like_brace = '%' . $wpdb->esc_like( '"user_id":' . $user->ID . '}' ) . '%';

		// Count matching rows before deletion.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table comes from Audit_Table::table_name().
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ( context LIKE %s OR context LIKE %s )",
				$like_comma,
				$like_brace
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 0 === $count ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		// Delete in batches to avoid timeouts.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table comes from Audit_Table::table_name().
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE ( context LIKE %s OR context LIKE %s ) LIMIT %d",
				$like_comma,
				$like_brace,
				self::BATCH_SIZE
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $deleted ) {
			wp_cache_delete( 'audit_table_status', 'wp_pinch_site_health' );
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				try {
					wp_cache_flush_group( 'wp_pinch_audit' );
				} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Object cache may not support flush_group.
				}
			}
		}

		$remaining = $count - (int) $deleted;

		return array(
			'items_removed'  => (int) $deleted,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => $remaining <= 0,
		);
	}
}
