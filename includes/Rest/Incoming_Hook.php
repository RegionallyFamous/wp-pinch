<?php
/**
 * REST handler for incoming webhook from OpenClaw (execute_ability, execute_batch, run_governance, ping).
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

use WP_Pinch\Abilities;
use WP_Pinch\Approval_Queue;
use WP_Pinch\Audit_Table;
use WP_Pinch\Governance;
use WP_Pinch\OpenClaw_Role;
use WP_Pinch\Plugin;
use WP_Pinch\Rest_Controller;
use WP_Pinch\Webhook_Dispatcher;

defined( 'ABSPATH' ) || exit;

/**
 * Incoming hook REST handler.
 */
class Incoming_Hook {

	/**
	 * Handle an incoming webhook from OpenClaw.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_incoming_hook( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}
		$limit = (int) apply_filters( 'wp_pinch_incoming_rate_limit', 120 );
		if ( $limit > 0 && ! Helpers::check_ip_rate_limit( 'wp_pinch_incoming_', $limit ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
				),
				429
			);
		}
		$action   = $request->get_param( 'action' );
		$trace_id = Rest_Controller::get_trace_id();
		Audit_Table::insert(
			'incoming_hook',
			'webhook',
			sprintf( 'Incoming hook received: %s', $action ),
			array_merge(
				array(
					'action'  => $action,
					'ability' => $request->get_param( 'ability' ),
					'task'    => $request->get_param( 'task' ),
				),
				array_filter( array( 'trace_id' => $trace_id ) )
			)
		);

		switch ( $action ) {
			case 'ping':
				return new \WP_REST_Response(
					array(
						'status'  => 'ok',
						'version' => WP_PINCH_VERSION,
						'time'    => gmdate( 'c' ),
					),
					200
				);

			case 'execute_ability':
				$ability_name = $request->get_param( 'ability' );
				$params       = $request->get_param( 'params' );
				if ( empty( $ability_name ) ) {
					return new \WP_Error(
						'missing_ability',
						__( 'The "ability" parameter is required for execute_ability action.', 'wp-pinch' ),
						array( 'status' => 400 )
					);
				}
				$ability_names = Abilities::get_ability_names();
				if ( ! in_array( $ability_name, $ability_names, true ) ) {
					return new \WP_Error(
						'unknown_ability',
						/* translators: %s: ability name */
						sprintf( __( 'Unknown ability: %s', 'wp-pinch' ), $ability_name ),
						array( 'status' => 404 )
					);
				}
				$disabled = get_option( 'wp_pinch_disabled_abilities', array() );
				if ( in_array( $ability_name, $disabled, true ) ) {
					return new \WP_Error(
						'ability_disabled',
						/* translators: %s: ability name */
						sprintf( __( 'Ability "%s" is currently disabled.', 'wp-pinch' ), $ability_name ),
						array( 'status' => 403 )
					);
				}
				if ( Approval_Queue::requires_approval( $ability_name ) ) {
					$item_id = Approval_Queue::queue(
						$ability_name,
						is_array( $params ) ? $params : array(),
						$trace_id
					);
					Audit_Table::insert(
						'ability_queued',
						'incoming_hook',
						sprintf( 'Ability "%s" queued for approval (id: %s).', $ability_name, $item_id ),
						array(
							'ability'  => $ability_name,
							'queue_id' => $item_id,
						)
					);
					return new \WP_REST_Response(
						array(
							'status'   => 'queued',
							'message'  => __( 'Ability queued for approval. An administrator must approve it in WP Pinch → Approvals.', 'wp-pinch' ),
							'queue_id' => $item_id,
						),
						202
					);
				}
				if ( Write_Budget::is_write_ability( $ability_name ) ) {
					$budget_error = Write_Budget::check_daily_write_budget();
					if ( $budget_error instanceof \WP_Error ) {
						return new \WP_REST_Response(
							array(
								'code'    => $budget_error->get_error_code(),
								'message' => $budget_error->get_error_message(),
							),
							429
						);
					}
				}
				if ( ! function_exists( 'wp_execute_ability' ) ) {
					return new \WP_Error(
						'abilities_unavailable',
						__( 'WordPress Abilities API is not available.', 'wp-pinch' ),
						array( 'status' => 500 )
					);
				}
				$previous_user  = get_current_user_id();
				$execution_user = OpenClaw_Role::get_execution_user_id();
				if ( 0 === $execution_user ) {
					return new \WP_Error(
						'no_execution_user',
						__( 'No user found to execute the ability. Create an OpenClaw agent user or ensure an administrator exists.', 'wp-pinch' ),
						array( 'status' => 500 )
					);
				}
				wp_set_current_user( $execution_user );
				Webhook_Dispatcher::set_skip_webhooks_this_request( true );
				try {
					$result = wp_execute_ability( $ability_name, is_array( $params ) ? $params : array() );
				} finally {
					Webhook_Dispatcher::set_skip_webhooks_this_request( false );
				}
				wp_set_current_user( $previous_user );
				if ( is_wp_error( $result ) ) {
					return new \WP_Error(
						'ability_error',
						$result->get_error_message(),
						array( 'status' => 422 )
					);
				}
				if ( Write_Budget::is_write_ability( $ability_name ) ) {
					Write_Budget::increment_daily_write_count();
					Write_Budget::maybe_send_daily_write_alert();
				}
				$trace_id        = Rest_Controller::get_trace_id();
				$request_summary = Helpers::sanitize_audit_params( is_array( $params ) ? $params : array() );
				$result_summary  = Helpers::sanitize_audit_result( $result );
				Audit_Table::insert(
					'ability_executed',
					'incoming_hook',
					sprintf( 'Ability "%s" executed via incoming hook.', $ability_name ),
					array_merge(
						array(
							'ability'         => $ability_name,
							'request_summary' => $request_summary,
							'result_summary'  => $result_summary,
						),
						array_filter( array( 'trace_id' => $trace_id ) )
					)
				);
				return new \WP_REST_Response(
					array(
						'status' => 'ok',
						'result' => $result,
					),
					200
				);

			case 'execute_batch':
				$batch = $request->get_param( 'batch' );
				if ( ! is_array( $batch ) || empty( $batch ) ) {
					return new \WP_Error(
						'validation_error',
						__( 'The "batch" parameter must be a non-empty array of { ability, params }.', 'wp-pinch' ),
						array( 'status' => 400 )
					);
				}
				foreach ( $batch as $idx => $item ) {
					if ( empty( $item['ability'] ) || ! is_string( $item['ability'] ) ) {
						return new \WP_Error(
							'invalid_batch_item',
							sprintf(
								/* translators: %d: batch item index (1-based) */
								__( 'Batch item %d must have a string "ability" field.', 'wp-pinch' ),
								$idx + 1
							),
							array( 'status' => 400 )
						);
					}
					if ( isset( $item['params'] ) && ! is_array( $item['params'] ) ) {
						return new \WP_Error(
							'invalid_batch_item',
							sprintf(
								/* translators: %d: batch item index (1-based) */
								__( 'Batch item %d "params" must be an array.', 'wp-pinch' ),
								$idx + 1
							),
							array( 'status' => 400 )
						);
					}
				}
				$execution_user = OpenClaw_Role::get_execution_user_id();
				if ( 0 === $execution_user ) {
					return new \WP_Error(
						'no_execution_user',
						__( 'No user found to run abilities. Create an OpenClaw agent user or ensure an administrator exists.', 'wp-pinch' ),
						array( 'status' => 503 )
					);
				}
				$previous_user = get_current_user_id();
				wp_set_current_user( $execution_user );
				Webhook_Dispatcher::set_skip_webhooks_this_request( true );
				$results = array();
				try {
					foreach ( $batch as $item ) {
						$ability_name = isset( $item['ability'] ) ? trim( (string) $item['ability'] ) : '';
						$params       = isset( $item['params'] ) && is_array( $item['params'] ) ? $item['params'] : array();
						if ( '' === $ability_name ) {
							$results[] = array(
								'success' => false,
								'error'   => __( 'Missing ability name.', 'wp-pinch' ),
							);
							continue;
						}
						if ( ! function_exists( 'wp_execute_ability' ) ) {
							$results[] = array(
								'success' => false,
								'error'   => __( 'Abilities API not available.', 'wp-pinch' ),
							);
							break;
						}
						if ( Write_Budget::is_write_ability( $ability_name ) ) {
							$budget_error = Write_Budget::check_daily_write_budget();
							if ( $budget_error instanceof \WP_Error ) {
								return new \WP_REST_Response(
									array(
										'code'    => $budget_error->get_error_code(),
										'message' => $budget_error->get_error_message(),
										'partial' => $results,
									),
									429
								);
							}
						}
						$result = wp_execute_ability( $ability_name, $params );
						if ( is_wp_error( $result ) ) {
							$results[] = array(
								'success' => false,
								'error'   => $result->get_error_message(),
								'code'    => $result->get_error_code(),
							);
						} else {
							if ( Write_Budget::is_write_ability( $ability_name ) ) {
								Write_Budget::increment_daily_write_count();
								Write_Budget::maybe_send_daily_write_alert();
							}
							$results[] = array(
								'success' => true,
								'result'  => $result,
							);
						}
					}
				} finally {
					Webhook_Dispatcher::set_skip_webhooks_this_request( false );
				}
				wp_set_current_user( $previous_user );
				$trace_id = Rest_Controller::get_trace_id();
				Audit_Table::insert(
					'batch_executed',
					'incoming_hook',
					sprintf( 'Batch of %d ability calls executed via incoming hook.', count( $batch ) ),
					array_merge( array( 'count' => count( $results ) ), array_filter( array( 'trace_id' => $trace_id ) ) )
				);
				return new \WP_REST_Response(
					array(
						'status'  => 'ok',
						'results' => $results,
					),
					200
				);

			case 'run_governance':
				$task = $request->get_param( 'task' );
				if ( empty( $task ) ) {
					return new \WP_Error(
						'missing_task',
						__( 'The "task" parameter is required for run_governance action.', 'wp-pinch' ),
						array( 'status' => 400 )
					);
				}
				$available_tasks = Governance::get_available_tasks();
				if ( ! array_key_exists( $task, $available_tasks ) ) {
					return new \WP_Error(
						'unknown_task',
						/* translators: %s: governance task name */
						sprintf( __( 'Unknown governance task: %s', 'wp-pinch' ), $task ),
						array( 'status' => 404 )
					);
				}
				$method = 'task_' . $task;
				if ( ! method_exists( Governance::class, $method ) ) {
					return new \WP_Error(
						'task_unavailable',
						__( 'Governance task method is not available.', 'wp-pinch' ),
						array( 'status' => 500 )
					);
				}
				Governance::$method();
				$trace_id = Rest_Controller::get_trace_id();
				Audit_Table::insert(
					'governance_triggered',
					'incoming_hook',
					sprintf( 'Governance task "%s" triggered via incoming hook.', $task ),
					array_merge( array( 'task' => $task ), array_filter( array( 'trace_id' => $trace_id ) ) )
				);
				return new \WP_REST_Response(
					array(
						'status'  => 'ok',
						'task'    => $task,
						/* translators: %s: governance task name */
						'message' => sprintf( __( 'Governance task "%s" executed.', 'wp-pinch' ), $task ),
					),
					200
				);

			default:
				return new \WP_Error(
					'unknown_action',
					/* translators: %s: action name */
					sprintf( __( 'Unknown action: %s', 'wp-pinch' ), $action ),
					array( 'status' => 400 )
				);
		}
	}
}
