<?php
/**
 * Audit log database table — creation, insert, query, cleanup.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the wp_pinch_audit_log custom table.
 */
class Audit_Table {

	/**
	 * Max age for audit entries (in days).
	 */
	const RETENTION_DAYS = 90;

	/**
	 * Maximum rows in CSV export (DoS mitigation).
	 */
	const EXPORT_MAX_ROWS = 5000;

	/**
	 * Wire hooks — registers the cleanup callback so Action Scheduler can fire it.
	 */
	public static function init(): void {
		add_action( 'wp_pinch_audit_cleanup', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * Return the full table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'wp_pinch_audit_log';
	}

	/**
	 * Create (or update) the audit log table via dbDelta.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(64) NOT NULL DEFAULT '',
			source varchar(64) NOT NULL DEFAULT '',
			message text NOT NULL,
			context longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY source (source),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Schedule weekly cleanup if not already scheduled.
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			try {
				if ( ! as_has_scheduled_action( 'wp_pinch_audit_cleanup' ) ) {
					as_schedule_recurring_action( time(), WEEK_IN_SECONDS, 'wp_pinch_audit_cleanup', array(), 'wp-pinch' );
				}
			} catch ( \Throwable $e ) {
				// Cannot log to audit table here (would recurse), so use error_log.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WP Pinch: Failed to schedule audit cleanup: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}
	}

	/**
	 * Insert an audit log entry.
	 *
	 * @param string $event_type Short event identifier (e.g. "webhook_sent", "governance_finding").
	 * @param string $source     Origin subsystem (e.g. "webhook", "governance", "ability").
	 * @param string $message    Human-readable description.
	 * @param array  $context    Optional structured data stored as JSON. Never include API/capture tokens; use Utils::mask_token() if needed.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function insert( string $event_type, string $source, string $message, array $context = array() ) {
		global $wpdb;

		/**
		 * Filter audit entry before insertion.
		 *
		 * Return false to suppress the entry.
		 *
		 * @since 1.0.0
		 *
		 * @param array $entry {
		 *     @type string $event_type
		 *     @type string $source
		 *     @type string $message
		 *     @type array  $context
		 * }
		 */
		$entry = apply_filters(
			'wp_pinch_audit_entry',
			array(
				'event_type' => $event_type,
				'source'     => $source,
				'message'    => $message,
				'context'    => $context,
			)
		);

		if ( false === $entry ) {
			return false;
		}

		$result = $wpdb->insert(
			self::table_name(),
			array(
				'event_type' => sanitize_key( $entry['event_type'] ),
				'source'     => sanitize_key( $entry['source'] ),
				'message'    => sanitize_text_field( $entry['message'] ),
				'context'    => wp_json_encode( $entry['context'] ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Query audit log entries.
	 *
	 * @param array $args {
	 *     @type string $event_type Filter by event type.
	 *     @type string $source     Filter by source.
	 *     @type string $search     Full-text search in message column.
	 *     @type string $date_from  Filter entries from this date (Y-m-d).
	 *     @type string $date_to    Filter entries up to this date (Y-m-d).
	 *     @type int    $per_page   Results per page. Default 50.
	 *     @type int    $page       Page number. Default 1.
	 *     @type string $orderby    Column to sort by. Default 'created_at'.
	 *     @type string $order      ASC or DESC. Default 'DESC'.
	 * }
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'event_type' => '',
			'source'     => '',
			'search'     => '',
			'date_from'  => '',
			'date_to'    => '',
			'per_page'   => 50,
			'page'       => 1,
			'orderby'    => 'created_at',
			'order'      => 'DESC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = self::table_name();
		$where = array( '1=1' );

		if ( ! empty( $args['event_type'] ) ) {
			$where[] = $wpdb->prepare( 'event_type = %s', $args['event_type'] );
		}

		if ( ! empty( $args['source'] ) ) {
			$where[] = $wpdb->prepare( 'source = %s', $args['source'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = $wpdb->prepare( 'message LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['date_from'] . ' 00:00:00' );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['date_to'] . ' 23:59:59' );
		}

		$where_sql = implode( ' AND ', $where );

		$allowed_orderby = array( 'id', 'event_type', 'source', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === mb_strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = absint( $args['per_page'] );
		$offset   = ( absint( $args['page'] ) - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table and $where_sql are built from sanitized values above.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			),
			ARRAY_A
		);

		// Decode JSON context.
		foreach ( $items as &$item ) {
			$item['context'] = json_decode( $item['context'] ?? '{}', true );
		}

		return array(
			'items' => ! empty( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Export audit log entries as CSV.
	 *
	 * @param array $args Same arguments as query().
	 * @return string CSV content.
	 */
	public static function export_csv( array $args = array() ): string {
		// Cap export size to limit memory and DoS risk.
		$args['per_page'] = self::EXPORT_MAX_ROWS;
		$args['page']     = 1;

		$result = self::query( $args );
		$items  = $result['items'];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp stream for CSV generation.
		$output = fopen( 'php://temp', 'r+' );

		// CSV header.
		fputcsv( $output, array( 'ID', 'Date', 'Event Type', 'Source', 'Message', 'Context' ) );

		foreach ( $items as $item ) {
			fputcsv(
				$output,
				array(
					$item['id'],
					$item['created_at'],
					$item['event_type'],
					$item['source'],
					$item['message'],
					wp_json_encode( $item['context'] ),
				)
			);
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing php://temp stream.
		fclose( $output );

		return $csv;
	}

	/**
	 * Delete entries older than the retention period.
	 */
	public static function cleanup(): void {
		global $wpdb;

		$table    = self::table_name();
		$days_ago = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$days_ago
			)
		);
	}
}
