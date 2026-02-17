<?php
/**
 * Server-side render for the Pinch Chat block.
 *
 * Uses WordPress Interactivity API directives for reactive frontend.
 *
 * @package WP_Pinch
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$placeholder = esc_attr( $attributes['placeholder'] ?? __( 'Pinch in a question — what do you want to know about this site?', 'wp-pinch' ) );
$show_header = $attributes['showHeader'] ?? true;
// Validate max-height as a safe CSS dimension (number + unit only).
$raw_max_height = $attributes['maxHeight'] ?? '400px';
$max_height     = preg_match( '/^\d+(\.\d+)?(px|em|rem|vh|%)$/', $raw_max_height ) ? $raw_max_height : '400px';
// Use the stable blockId attribute (persisted in post content) for session storage keys.
// Fall back to wp_unique_id only for legacy blocks saved before 2.0.0.
$unique_id = ! empty( $attributes['blockId'] ) ? sanitize_key( $attributes['blockId'] ) : wp_unique_id( 'wp-pinch-chat-' );

// Enqueue wp-a11y on the frontend so screen reader announcements work.
if ( ! is_admin() ) {
	wp_enqueue_script( 'wp-a11y' );
}

// Set up Interactivity API initial state.
// Only expose credentials (nonce, REST URL, session key) to users who can actually use the chat.
$is_public_mode = ! empty( $attributes['publicMode'] ) && \WP_Pinch\Feature_Flags::is_enabled( 'public_chat' );
$can_chat       = ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) || $is_public_mode;
$chat_endpoint  = $is_public_mode && ! is_user_logged_in() ? 'wp-pinch/v1/chat/public' : 'wp-pinch/v1/chat';

$stream_url = '';
// Streaming requires edit_posts — only available to authenticated users, not public chat.
if ( class_exists( '\WP_Pinch\Feature_Flags' )
	&& \WP_Pinch\Feature_Flags::is_enabled( 'streaming_chat' )
	&& is_user_logged_in()
	&& current_user_can( 'edit_posts' )
) {
	$stream_url = rest_url( 'wp-pinch/v1/chat/stream' );
}

$block_agent_id    = $attributes['agentId'] ?? '';
$effective_agent   = '' !== $block_agent_id ? $block_agent_id : get_option( 'wp_pinch_agent_id', '' );
$chat_model        = get_option( 'wp_pinch_chat_model', '' );
$chat_thinking     = get_option( 'wp_pinch_chat_thinking', '' );
$slash_commands_on = \WP_Pinch\Feature_Flags::is_enabled( 'slash_commands' );
$token_display_on  = \WP_Pinch\Feature_Flags::is_enabled( 'token_display' );

wp_interactivity_state(
	'wp-pinch/chat',
	array(
		'messages'        => array(),
		'inputValue'      => '',
		'isLoading'       => false,
		'isConnected'     => $can_chat,
		'restUrl'         => $can_chat ? rest_url( $chat_endpoint ) : '',
		'nonce'           => ( $can_chat && is_user_logged_in() ) ? wp_create_nonce( 'wp_rest' ) : '',
		'sessionKey'      => $can_chat
			? ( is_user_logged_in()
				? 'wp-pinch-chat-' . get_current_user_id()
				: '' )
			: '',
		'blockId'         => $unique_id,
		'streamUrl'       => $stream_url,
		'agentId'         => $effective_agent,
		'model'           => $chat_model,
		'thinking'        => $chat_thinking,
		'canResetSession' => $can_chat,
		'sessionResetUrl' => $can_chat ? rest_url( 'wp-pinch/v1/session/reset' ) : '',
		'tokenUsage'      => null,
		'slashCommandsOn' => $slash_commands_on,
		'tokenDisplayOn'  => $token_display_on,
		'ghostWriterOn'   => \WP_Pinch\Feature_Flags::is_enabled( 'ghost_writer' ),
		'ghostWriteUrl'   => $can_chat ? rest_url( 'wp-pinch/v1/ghostwrite' ) : '',
		'moltOn'               => \WP_Pinch\Feature_Flags::is_enabled( 'molt' ),
		'moltUrl'              => $can_chat ? rest_url( 'wp-pinch/v1/molt' ) : '',
		'showScrollToBottom'   => false,
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wp-pinch-chat' ) ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block wrapper API returns safe markup. */ ?>
	data-wp-interactive="wp-pinch/chat"
	data-wp-init="callbacks.init"
	<?php echo wp_interactivity_data_wp_context( array( 'id' => $unique_id ) ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Interactivity API returns safe data attribute. */ ?>
	id="<?php echo esc_attr( $unique_id ); ?>"
>
	<?php if ( $show_header ) : ?>
		<div class="wp-pinch-chat__header">
			<span
				class="wp-pinch-chat__status-dot"
				data-wp-class--wp-pinch-chat__status-dot--connected="state.isConnected"
				data-wp-class--wp-pinch-chat__status-dot--loading="state.isLoading"
			></span>
			<span class="wp-pinch-chat__header-title">
				<?php esc_html_e( 'Pinch Chat', 'wp-pinch' ); ?>
			</span>
			<span
				class="wp-pinch-chat__typing"
				data-wp-show="state.isLoading"
				aria-hidden="true"
			>
				<?php esc_html_e( 'Thinking...', 'wp-pinch' ); ?>
			</span>
		</div>
		<?php if ( $can_chat ) : ?>
			<button
				class="wp-pinch-chat__new-session"
				data-wp-on--click="actions.newSession"
				aria-label="<?php esc_attr_e( 'New conversation', 'wp-pinch' ); ?>"
				title="<?php esc_attr_e( 'Start new conversation', 'wp-pinch' ); ?>"
			>+</button>
		<?php endif; ?>
	<?php endif; ?>

	<div
		class="wp-pinch-chat__messages-wrap"
		style="max-height: <?php echo esc_attr( $max_height ); ?>; position: relative;"
	>
		<button
			type="button"
			class="wp-pinch-chat__scroll-to-bottom"
			data-wp-show="state.showScrollToBottom"
			data-wp-on--click="actions.scrollToBottomAndHide"
			aria-label="<?php esc_attr_e( 'Scroll to bottom', 'wp-pinch' ); ?>"
			title="<?php esc_attr_e( 'New message — scroll to bottom', 'wp-pinch' ); ?>"
		>
			<span aria-hidden="true">&#8595;</span> <?php esc_html_e( 'Scroll to bottom', 'wp-pinch' ); ?>
		</button>
		<div
			class="wp-pinch-chat__messages"
			role="log"
			aria-live="polite"
			aria-label="<?php esc_attr_e( 'Chat messages', 'wp-pinch' ); ?>"
			style="max-height: <?php echo esc_attr( $max_height ); ?>; overflow-y: auto;"
			data-wp-each="state.messages"
			data-wp-each-key="context.item.id"
		>
		<template data-wp-each-child>
			<div
				class="wp-pinch-chat__message"
				data-wp-class--wp-pinch-chat__message--user="context.item.isUser"
				data-wp-class--wp-pinch-chat__message--agent="!context.item.isUser"
			>
				<span
					class="wp-pinch-chat__message-text"
					data-wp-text="context.item.text"
				></span>
				<button
					class="wp-pinch-chat__copy-btn"
					data-wp-on--click="actions.copyMessage"
					data-wp-show="!context.item.isUser"
					aria-label="<?php esc_attr_e( 'Copy message', 'wp-pinch' ); ?>"
					title="<?php esc_attr_e( 'Copy to clipboard', 'wp-pinch' ); ?>"
				>&#128203;</button>
			</div>
		</template>
		</div>
	</div>

	<!-- Typing indicator -->
	<div
		class="wp-pinch-chat__typing-indicator"
		data-wp-show="state.isLoading"
		aria-hidden="true"
	>
		<span class="wp-pinch-chat__typing-dot"></span>
		<span class="wp-pinch-chat__typing-dot"></span>
		<span class="wp-pinch-chat__typing-dot"></span>
	</div>

	<?php if ( $can_chat ) : ?>
		<div class="wp-pinch-chat__input-area">
			<label for="<?php echo esc_attr( $unique_id ); ?>-input" class="screen-reader-text">
				<?php esc_html_e( 'Type a message', 'wp-pinch' ); ?>
			</label>
			<input
				id="<?php echo esc_attr( $unique_id ); ?>-input"
				type="text"
				class="wp-pinch-chat__input"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				maxlength="4000"
				data-wp-bind--value="state.inputValue"
				data-wp-on--input="actions.updateInput"
				data-wp-on--keydown="actions.handleKeyDown"
				data-wp-bind--disabled="state.isLoading"
				autocomplete="off"
			/>
			<button
				class="wp-pinch-chat__send"
				data-wp-on--click="actions.sendMessage"
				data-wp-bind--disabled="state.isLoading"
				aria-label="<?php esc_attr_e( 'Send message', 'wp-pinch' ); ?>"
			>
				&#9654;
			</button>
		</div>

		<div class="wp-pinch-chat__footer">
			<span
				class="wp-pinch-chat__char-counter"
				data-wp-text="state.charsRemaining"
				data-wp-class--wp-pinch-chat__char-counter--warning="state.charsWarning"
				data-wp-class--wp-pinch-chat__char-counter--exceeded="state.charsExceeded"
				aria-live="polite"
				aria-atomic="true"
			></span>
			<button
				class="wp-pinch-chat__clear-btn"
				data-wp-on--click="actions.clearChat"
				data-wp-show="state.messageCount"
				aria-label="<?php esc_attr_e( 'Clear chat', 'wp-pinch' ); ?>"
				title="<?php esc_attr_e( 'Clear conversation', 'wp-pinch' ); ?>"
			>
				<?php esc_html_e( 'Clear', 'wp-pinch' ); ?>
			</button>
		</div>
	<?php else : ?>
		<div class="wp-pinch-chat__login-notice">
			<?php
			if ( ! empty( $attributes['publicMode'] ) && ! \WP_Pinch\Feature_Flags::is_enabled( 'public_chat' ) ) {
				esc_html_e( 'Public chat is not enabled. Enable the "public_chat" feature flag in WP Pinch settings.', 'wp-pinch' );
			} else {
				esc_html_e( 'Please log in to use the chat.', 'wp-pinch' );
			}
			?>
		</div>
	<?php endif; ?>
</div>
