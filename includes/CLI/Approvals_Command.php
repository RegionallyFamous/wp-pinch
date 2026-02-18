<?php
/**
 * WP-CLI: wp pinch approvals
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\CLI;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Approvals command — list, approve, or reject queued abilities.
 */
class Approvals_Command {

	/**
	 * Register the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'pinch approvals', array( __CLASS__, 'run' ) );
	}

	/**
	 * List pending approvals, or approve/reject by ID.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : "list", "approve", or "reject".
	 *
	 * [<id>]
	 * : Queue item ID for approve/reject.
	 *
	 * [--format=<format>]
	 * : Output format for list.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		if ( ! \WP_Pinch\Feature_Flags::is_enabled( 'approval_workflow' ) ) {
			\WP_CLI::error( 'Approval workflow is disabled. Enable the "approval_workflow" feature flag first.' );
		}

		$subcommand = $args[0] ?? 'list';

		if ( 'list' === $subcommand ) {
			$items  = \WP_Pinch\Approval_Queue::get_pending();
			$format = $assoc_args['format'] ?? 'table';

			if ( empty( $items ) ) {
				\WP_CLI::log( 'No pending approvals.' );
				return;
			}

			$rows = array();
			foreach ( $items as $item ) {
				$rows[] = array(
					'ID'      => $item['id'] ?? '',
					'Ability' => $item['ability'] ?? '',
					'Queued'  => $item['queued_at'] ?? '',
				);
			}
			\WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Ability', 'Queued' ) );
			return;
		}

		if ( 'approve' === $subcommand ) {
			$id = $args[1] ?? '';
			if ( '' === $id ) {
				\WP_CLI::error( 'Provide a queue item ID. Example: wp pinch approvals approve aq_xxxx' );
			}

			$result = \WP_Pinch\Approval_Queue::approve_item( $id );
			if ( true === $result ) {
				\WP_CLI::success( 'Approved and executed.' );
				return;
			}
			\WP_CLI::error( $result->get_error_message() );
		}

		if ( 'reject' === $subcommand ) {
			$id = $args[1] ?? '';
			if ( '' === $id ) {
				\WP_CLI::error( 'Provide a queue item ID. Example: wp pinch approvals reject aq_xxxx' );
			}

			if ( \WP_Pinch\Approval_Queue::reject_item( $id ) ) {
				\WP_CLI::success( 'Rejected.' );
			} else {
				\WP_CLI::error( 'Item not found or already processed.' );
			}
			return;
		}

		\WP_CLI::error( 'Unknown subcommand. Use "list", "approve", or "reject".' );
	}
}
