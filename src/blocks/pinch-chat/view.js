/**
 * Pinch Chat — Frontend Interactivity API store.
 *
 * @package
 */

/* global sessionStorage, requestAnimationFrame */

import { store, getElement } from '@wordpress/interactivity';

let msgCounter = 0;

/**
 * Global error handler — catches unhandled errors in the chat block
 * and renders a friendly fallback instead of a blank widget.
 */
function handleChatError( error, context ) {
	// eslint-disable-next-line no-console
	console.error( '[WP Pinch Chat]', context, error );

	// Show a fallback message in all chat containers.
	const containers = document.querySelectorAll( '.wp-pinch-chat__messages' );
	containers.forEach( ( container ) => {
		if ( ! container.querySelector( '.wp-pinch-chat__error-boundary' ) ) {
			const fallback = document.createElement( 'div' );
			fallback.className =
				'wp-pinch-chat__message wp-pinch-chat__message--system wp-pinch-chat__error-boundary';
			fallback.textContent =
				'Something went wrong with the chat. Please reload the page to try again.';
			container.appendChild( fallback );
		}
	} );
}

const { state, actions } = store( 'wp-pinch/chat', {
	state: {
		get messageCount() {
			return state.messages.length;
		},
	},

	actions: {
		/**
		 * Update the input value from the text field.
		 *
		 * @param {Event} event Input event.
		 */
		updateInput( event ) {
			state.inputValue = event.target.value;
		},

		/**
		 * Handle keydown — send on Enter.
		 *
		 * @param {KeyboardEvent} event Keyboard event.
		 */
		handleKeyDown( event ) {
			if ( event.key === 'Enter' && ! event.shiftKey ) {
				event.preventDefault();
				actions.sendMessage();
			}
		},

		/**
		 * Send a chat message to the WP Pinch REST API.
		 */
		async sendMessage() {
			try {
				const text = state.inputValue.trim();
				if ( ! text || state.isLoading ) {
					return;
				}

				// Add user message.
				const userMsg = {
					id: 'msg-' + Date.now() + '-' + ++msgCounter,
					text,
					isUser: true,
					timestamp: new Date().toISOString(),
				};
				state.messages = [ ...state.messages, userMsg ];
				state.inputValue = '';
				state.isLoading = true;

				// Persist to session storage.
				actions.saveSession();

				try {
					const response = await fetch( state.restUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': state.nonce,
						},
						body: JSON.stringify( {
							message: text,
							session_key: state.sessionKey,
						} ),
					} );

					// Track rate limit info from response headers.
					const remaining =
						response.headers.get( 'X-RateLimit-Remaining' );
					if ( remaining !== null ) {
						state.rateLimitRemaining = parseInt( remaining, 10 );
					}

					let data;
					try {
						data = await response.json();
					} catch ( parseErr ) {
						throw new Error(
							'Server returned an invalid response.'
						);
					}

					if ( response.ok ) {
						const agentMsg = {
							id: 'msg-' + Date.now() + '-' + ++msgCounter,
							text:
								data.reply ||
								data.message ||
								'No response received.',
							isUser: false,
							timestamp: new Date().toISOString(),
						};
						state.messages = [ ...state.messages, agentMsg ];

						// Announce for screen readers.
						if ( window.wp?.a11y?.speak ) {
							window.wp.a11y.speak( agentMsg.text, 'polite' );
						}
					} else {
						const errorMsg = {
							id: 'msg-' + Date.now() + '-' + ++msgCounter,
							text: data.message || 'Something went wrong.',
							isUser: false,
							timestamp: new Date().toISOString(),
						};
						state.messages = [ ...state.messages, errorMsg ];
					}
				} catch ( err ) {
					const errorMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text: 'Network error. Please check your connection.',
						isUser: false,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, errorMsg ];
					state.isConnected = false;
				} finally {
					state.isLoading = false;
					actions.saveSession();
					actions.scrollToBottom();
					actions.focusInput();
				}
			} catch ( fatalErr ) {
				handleChatError( fatalErr, 'sendMessage' );
			}
		},

		/**
		 * Save messages to sessionStorage for persistence across page loads.
		 */
		saveSession() {
			try {
				const key = 'wp-pinch-chat-' + ( state.blockId || 'default' );
				sessionStorage.setItem( key, JSON.stringify( state.messages ) );
			} catch ( e ) {
				// Silent fail — sessionStorage may be full or unavailable.
			}
		},

		/**
		 * Restore messages from sessionStorage.
		 */
		restoreSession() {
			try {
				const key = 'wp-pinch-chat-' + ( state.blockId || 'default' );
				const saved = sessionStorage.getItem( key );
				if ( saved ) {
					const parsed = JSON.parse( saved );
					if ( Array.isArray( parsed ) ) {
						state.messages = parsed;
					}
				}
			} catch ( e ) {
				// Silent fail.
			}
		},

		/**
		 * Scroll the message container to the bottom.
		 */
		scrollToBottom() {
			requestAnimationFrame( () => {
				const el = getElement();
				const root = el?.ref?.closest( '.wp-pinch-chat' );
				if ( root ) {
					const container = root.querySelector(
						'.wp-pinch-chat__messages'
					);
					if ( container ) {
						container.scrollTop = container.scrollHeight;
					}
				} else {
					// Fallback: scroll all containers (e.g. called outside directive context).
					const containers = document.querySelectorAll(
						'.wp-pinch-chat__messages'
					);
					containers.forEach( ( c ) => {
						c.scrollTop = c.scrollHeight;
					} );
				}
			} );
		},

		/**
		 * Return focus to the input field after sending.
		 */
		focusInput() {
			requestAnimationFrame( () => {
				const el = getElement();
				const root = el?.ref?.closest( '.wp-pinch-chat' );
				if ( root ) {
					const input = root.querySelector( '.wp-pinch-chat__input' );
					if ( input && ! input.disabled ) {
						input.focus();
					}
				} else {
					const input = document.querySelector(
						'.wp-pinch-chat__input'
					);
					if ( input && ! input.disabled ) {
						input.focus();
					}
				}
			} );
		},
	},

	callbacks: {
		/**
		 * Initialize — restore session on mount.
		 */
		init() {
			try {
				actions.restoreSession();
			} catch ( err ) {
				handleChatError( err, 'init' );
			}
		},
	},
} );
