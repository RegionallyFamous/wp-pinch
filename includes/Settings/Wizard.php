<?php
/**
 * Onboarding wizard — steps 1–3 and finish/skip handlers.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings;

use WP_Pinch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Wizard UI and redirect handlers.
 */
class Wizard {

	/**
	 * Handle "Finish wizard" link: set wizard completed and redirect to settings.
	 */
	public static function maybe_finish_wizard(): void {
		if ( ! isset( $_GET['wp_pinch_finish_wizard'] ) || '1' !== $_GET['wp_pinch_finish_wizard'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'wp_pinch_finish_wizard' ) ) {
			return;
		}
		update_option( 'wp_pinch_wizard_completed', true );
		wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch' ) );
		exit;
	}

	/**
	 * Handle "Skip setup" link: set wizard completed and redirect to settings.
	 */
	public static function maybe_skip_wizard(): void {
		if ( ! isset( $_GET['wp_pinch_skip_wizard'] ) || '1' !== $_GET['wp_pinch_skip_wizard'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'wp_pinch_skip_wizard' ) ) {
			return;
		}
		update_option( 'wp_pinch_wizard_completed', true );
		wp_safe_redirect( admin_url( 'admin.php?page=wp-pinch' ) );
		exit;
	}

	/**
	 * Render the onboarding wizard (steps 1–3).
	 *
	 * @param int $initial_step Which step to show initially (1, 2, or 3). Step 3 when gateway + token already saved.
	 */
	public static function render( int $initial_step = 1 ): void {
		$mcp_url    = rest_url( 'wp-pinch/v1/mcp' );
		$gateway    = get_option( 'wp_pinch_gateway_url', '' );
		$token      = Settings::get_api_token();
		$show_s1    = ( 1 === $initial_step ) ? 'block' : 'none';
		$show_s2    = ( 2 === $initial_step ) ? 'block' : 'none';
		$show_s3    = ( 3 === $initial_step ) ? 'block' : 'none';
		$finish_url = wp_nonce_url( admin_url( 'admin.php?page=wp-pinch&wp_pinch_finish_wizard=1' ), 'wp_pinch_finish_wizard' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Welcome to WP Pinch — your site\'s got claws', 'wp-pinch' ); ?></h1>

			<div class="wp-pinch-wizard" id="wp-pinch-wizard" aria-live="polite">
				<p class="wp-pinch-wizard__step-indicator" id="wp-pinch-wizard-step-label" aria-live="polite" aria-atomic="true">
					<?php
					/* translators: 1: current step number, 2: total steps */
					echo esc_html( sprintf( __( 'Step %1$d of %2$d', 'wp-pinch' ), (int) $initial_step, 3 ) );
					?>
				</p>

				<!-- Step 1: Welcome -->
				<div class="wp-pinch-wizard__step" data-step="1" id="wp-pinch-wizard-step-1" style="display: <?php echo esc_attr( $show_s1 ); ?>;">
					<div class="wp-pinch-wizard__card">
						<h2><?php esc_html_e( 'Connect WordPress to OpenClaw', 'wp-pinch' ); ?></h2>
						<p>
							<?php esc_html_e( 'WP Pinch bridges your WordPress site with OpenClaw, letting you manage your site from WhatsApp, Telegram, Slack, Discord, or any messaging platform OpenClaw supports.', 'wp-pinch' ); ?>
						</p>
						<h3><?php esc_html_e( 'What you\'ll need (no claws required):', 'wp-pinch' ); ?></h3>
						<ul class="wp-pinch-wizard-list">
							<li><?php esc_html_e( 'OpenClaw installed and running (local or remote)', 'wp-pinch' ); ?></li>
							<li><?php esc_html_e( 'Your OpenClaw gateway URL and API token', 'wp-pinch' ); ?></li>
						</ul>
						<p>
							<?php
							printf(
								/* translators: %s: link to OpenClaw install docs */
								esc_html__( 'Don\'t have OpenClaw yet? %s', 'wp-pinch' ),
								'<a href="https://docs.openclaw.ai/install" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Install it here', 'wp-pinch' ) . ' &rarr;</a>'
							);
							?>
						</p>
						<p class="wp-pinch-wizard-actions">
							<button type="button" class="button button-primary button-hero" data-wizard-action="go" data-wizard-to="2">
								<?php esc_html_e( 'Let\'s get pinching', 'wp-pinch' ); ?> &rarr;
							</button>
						</p>
						<p class="wp-pinch-wizard-skip">
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wp-pinch&wp_pinch_skip_wizard=1' ), 'wp_pinch_skip_wizard' ) ); ?>"><?php esc_html_e( 'I\'ll scuttle back later', 'wp-pinch' ); ?></a>
						</p>
					</div>
				</div>

				<!-- Step 2: Connect -->
				<div class="wp-pinch-wizard__step" data-step="2" id="wp-pinch-wizard-step-2" style="display: <?php echo esc_attr( $show_s2 ); ?>;">
					<div class="wp-pinch-wizard__card">
						<h2><?php esc_html_e( 'Configure Connection', 'wp-pinch' ); ?></h2>

						<form method="post" action="options.php" id="wp-pinch-wizard-form">
							<?php settings_fields( 'wp_pinch_connection' ); ?>

							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">
										<label for="wp_pinch_gateway_url"><?php esc_html_e( 'Gateway URL', 'wp-pinch' ); ?></label>
									</th>
									<td>
										<input
											type="url"
											id="wp_pinch_gateway_url"
											name="wp_pinch_gateway_url"
											value="<?php echo esc_attr( $gateway ); ?>"
											class="regular-text"
											placeholder="http://127.0.0.1:18789"
											required
										/>
										<p class="description">
											<?php esc_html_e( 'The URL of your OpenClaw gateway (usually http://127.0.0.1:18789 for local installs).', 'wp-pinch' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wp_pinch_api_token"><?php esc_html_e( 'API Token', 'wp-pinch' ); ?></label>
									</th>
									<td>
										<input
											type="password"
											id="wp_pinch_api_token"
											name="wp_pinch_api_token"
											value="<?php echo esc_attr( $token ); ?>"
											class="regular-text"
											required
										/>
										<p class="description">
											<?php esc_html_e( 'Your OpenClaw gateway token (OPENCLAW_GATEWAY_TOKEN).', 'wp-pinch' ); ?>
										</p>
									</td>
								</tr>
							</table>

							<div class="wp-pinch-wizard-test-row">
								<button type="button" class="button" id="wp-pinch-wizard-test" aria-busy="false" aria-live="polite">
									<?php esc_html_e( 'Test Connection', 'wp-pinch' ); ?>
								</button>
								<span id="wp-pinch-wizard-test-result" class="wp-pinch-wizard-test-result" aria-live="polite"></span>
							</div>

							<hr />

							<h3><?php esc_html_e( 'Your MCP Endpoint', 'wp-pinch' ); ?></h3>
							<p class="description">
								<?php esc_html_e( 'Use this URL to connect OpenClaw to your WordPress site via MCP:', 'wp-pinch' ); ?>
							</p>
							<div class="wp-pinch-copy-row">
								<code id="wp-pinch-mcp-url" class="wp-pinch-copy-code"><?php echo esc_html( $mcp_url ); ?></code>
								<button type="button" class="button wp-pinch-copy-btn" data-wizard-copy="wp-pinch-mcp-url" aria-label="<?php esc_attr_e( 'Copy MCP URL', 'wp-pinch' ); ?>"><?php esc_html_e( 'Copy', 'wp-pinch' ); ?></button>
								<span class="wp-pinch-copy-feedback" id="wp-pinch-copy-feedback-mcp" aria-live="polite"></span>
							</div>
							<p class="description">
								<?php esc_html_e( 'Or run this command in your OpenClaw CLI:', 'wp-pinch' ); ?>
							</p>
							<div class="wp-pinch-copy-row">
								<code id="wp-pinch-cli-cmd" class="wp-pinch-copy-code">npx openclaw connect --mcp-url <?php echo esc_html( $mcp_url ); ?></code>
								<button type="button" class="button wp-pinch-copy-btn" data-wizard-copy="wp-pinch-cli-cmd" aria-label="<?php esc_attr_e( 'Copy command', 'wp-pinch' ); ?>"><?php esc_html_e( 'Copy', 'wp-pinch' ); ?></button>
								<span class="wp-pinch-copy-feedback" id="wp-pinch-copy-feedback-cli" aria-live="polite"></span>
							</div>

							<p class="wp-pinch-wizard-actions wp-pinch-wizard-step-footer">
								<button type="button" class="button" data-wizard-action="go" data-wizard-to="1">
									&larr; <?php esc_html_e( 'Back', 'wp-pinch' ); ?>
								</button>
								<?php submit_button( __( 'Save & Continue', 'wp-pinch' ), 'primary', 'submit', false ); ?>
							</p>
						</form>
					</div>
				</div>

				<!-- Step 3: Time to pinch -->
				<div class="wp-pinch-wizard__step" data-step="3" id="wp-pinch-wizard-step-3" style="display: <?php echo esc_attr( $show_s3 ); ?>;">
					<div class="wp-pinch-wizard__card">
						<h2><?php esc_html_e( 'Time to pinch', 'wp-pinch' ); ?></h2>
						<p>
							<?php esc_html_e( 'Send a message from WhatsApp, Telegram, Slack, or Discord to your OpenClaw agent. Your agent can now pinch your WordPress site via the MCP endpoint below.', 'wp-pinch' ); ?>
						</p>
						<?php if ( Settings::get_openclaw_skill_content() !== '' ) : ?>
						<h3><?php esc_html_e( 'Install the WP Pinch skill', 'wp-pinch' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'Copy the skill so your agent knows when and how to use each ability. Save to ~/.openclaw/workspace/skills/wp-pinch/SKILL.md', 'wp-pinch' ); ?>
						</p>
						<div class="wp-pinch-copy-row">
							<button type="button" class="button wp-pinch-copy-btn" data-wizard-copy="wp-pinch-skill-content" aria-label="<?php esc_attr_e( 'Copy skill to clipboard', 'wp-pinch' ); ?>"><?php esc_html_e( 'Copy skill', 'wp-pinch' ); ?></button>
							<span class="wp-pinch-copy-feedback" id="wp-pinch-wizard-skill-feedback" aria-live="polite"></span>
						</div>
						<code id="wp-pinch-skill-content" class="wp-pinch-copy-code" style="display:none;"><?php echo esc_html( Settings::get_openclaw_skill_content() ); ?></code>
						<?php endif; ?>
						<p><strong><?php esc_html_e( 'Try this first', 'wp-pinch' ); ?></strong></p>
						<ul class="wp-pinch-wizard-list" style="margin-top: 0;">
							<li><?php esc_html_e( 'PinchDrop: Send a sentence to your channel and ask the assistant to turn it into a draft pack.', 'wp-pinch' ); ?></li>
							<li><?php esc_html_e( 'Molt: Say "Molt 123" (or "Turn post 123 into social and meta") to get multiple formats from one post.', 'wp-pinch' ); ?></li>
						</ul>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Recipes wiki */
								esc_html__( 'Step-by-step flows: %s', 'wp-pinch' ),
								'<a href="https://github.com/RegionallyFamous/wp-pinch/wiki/Recipes" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Recipes', 'wp-pinch' ) . ' &rarr;</a>'
							);
							?>
						</p>
						<div class="wp-pinch-copy-row">
							<code class="wp-pinch-copy-code"><?php echo esc_html( $mcp_url ); ?></code>
						</div>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to OpenClaw docs */
								esc_html__( 'Need help connecting a channel? See the %s.', 'wp-pinch' ),
								'<a href="https://docs.openclaw.ai" target="_blank" rel="noopener noreferrer">' . esc_html__( 'OpenClaw docs', 'wp-pinch' ) . ' &rarr;</a>'
							);
							?>
						</p>
						<p class="wp-pinch-wizard-step-footer">
							<a href="<?php echo esc_url( $finish_url ); ?>" class="button button-primary button-hero"><?php esc_html_e( 'Enter the warren', 'wp-pinch' ); ?></a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
