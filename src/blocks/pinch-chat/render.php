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

$placeholder = esc_attr( $attributes['placeholder'] ?? __( 'Ask your AI assistant anything about this site...', 'wp-pinch' ) );
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
$can_chat = is_user_logged_in() && current_user_can( 'edit_posts' );
wp_interactivity_state(
	'wp-pinch/chat',
	array(
		'messages'    => array(),
		'inputValue'  => '',
		'isLoading'   => false,
		'isConnected' => $can_chat,
		'restUrl'     => $can_chat ? rest_url( 'wp-pinch/v1/chat' ) : '',
		'nonce'       => $can_chat ? wp_create_nonce( 'wp_rest' ) : '',
		'sessionKey'  => $can_chat ? 'wp-pinch-chat-' . get_current_user_id() : '',
		'blockId'     => $unique_id,
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wp-pinch-chat' ) ); ?>
	data-wp-interactive="wp-pinch/chat"
	data-wp-init="callbacks.init"
	<?php echo wp_interactivity_data_wp_context( array( 'id' => $unique_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
	<?php endif; ?>

	<div
		class="wp-pinch-chat__messages"
		role="log"
		aria-live="polite"
		aria-label="<?php esc_attr_e( 'Chat messages', 'wp-pinch' ); ?>"
		style="max-height: <?php echo esc_attr( $max_height ); ?>;"
		data-wp-each="state.messages"
		data-wp-each-key="context.item.id"
	>
		<template data-wp-each-child>
			<div
				class="wp-pinch-chat__message"
				data-wp-class--wp-pinch-chat__message--user="context.item.isUser"
				data-wp-class--wp-pinch-chat__message--agent="!context.item.isUser"
				data-wp-text="context.item.text"
			></div>
		</template>
	</div>

	<?php if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) : ?>
		<div class="wp-pinch-chat__input-area">
			<label for="<?php echo esc_attr( $unique_id ); ?>-input" class="screen-reader-text">
				<?php esc_html_e( 'Type a message', 'wp-pinch' ); ?>
			</label>
			<input
				id="<?php echo esc_attr( $unique_id ); ?>-input"
				type="text"
				class="wp-pinch-chat__input"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
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
	<?php else : ?>
		<div class="wp-pinch-chat__login-notice">
			<?php esc_html_e( 'Please log in to use the chat.', 'wp-pinch' ); ?>
		</div>
	<?php endif; ?>
</div>
