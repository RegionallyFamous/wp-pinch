<?php
/**
 * Features tab — feature flags and circuit breaker status.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings\Tabs;

use WP_Pinch\Circuit_Breaker;
use WP_Pinch\Feature_Flags;

defined( 'ABSPATH' ) || exit;

/**
 * Features tab.
 */
class Features_Tab {

	/**
	 * Render the tab content.
	 */
	public static function render(): void {
		$flags  = Feature_Flags::get_all();
		$labels = array(
			'streaming_chat'     => __( 'Streaming Chat (SSE)', 'wp-pinch' ),
			'webhook_signatures' => __( 'HMAC-SHA256 Webhook Signatures', 'wp-pinch' ),
			'circuit_breaker'    => __( 'Circuit Breaker (claws up when the gateway goes down)', 'wp-pinch' ),
			'ability_toggle'     => __( 'Ability Toggle (disable individual abilities)', 'wp-pinch' ),
			'webhook_dashboard'  => __( 'Webhook Dashboard in Audit Log', 'wp-pinch' ),
			'audit_search'       => __( 'Audit Log Search & Filters', 'wp-pinch' ),
			'health_endpoint'    => __( 'Public Health Check Endpoint', 'wp-pinch' ),
			'slash_commands'     => __( 'Slash commands (/new, /status) in chat', 'wp-pinch' ),
			'token_display'      => __( 'Show token usage in chat footer', 'wp-pinch' ),
			'pinchdrop_engine'   => __( 'PinchDrop engine (capture anywhere draft packs)', 'wp-pinch' ),
			'ghost_writer'       => __( 'Ghost Writer (draft completion in author voice)', 'wp-pinch' ),
			'molt'               => __( 'Molt (content repackager)', 'wp-pinch' ),
			'prompt_sanitizer'   => __( 'Prompt sanitizer (mitigate instruction injection in content sent to LLMs)', 'wp-pinch' ),
			'approval_workflow'  => __( 'Approval workflow (queue destructive abilities for admin approval)', 'wp-pinch' ),
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wp_pinch_features' ); ?>

			<h3><?php esc_html_e( 'Feature Flags', 'wp-pinch' ); ?></h3>
			<p><?php esc_html_e( 'Flip the switches. Enable what you need — we take our features as seriously as a lobster takes its territory.', 'wp-pinch' ); ?></p>

			<table class="form-table">
				<?php foreach ( $labels as $flag => $label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wp_pinch_feature_flags[<?php echo esc_attr( $flag ); ?>]"
									value="1"
									<?php checked( $flags[ $flag ] ?? false ); ?> />
								<?php esc_html_e( 'Enabled', 'wp-pinch' ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<p class="description">
				<?php esc_html_e( 'Feature flags can also be overridden via the wp_pinch_feature_flag filter in code.', 'wp-pinch' ); ?>
			</p>

			<?php submit_button(); ?>
		</form>

		<div class="wp-pinch-circuit-status">
			<h3><?php esc_html_e( 'Circuit Breaker Status', 'wp-pinch' ); ?></h3>
			<?php
			$state       = Circuit_Breaker::get_state();
			$retry_after = Circuit_Breaker::get_retry_after();
			$state_label = array(
				'closed'    => __( 'Closed (normal)', 'wp-pinch' ),
				'open'      => __( 'Claws up (failing fast)', 'wp-pinch' ),
				'half_open' => __( 'Half-Open (poking a claw out)', 'wp-pinch' ),
			);
			?>
			<p>
				<?php
				printf(
					/* translators: %s: circuit state label */
					esc_html__( 'State: %s', 'wp-pinch' ),
					'<strong>' . esc_html( $state_label[ $state ] ?? $state ) . '</strong>'
				);
				?>
			</p>
			<?php if ( $retry_after > 0 ) : ?>
				<p>
					<?php
					printf(
						/* translators: %d: seconds until retry */
						esc_html__( 'Retry in %d seconds.', 'wp-pinch' ),
						absint( $retry_after )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
