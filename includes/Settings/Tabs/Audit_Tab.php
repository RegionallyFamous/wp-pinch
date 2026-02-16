<?php
/**
 * Audit Log tab — search, filters, table, CSV export.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings\Tabs;

use WP_Pinch\Audit_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Audit tab.
 */
class Audit_Tab {

	/**
	 * Render the tab content.
	 */
	public static function render(): void {
		// Filter params are sanitized; export is protected by nonce below. Admin-only (manage_options).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page      = max( 1, absint( $_GET['audit_page'] ?? 1 ) );
		$filter    = sanitize_key( $_GET['event_type'] ?? '' );
		$source    = sanitize_key( $_GET['source'] ?? '' );
		$search    = sanitize_text_field( wp_unslash( $_GET['audit_search'] ?? '' ) );
		$date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
		$date_to   = sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) );
		// phpcs:enable

		$query_args = array(
			'event_type' => $filter,
			'source'     => $source,
			'search'     => $search,
			'date_from'  => $date_from,
			'date_to'    => $date_to,
			'per_page'   => 30,
			'page'       => $page,
		);

		$result    = Audit_Table::query( $query_args );
		$items     = $result['items'];
		$total     = $result['total'];
		$max_pages = (int) ceil( $total / 30 );
		?>
		<h3><?php esc_html_e( 'Audit Log', 'wp-pinch' ); ?></h3>

		<!-- Search & Filter Bar -->
		<div class="wp-pinch-audit-filters">
			<form method="get" action="">
				<input type="hidden" name="page" value="wp-pinch" />
				<input type="hidden" name="tab" value="audit" />

				<label for="audit_search"><?php esc_html_e( 'Search:', 'wp-pinch' ); ?></label>
				<input type="text" id="audit_search" name="audit_search"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php esc_attr_e( 'Search messages...', 'wp-pinch' ); ?>"
					class="regular-text" />

				<label for="event_type"><?php esc_html_e( 'Event:', 'wp-pinch' ); ?></label>
				<select id="event_type" name="event_type" class="wp-pinch-audit-input-event">
					<option value=""><?php esc_html_e( 'All events', 'wp-pinch' ); ?></option>
					<option value="ability_executed" <?php selected( $filter, 'ability_executed' ); ?>><?php esc_html_e( 'Ability executed', 'wp-pinch' ); ?></option>
					<option value="batch_executed" <?php selected( $filter, 'batch_executed' ); ?>><?php esc_html_e( 'Batch executed', 'wp-pinch' ); ?></option>
					<option value="post_created" <?php selected( $filter, 'post_created' ); ?>><?php esc_html_e( 'Post created', 'wp-pinch' ); ?></option>
					<option value="post_updated" <?php selected( $filter, 'post_updated' ); ?>><?php esc_html_e( 'Post updated', 'wp-pinch' ); ?></option>
					<option value="preview_approved" <?php selected( $filter, 'preview_approved' ); ?>><?php esc_html_e( 'Preview approved', 'wp-pinch' ); ?></option>
					<option value="webhook_sent" <?php selected( $filter, 'webhook_sent' ); ?>><?php esc_html_e( 'Webhook sent', 'wp-pinch' ); ?></option>
					<option value="webhook_failed" <?php selected( $filter, 'webhook_failed' ); ?>><?php esc_html_e( 'Webhook failed', 'wp-pinch' ); ?></option>
					<option value="chat_message" <?php selected( $filter, 'chat_message' ); ?>><?php esc_html_e( 'Chat message', 'wp-pinch' ); ?></option>
					<option value="incoming_hook" <?php selected( $filter, 'incoming_hook' ); ?>><?php esc_html_e( 'Incoming hook', 'wp-pinch' ); ?></option>
				</select>

				<label for="source"><?php esc_html_e( 'Source:', 'wp-pinch' ); ?></label>
				<input type="text" id="source" name="source"
					value="<?php echo esc_attr( $source ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. webhook', 'wp-pinch' ); ?>"
					class="regular-text wp-pinch-audit-input-source" />

				<br class="wp-pinch-audit-filters-br" />

				<label for="date_from"><?php esc_html_e( 'From:', 'wp-pinch' ); ?></label>
				<input type="date" id="date_from" name="date_from"
					value="<?php echo esc_attr( $date_from ); ?>" />

				<label for="date_to"><?php esc_html_e( 'To:', 'wp-pinch' ); ?></label>
				<input type="date" id="date_to" name="date_to"
					value="<?php echo esc_attr( $date_to ); ?>" />

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-pinch' ); ?></button>

				<?php if ( $search || $filter || $source || $date_from || $date_to ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-pinch&tab=audit' ) ); ?>" class="button">
						<?php esc_html_e( 'Reset', 'wp-pinch' ); ?>
					</a>
				<?php endif; ?>
			</form>
		</div>

		<p>
			<?php
			printf(
				/* translators: %d: total number of log entries */
				esc_html__( '%d entries found. Entries older than 90 days are automatically removed.', 'wp-pinch' ),
				(int) $total
			);
			?>

			<?php if ( $total > 0 ) : ?>
				&mdash;
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_pinch_export_audit', '1' ), 'wp_pinch_export_audit' ) ); ?>">
					<?php esc_html_e( 'Export CSV', 'wp-pinch' ); ?>
				</a>
			<?php endif; ?>
		</p>

		<?php if ( ! empty( $items ) ) : ?>
			<div class="wp-pinch-audit-table-wrap">
			<table class="widefat striped wp-pinch-audit-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Event', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Source', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Message', 'wp-pinch' ); ?></th>
						<th><?php esc_html_e( 'Details', 'wp-pinch' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php
						$ctx     = ( isset( $item['context'] ) && is_array( $item['context'] ) ) ? $item['context'] : array();
						$details = array();
						if ( ! empty( $ctx['post_id'] ) ) {
							$details[] = 'post_id: ' . (int) $ctx['post_id'];
						}
						if ( ! empty( $ctx['ability'] ) ) {
							$details[] = 'ability: ' . esc_html( (string) $ctx['ability'] );
						}
						if ( ! empty( $ctx['diff'] ) && is_array( $ctx['diff'] ) ) {
							$details[] = 'diff: ' . esc_html( wp_json_encode( $ctx['diff'] ) );
						}
						if ( ! empty( $ctx['request_summary'] ) && is_array( $ctx['request_summary'] ) ) {
							$details[] = 'params: ' . esc_html( wp_json_encode( $ctx['request_summary'] ) );
						}
						$details_str = implode( '; ', $details );
						if ( mb_strlen( $details_str ) > 120 ) {
							$details_str = mb_substr( $details_str, 0, 120 ) . '…';
						}
						?>
						<tr>
							<td class="wp-pinch-audit-date"><?php echo esc_html( $item['created_at'] ); ?></td>
							<td><code><?php echo esc_html( $item['event_type'] ); ?></code></td>
							<td><?php echo esc_html( $item['source'] ); ?></td>
							<td><?php echo esc_html( $item['message'] ); ?></td>
							<td class="wp-pinch-audit-details"><?php echo esc_html( $details_str ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>

			<?php if ( $max_pages > 1 ) : ?>
				<div class="tablenav wp-pinch-audit-nav">
					<div class="tablenav-pages">
						<?php
						$base_url      = remove_query_arg( 'audit_page' );
						$max_page_link = min( $max_pages, 50 );
						for ( $i = 1; $i <= $max_page_link; $i++ ) :
							if ( $i === $page ) :
								?>
								<strong><?php echo esc_html( (string) $i ); ?></strong>
							<?php else : ?>
								<a href="<?php echo esc_url( add_query_arg( 'audit_page', $i, $base_url ) ); ?>"><?php echo esc_html( (string) $i ); ?></a>
							<?php endif; ?>
						<?php endfor; ?>
						<?php if ( $max_pages > 50 ) : ?>
							<span>&hellip; (<?php echo esc_html( (string) $max_pages ); ?> pages)</span>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="wp-pinch-audit-empty">
				<div class="wp-pinch-audit-empty-icon" aria-hidden="true">🦞</div>
				<p><?php esc_html_e( 'Nothing in the log yet — the waters are calm.', 'wp-pinch' ); ?></p>
				<p class="description"><?php esc_html_e( 'Events will pinch in once webhooks run or the chat block is used. Try adjusting filters or scuttle back later.', 'wp-pinch' ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}
}
