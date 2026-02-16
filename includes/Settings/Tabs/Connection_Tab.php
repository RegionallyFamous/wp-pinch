<?php
/**
 * Connection tab — gateway, API token, webhook defaults, chat, PinchDrop.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings\Tabs;

defined( 'ABSPATH' ) || exit;

/**
 * Connection tab.
 */
class Connection_Tab {

	/**
	 * Render the tab content.
	 */
	public static function render(): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wp_pinch_connection' ); ?>

			<div class="wp-pinch-card">
				<h3 class="wp-pinch-card__title"><?php esc_html_e( 'Gateway & API — where the claws connect', 'wp-pinch' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wp_pinch_gateway_url"><?php esc_html_e( 'OpenClaw Gateway URL', 'wp-pinch' ); ?></label>
						</th>
						<td>
							<input type="url" id="wp_pinch_gateway_url" name="wp_pinch_gateway_url"
									value="<?php echo esc_attr( get_option( 'wp_pinch_gateway_url' ) ); ?>"
									class="regular-text" placeholder="http://127.0.0.1:3000" />
							<p class="description"><?php esc_html_e( 'The URL of your OpenClaw gateway. This is where we reach out and pinch.', 'wp-pinch' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_pinch_api_token"><?php esc_html_e( 'API Token', 'wp-pinch' ); ?></label>
						</th>
						<td>
						<?php $has_token = ! empty( \WP_Pinch\Settings::get_api_token() ); ?>
						<input type="password" id="wp_pinch_api_token" name="wp_pinch_api_token"
								value="<?php echo $has_token ? esc_attr( str_repeat( "\u{2022}", 8 ) ) : ''; ?>"
								class="regular-text" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Your secret handshake. Bearer token for webhook auth — keep it safe, we\'re territorial about it.', 'wp-pinch' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Safety controls (claws sheathed)', 'wp-pinch' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="wp_pinch_api_disabled" name="wp_pinch_api_disabled" value="1"
									<?php checked( (bool) get_option( 'wp_pinch_api_disabled', false ) ); ?> />
								<?php esc_html_e( 'Disable API access (hide in the kelp)', 'wp-pinch' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When checked, all REST endpoints return 503. Use during incidents — we\'ll keep our claws to ourselves.', 'wp-pinch' ); ?></p>
							<label style="display:block; margin-top:1em;">
								<input type="checkbox" id="wp_pinch_read_only_mode" name="wp_pinch_read_only_mode" value="1"
									<?php checked( (bool) get_option( 'wp_pinch_read_only_mode', false ) ); ?> />
								<?php esc_html_e( 'Read-only mode (look but don\'t pinch)', 'wp-pinch' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When checked, write abilities are blocked. We\'ll look around all you want, but no grabbing.', 'wp-pinch' ); ?></p>
							<label style="display:block; margin-top:1em;">
								<input type="checkbox" id="wp_pinch_gateway_reply_strict_sanitize" name="wp_pinch_gateway_reply_strict_sanitize" value="1"
									<?php checked( (bool) get_option( 'wp_pinch_gateway_reply_strict_sanitize', false ) ); ?> />
								<?php esc_html_e( 'Strict gateway reply sanitization', 'wp-pinch' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When checked, chat replies are stripped of HTML comments and instruction-like text, and iframe/object/embed/form are removed to reduce prompt-injection and XSS risk.', 'wp-pinch' ); ?></p>
							<?php if ( defined( 'WP_PINCH_DISABLED' ) && WP_PINCH_DISABLED ) : ?>
								<p class="description" style="color:#b32d2e;">
									<?php esc_html_e( 'Note: WP_PINCH_DISABLED is set in wp-config.php — API is disabled until that constant is removed.', 'wp-pinch' ); ?>
								</p>
							<?php endif; ?>
							<?php if ( defined( 'WP_PINCH_READ_ONLY' ) && WP_PINCH_READ_ONLY ) : ?>
								<p class="description" style="color:#b32d2e;">
									<?php esc_html_e( 'Note: WP_PINCH_READ_ONLY is set in wp-config.php — write operations are disabled until that constant is removed.', 'wp-pinch' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_pinch_rate_limit"><?php esc_html_e( 'Rate Limit', 'wp-pinch' ); ?></label>
						</th>
						<td>
							<input type="number" id="wp_pinch_rate_limit" name="wp_pinch_rate_limit"
									value="<?php echo esc_attr( get_option( 'wp_pinch_rate_limit', 30 ) ); ?>"
									class="small-text" min="1" max="1000" />
							<span><?php esc_html_e( 'webhooks per minute', 'wp-pinch' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_pinch_daily_write_cap"><?php esc_html_e( 'Daily write budget', 'wp-pinch' ); ?></label>
						</th>
						<td>
							<input type="number" id="wp_pinch_daily_write_cap" name="wp_pinch_daily_write_cap"
									value="<?php echo esc_attr( get_option( 'wp_pinch_daily_write_cap', 0 ) ); ?>"
									class="small-text" min="0" max="10000" />
							<span><?php esc_html_e( 'max write operations per day (0 = no limit)', 'wp-pinch' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Write operations: create/update/delete posts, media, options, etc. When exceeded, requests return 429 until the next day.', 'wp-pinch' ); ?>
							</p>
							<p class="description" style="margin-top:0.5em;">
								<label for="wp_pinch_daily_write_alert_threshold"><?php esc_html_e( 'Alert email when usage reaches', 'wp-pinch' ); ?></label>
								<input type="number" id="wp_pinch_daily_write_alert_threshold" name="wp_pinch_daily_write_alert_threshold"
										value="<?php echo esc_attr( get_option( 'wp_pinch_daily_write_alert_threshold', 80 ) ); ?>"
										class="tiny-text" min="1" max="100" /> %
								<label for="wp_pinch_daily_write_alert_email"><?php esc_html_e( '— Email:', 'wp-pinch' ); ?></label>
								<input type="email" id="wp_pinch_daily_write_alert_email" name="wp_pinch_daily_write_alert_email"
										value="<?php echo esc_attr( get_option( 'wp_pinch_daily_write_alert_email', '' ) ); ?>"
										class="regular-text" placeholder="<?php esc_attr_e( 'admin@example.com', 'wp-pinch' ); ?>" />
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_pinch_agent_id"><?php esc_html_e( 'Agent ID', 'wp-pinch' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="wp_pinch_agent_id"
								name="wp_pinch_agent_id"
								value="<?php echo esc_attr( get_option( 'wp_pinch_agent_id', '' ) ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. hooks or main', 'wp-pinch' ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Optional. Route webhooks and chat to a specific OpenClaw agent. Leave blank for default agent.', 'wp-pinch' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Public chat & stream limits', 'wp-pinch' ); ?></th>
						<td>
							<p class="description" style="margin-top:0;">
								<label for="wp_pinch_public_chat_rate_limit"><?php esc_html_e( 'Public chat (unauthenticated) rate limit:', 'wp-pinch' ); ?></label>
								<input type="number" id="wp_pinch_public_chat_rate_limit" name="wp_pinch_public_chat_rate_limit"
										value="<?php echo esc_attr( get_option( 'wp_pinch_public_chat_rate_limit', 3 ) ); ?>"
										class="tiny-text" min="1" max="60" /> <?php esc_html_e( 'requests per minute per IP', 'wp-pinch' ); ?>
							</p>
							<p class="description" style="margin-top:0.5em;">
								<label for="wp_pinch_sse_max_connections_per_ip"><?php esc_html_e( 'Max concurrent SSE streams per IP:', 'wp-pinch' ); ?></label>
								<input type="number" id="wp_pinch_sse_max_connections_per_ip" name="wp_pinch_sse_max_connections_per_ip"
										value="<?php echo esc_attr( get_option( 'wp_pinch_sse_max_connections_per_ip', 5 ) ); ?>"
										class="tiny-text" min="0" max="20" /> (0 = <?php esc_html_e( 'no limit', 'wp-pinch' ); ?>)
							</p>
							<p class="description" style="margin-top:0.5em;">
								<label for="wp_pinch_chat_max_response_length"><?php esc_html_e( 'Max chat response length (chars):', 'wp-pinch' ); ?></label>
								<input type="number" id="wp_pinch_chat_max_response_length" name="wp_pinch_chat_max_response_length"
										value="<?php echo esc_attr( get_option( 'wp_pinch_chat_max_response_length', 200000 ) ); ?>"
										class="small-text" min="0" /> (0 = <?php esc_html_e( 'no limit', 'wp-pinch' ); ?>)
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ability cache', 'wp-pinch' ); ?></th>
						<td>
							<p class="description" style="margin-top:0;">
								<label for="wp_pinch_ability_cache_ttl"><?php esc_html_e( 'Cache TTL for read-heavy abilities (seconds):', 'wp-pinch' ); ?></label>
								<input type="number" id="wp_pinch_ability_cache_ttl" name="wp_pinch_ability_cache_ttl"
										value="<?php echo esc_attr( get_option( 'wp_pinch_ability_cache_ttl', 300 ) ); ?>"
										class="small-text" min="0" max="86400" /> (0 = <?php esc_html_e( 'disabled', 'wp-pinch' ); ?>)
							</p>
							<p class="description"><?php esc_html_e( 'list-posts, search-content, list-media, list-taxonomies. Invalidated on post save/delete.', 'wp-pinch' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<?php \WP_Pinch\Settings::render_openclaw_role_section(); ?>

			<div class="wp-pinch-card">
				<h3 class="wp-pinch-card__title"><?php esc_html_e( 'Webhook defaults', 'wp-pinch' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wp_pinch_webhook_channel"><?php esc_html_e( 'Delivery Channel', 'wp-pinch' ); ?></label>
						</th>
					<td>
						<select id="wp_pinch_webhook_channel" name="wp_pinch_webhook_channel">
							<option value="" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), '' ); ?>><?php esc_html_e( 'None (agent only)', 'wp-pinch' ); ?></option>
							<option value="last" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'last' ); ?>><?php esc_html_e( 'Last active channel', 'wp-pinch' ); ?></option>
							<option value="whatsapp" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'whatsapp' ); ?>>WhatsApp</option>
							<option value="telegram" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'telegram' ); ?>>Telegram</option>
							<option value="discord" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'discord' ); ?>>Discord</option>
							<option value="slack" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'slack' ); ?>>Slack</option>
							<option value="signal" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'signal' ); ?>>Signal</option>
							<option value="imessage" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'imessage' ); ?>>iMessage</option>
							<option value="msteams" <?php selected( get_option( 'wp_pinch_webhook_channel', '' ), 'msteams' ); ?>>Microsoft Teams</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Optional. Deliver webhook responses to a messaging channel.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_webhook_to"><?php esc_html_e( 'Recipient', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="wp_pinch_webhook_to"
							name="wp_pinch_webhook_to"
							value="<?php echo esc_attr( get_option( 'wp_pinch_webhook_to', '' ) ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. +15551234567 or channel ID', 'wp-pinch' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Optional. Recipient identifier for the delivery channel (phone number, chat ID, etc.).', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Deliver Responses', 'wp-pinch' ); ?>
					</th>
					<td>
						<label for="wp_pinch_webhook_deliver">
							<input
								type="checkbox"
								id="wp_pinch_webhook_deliver"
								name="wp_pinch_webhook_deliver"
								value="1"
								<?php checked( get_option( 'wp_pinch_webhook_deliver', true ) ); ?>
							/>
							<?php esc_html_e( 'Send agent responses to the delivery channel', 'wp-pinch' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, OpenClaw will deliver webhook responses to the configured messaging channel.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_webhook_model"><?php esc_html_e( 'Webhook Model', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="wp_pinch_webhook_model"
							name="wp_pinch_webhook_model"
							value="<?php echo esc_attr( get_option( 'wp_pinch_webhook_model', '' ) ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. openai/gpt-5.2-mini', 'wp-pinch' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Override which model processes webhook events. Leave empty for the agent default.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_webhook_thinking"><?php esc_html_e( 'Thinking Level', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<select id="wp_pinch_webhook_thinking" name="wp_pinch_webhook_thinking">
							<option value="" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), '' ); ?>><?php esc_html_e( 'Default', 'wp-pinch' ); ?></option>
							<option value="off" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), 'off' ); ?>><?php esc_html_e( 'Off', 'wp-pinch' ); ?></option>
							<option value="low" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), 'low' ); ?>><?php esc_html_e( 'Low', 'wp-pinch' ); ?></option>
							<option value="medium" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), 'medium' ); ?>><?php esc_html_e( 'Medium', 'wp-pinch' ); ?></option>
							<option value="high" <?php selected( get_option( 'wp_pinch_webhook_thinking', '' ), 'high' ); ?>><?php esc_html_e( 'High', 'wp-pinch' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Control the thinking level for webhook-triggered agent turns.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_webhook_timeout"><?php esc_html_e( 'Timeout (seconds)', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="wp_pinch_webhook_timeout"
							name="wp_pinch_webhook_timeout"
							value="<?php echo esc_attr( get_option( 'wp_pinch_webhook_timeout', 0 ) ); ?>"
							class="small-text"
							min="0"
							max="600"
							placeholder="0"
						/>
						<p class="description">
							<?php esc_html_e( 'Maximum seconds for webhook agent runs (0 = no limit).', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				</table>
			</div>

			<div class="wp-pinch-card">
				<h3 class="wp-pinch-card__title"><?php esc_html_e( 'Chat Settings', 'wp-pinch' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Configure how the chat block behaves — model, thinking level, placeholder, and more.', 'wp-pinch' ); ?></p>
				<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wp_pinch_chat_model"><?php esc_html_e( 'Chat Model', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input type="text" id="wp_pinch_chat_model" name="wp_pinch_chat_model"
								value="<?php echo esc_attr( get_option( 'wp_pinch_chat_model', '' ) ); ?>"
								class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'e.g. anthropic/claude-sonnet-4-5 — leave empty for gateway default.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_chat_thinking"><?php esc_html_e( 'Chat Thinking Level', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<select id="wp_pinch_chat_thinking" name="wp_pinch_chat_thinking">
							<?php
							$current_thinking = get_option( 'wp_pinch_chat_thinking', '' );
							$thinking_options = array(
								''       => __( 'Default (gateway decides)', 'wp-pinch' ),
								'off'    => __( 'Off', 'wp-pinch' ),
								'low'    => __( 'Low', 'wp-pinch' ),
								'medium' => __( 'Medium', 'wp-pinch' ),
								'high'   => __( 'High', 'wp-pinch' ),
							);
							foreach ( $thinking_options as $val => $label ) :
								?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_thinking, $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_chat_timeout"><?php esc_html_e( 'Chat Timeout (seconds)', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input type="number" id="wp_pinch_chat_timeout" name="wp_pinch_chat_timeout"
								value="<?php echo esc_attr( get_option( 'wp_pinch_chat_timeout', 0 ) ); ?>"
								min="0" max="600" step="1" class="small-text" />
						<p class="description">
							<?php esc_html_e( '0 = gateway default. Maximum 600 seconds.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_chat_placeholder"><?php esc_html_e( 'Default Chat Placeholder', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input type="text" id="wp_pinch_chat_placeholder" name="wp_pinch_chat_placeholder"
								value="<?php echo esc_attr( get_option( 'wp_pinch_chat_placeholder', '' ) ); ?>"
								class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Default placeholder when the block hasn\'t been customized. Leave empty for our lobster-y default. Can be overridden per post via Block Bindings.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_session_idle_minutes"><?php esc_html_e( 'Session Idle Timeout (minutes)', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input type="number" id="wp_pinch_session_idle_minutes" name="wp_pinch_session_idle_minutes"
								value="<?php echo esc_attr( get_option( 'wp_pinch_session_idle_minutes', 0 ) ); ?>"
								min="0" max="1440" step="1" class="small-text" />
						<p class="description">
							<?php esc_html_e( '0 = gateway default. After this many idle minutes, a new session starts.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_capture_token"><?php esc_html_e( 'Web Clipper capture token', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<?php $has_capture_token = ! empty( get_option( 'wp_pinch_capture_token' ) ); ?>
						<input
							type="password"
							id="wp_pinch_capture_token"
							name="wp_pinch_capture_token"
							value="<?php echo $has_capture_token ? esc_attr( str_repeat( "\u{2022}", 8 ) ) : ''; ?>"
							class="regular-text"
							autocomplete="off"
						/>
						<p class="description">
							<?php esc_html_e( 'Optional. Long-lived secret token for the Web Clipper / bookmarklet capture endpoint. If set, one-shot captures from the browser use this token (query param or X-WP-Pinch-Capture-Token header). Keep it secret; the URL may contain the token.', 'wp-pinch' ); ?>
						</p>
					</td>
				</tr>
				</table>
			</div>

			<div class="wp-pinch-card">
				<h3 class="wp-pinch-card__title"><?php esc_html_e( 'PinchDrop (Capture Anywhere)', 'wp-pinch' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Capture ideas from OpenClaw channels and auto-generate draft packs.', 'wp-pinch' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable PinchDrop', 'wp-pinch' ); ?></th>
					<td>
						<label for="wp_pinch_pinchdrop_enabled">
							<input type="checkbox" id="wp_pinch_pinchdrop_enabled" name="wp_pinch_pinchdrop_enabled" value="1"
								<?php checked( (bool) get_option( 'wp_pinch_pinchdrop_enabled', false ) ); ?> />
							<?php esc_html_e( 'Accept capture requests on /wp-pinch/v1/pinchdrop/capture', 'wp-pinch' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default output types', 'wp-pinch' ); ?></th>
					<td>
						<?php $pd_outputs = (array) get_option( 'wp_pinch_pinchdrop_default_outputs', array( 'post', 'product_update', 'changelog', 'social' ) ); ?>
						<label><input type="checkbox" name="wp_pinch_pinchdrop_default_outputs[]" value="post" <?php checked( in_array( 'post', $pd_outputs, true ) ); ?> /> <?php esc_html_e( 'Blog post', 'wp-pinch' ); ?></label><br />
						<label><input type="checkbox" name="wp_pinch_pinchdrop_default_outputs[]" value="product_update" <?php checked( in_array( 'product_update', $pd_outputs, true ) ); ?> /> <?php esc_html_e( 'Product update', 'wp-pinch' ); ?></label><br />
						<label><input type="checkbox" name="wp_pinch_pinchdrop_default_outputs[]" value="changelog" <?php checked( in_array( 'changelog', $pd_outputs, true ) ); ?> /> <?php esc_html_e( 'Changelog', 'wp-pinch' ); ?></label><br />
						<label><input type="checkbox" name="wp_pinch_pinchdrop_default_outputs[]" value="social" <?php checked( in_array( 'social', $pd_outputs, true ) ); ?> /> <?php esc_html_e( 'Social snippets', 'wp-pinch' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-save generated drafts', 'wp-pinch' ); ?></th>
					<td>
						<label for="wp_pinch_pinchdrop_auto_save_drafts">
							<input type="checkbox" id="wp_pinch_pinchdrop_auto_save_drafts" name="wp_pinch_pinchdrop_auto_save_drafts" value="1"
								<?php checked( (bool) get_option( 'wp_pinch_pinchdrop_auto_save_drafts', true ) ); ?> />
							<?php esc_html_e( 'Create draft posts automatically from generated output', 'wp-pinch' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp_pinch_pinchdrop_allowed_sources"><?php esc_html_e( 'Allowed capture sources', 'wp-pinch' ); ?></label>
					</th>
					<td>
						<input type="text" id="wp_pinch_pinchdrop_allowed_sources" name="wp_pinch_pinchdrop_allowed_sources"
							value="<?php echo esc_attr( get_option( 'wp_pinch_pinchdrop_allowed_sources', '' ) ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'slack,telegram,whatsapp', 'wp-pinch' ); ?>" />
						<p class="description"><?php esc_html_e( 'Optional comma-separated source allowlist. Leave empty to allow all sources.', 'wp-pinch' ); ?></p>
					</td>
				</tr>
			</table>
			</div>

			<p>
				<button type="button" id="wp-pinch-test-connection" class="button button-secondary">
					<?php esc_html_e( 'Test Connection', 'wp-pinch' ); ?>
				</button>
				<span id="wp-pinch-connection-result"></span>
			</p>

			<?php submit_button(); ?>
		</form>

		<div class="wp-pinch-mcp-info">
			<h3><?php esc_html_e( 'MCP Server Endpoint', 'wp-pinch' ); ?></h3>
			<p><?php esc_html_e( 'Your WP Pinch MCP server is available at:', 'wp-pinch' ); ?></p>
			<code><?php echo esc_html( rest_url( 'wp-pinch/mcp' ) ); ?></code>
			<p class="description">
				<?php esc_html_e( 'Use this URL to connect OpenClaw (or any MCP client) via mcp-wordpress-remote.', 'wp-pinch' ); ?>
			</p>
		</div>

		<?php if ( \WP_Pinch\Settings::get_openclaw_skill_content() !== '' ) : ?>
		<div class="wp-pinch-skill-install">
			<h3><?php esc_html_e( 'Install OpenClaw Skill', 'wp-pinch' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Copy the WP Pinch skill so your OpenClaw agent knows when and how to use each ability (posts, PinchDrop, Molt, etc.). Save to ~/.openclaw/workspace/skills/wp-pinch/SKILL.md', 'wp-pinch' ); ?>
			</p>
			<div class="wp-pinch-copy-row">
				<button type="button" class="button" id="wp-pinch-copy-skill" aria-label="<?php esc_attr_e( 'Copy skill to clipboard', 'wp-pinch' ); ?>">
					<?php esc_html_e( 'Copy skill', 'wp-pinch' ); ?>
				</button>
				<span id="wp-pinch-skill-copy-feedback" class="wp-pinch-skill-copy-feedback" aria-live="polite"></span>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}
}
