<?php
/**
 * Approval Queue — require explicit approval for destructive abilities.
 *
 * When the approval_workflow feature flag is enabled, designated abilities
 * executed via the incoming webhook are queued instead of running immediately.
 * An administrator must approve or reject each request.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Approval queue for destructive ability execution.
 */
class Approval_Queue {

	/**
	 * Option key for pending items.
	 */
	const OPTION_KEY = 'wp_pinch_approval_queue';

	/**
	 * Abilities that require approval when executed via incoming webhook.
	 *
	 * @var string[]
	 */
	const DESTRUCTIVE_ABILITIES = array(
		'wp-pinch/delete-post',
		'wp-pinch/delete-media',
		'wp-pinch/toggle-plugin',
		'wp-pinch/switch-theme',
		'wp-pinch/update-user-role',
		'wp-pinch/bulk-edit-posts',
		'wp-pinch/manage-cron',
	);

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 20 );
		add_action( 'wp_ajax_wp_pinch_approve_ability', array( __CLASS__, 'ajax_approve' ) );
		add_action( 'wp_ajax_wp_pinch_reject_ability', array( __CLASS__, 'ajax_reject' ) );
	}

	/**
	 * Check whether an ability requires approval.
	 *
	 * @param string $ability_name Ability name (e.g. wp-pinch/delete-post).
	 * @return bool
	 */
	public static function requires_approval( string $ability_name ): bool {
		if ( ! Feature_Flags::is_enabled( 'approval_workflow' ) ) {
			return false;
		}
		return in_array( $ability_name, self::DESTRUCTIVE_ABILITIES, true );
	}

	/**
	 * Add an ability execution request to the queue.
	 *
	 * @param string $ability_name Ability name.
	 * @param array  $params       Ability parameters.
	 * @param string $trace_id     Optional trace ID.
	 * @return string Queue item ID.
	 */
	public static function queue( string $ability_name, array $params, string $trace_id = '' ): string {
		$items   = self::get_pending();
		$item_id = 'aq_' . wp_generate_password( 12, false );
		$items[] = array(
			'id'        => $item_id,
			'ability'   => $ability_name,
			'params'    => $params,
			'trace_id'  => $trace_id,
			'queued_at' => gmdate( 'c' ),
			'queued_by' => 'incoming_webhook',
		);
		update_option( self::OPTION_KEY, $items, false );
		return $item_id;
	}

	/**
	 * Get pending queue items.
	 *
	 * @return array<int, array>
	 */
	public static function get_pending(): array {
		$items = get_option( self::OPTION_KEY, array() );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Remove an item from the queue by ID.
	 *
	 * @param string $item_id Queue item ID.
	 * @return bool True if found and removed.
	 */
	public static function remove( string $item_id ): bool {
		$items = self::get_pending();
		$len   = count( $items );
		$items = array_values(
			array_filter(
				$items,
				function ( $i ) use ( $item_id ) {
					return ( $i['id'] ?? '' ) !== $item_id;
				}
			)
		);
		if ( count( $items ) < $len ) {
			update_option( self::OPTION_KEY, $items, false );
			return true;
		}
		return false;
	}

	/**
	 * Get a queue item by ID.
	 *
	 * @param string $item_id Queue item ID.
	 * @return array|null Item data or null.
	 */
	public static function get_item( string $item_id ): ?array {
		foreach ( self::get_pending() as $item ) {
			if ( ( $item['id'] ?? '' ) === $item_id ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Add submenu page for approval queue.
	 */
	public static function add_menu(): void {
		if ( ! Feature_Flags::is_enabled( 'approval_workflow' ) ) {
			return;
		}
		$pending = self::get_pending();
		$count   = count( $pending );
		$title   = $count > 0
			? sprintf(
				/* translators: %d: number of pending approvals */
				__( 'Approvals (%d)', 'wp-pinch' ),
				$count
			)
			: __( 'Approvals', 'wp-pinch' );

		add_submenu_page(
			'wp-pinch',
			$title,
			$title,
			'manage_options',
			'wp-pinch-approvals',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the approvals admin page.
	 */
	public static function render_page(): void {
		$items = self::get_pending();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pending Approvals', 'wp-pinch' ); ?></h1>
			<p><?php esc_html_e( 'Destructive abilities executed via the incoming webhook are queued here. Approve to run, or reject to discard.', 'wp-pinch' ); ?></p>
			<?php if ( empty( $items ) ) : ?>
				<p><?php esc_html_e( 'No pending approvals.', 'wp-pinch' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ability', 'wp-pinch' ); ?></th>
							<th><?php esc_html_e( 'Parameters', 'wp-pinch' ); ?></th>
							<th><?php esc_html_e( 'Queued', 'wp-pinch' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wp-pinch' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td><code><?php echo esc_html( $item['ability'] ?? '' ); ?></code></td>
								<td><pre style="margin:0;max-width:400px;overflow:auto;"><?php echo esc_html( wp_json_encode( $item['params'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre></td>
								<td><?php echo esc_html( $item['queued_at'] ?? '' ); ?></td>
								<td>
									<?php
									$approve_url = wp_nonce_url(
										admin_url( 'admin-ajax.php?action=wp_pinch_approve_ability&id=' . rawurlencode( $item['id'] ?? '' ) ),
										'wp_pinch_approve_' . ( $item['id'] ?? '' )
									);
									$reject_url  = wp_nonce_url(
										admin_url( 'admin-ajax.php?action=wp_pinch_reject_ability&id=' . rawurlencode( $item['id'] ?? '' ) ),
										'wp_pinch_reject_' . ( $item['id'] ?? '' )
									);
									?>
									<a href="<?php echo esc_url( $approve_url ); ?>" class="button button-primary"><?php esc_html_e( 'Approve', 'wp-pinch' ); ?></a>
									<a href="<?php echo esc_url( $reject_url ); ?>" class="button"><?php esc_html_e( 'Reject', 'wp-pinch' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX: approve and execute a queued ability.
	 */
	public static function ajax_approve(): void {
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		if ( '' === $id ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-pinch' ), 400 );
		}
		check_ajax_referer( 'wp_pinch_approve_' . $id, '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-pinch' ), 403 );
		}

		$item = self::get_item( $id );
		if ( ! $item ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch-approvals&approved=expired' ) );
			exit;
		}

		$ability = $item['ability'] ?? '';
		$params  = $item['params'] ?? array();

		self::remove( $id );

		if ( ! function_exists( 'wp_execute_ability' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch-approvals&approved=error' ) );
			exit;
		}

		$previous_user  = get_current_user_id();
		$execution_user = OpenClaw_Role::get_execution_user_id();
		if ( $execution_user > 0 ) {
			wp_set_current_user( $execution_user );
		}
		$result = wp_execute_ability( $ability, $params );
		wp_set_current_user( $previous_user );

		Audit_Table::insert(
			'ability_approved',
			'approval_queue',
			sprintf( 'Approved and executed ability "%s" (queue id: %s).', $ability, $id ),
			array(
				'ability'  => $ability,
				'queue_id' => $id,
			)
		);

		$status = is_wp_error( $result ) ? 'error' : 'ok';
		wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch-approvals&approved=' . $status ) );
		exit;
	}

	/**
	 * AJAX: reject a queued ability.
	 */
	public static function ajax_reject(): void {
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		if ( '' === $id ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-pinch' ), 400 );
		}
		check_ajax_referer( 'wp_pinch_reject_' . $id, '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-pinch' ), 403 );
		}

		$item = self::get_item( $id );
		if ( $item ) {
			self::remove( $id );
			Audit_Table::insert(
				'ability_rejected',
				'approval_queue',
				sprintf( 'Rejected ability "%s" (queue id: %s).', $item['ability'] ?? '', $id ),
				array(
					'ability'  => $item['ability'] ?? '',
					'queue_id' => $id,
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch-approvals&rejected=1' ) );
		exit;
	}
}
