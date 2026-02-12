/**
 * Pinch Chat — Frontend Interactivity API store.
 *
 * Features:
 * - Send/receive chat messages via REST API
 * - Session persistence via sessionStorage
 * - Nonce auto-refresh on 403
 * - Character counter (4,000 char limit)
 * - Clear chat button
 * - Copy-to-clipboard on assistant messages
 * - Basic Markdown rendering (bold, italic, code, links)
 * - Typing indicator animation
 * - Keyboard shortcuts (Escape to clear input)
 * - Error boundary for graceful failure
 *
 * @package
 */

/* global sessionStorage, requestAnimationFrame, navigator */

import { store, getElement } from '@wordpress/interactivity';

let msgCounter = 0;

/**
 * Maximum message length — must match REST controller MAX_MESSAGE_LENGTH.
 */
const MAX_MESSAGE_LENGTH = 4000;

/**
 * Global error handler — catches unhandled errors in the chat block
 * and renders a friendly fallback instead of a blank widget.
 *
 * @param {Error}  error   The error that was caught.
 * @param {string} context Description of where the error occurred.
 */
function handleChatError( error, context ) {
	// eslint-disable-next-line no-console
	console.error( '[WP Pinch Chat]', context, error );

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

/**
 * Convert basic Markdown to HTML (safe subset).
 *
 * Supports: **bold**, *italic*, `code`, [text](url), and newlines.
 * Does NOT support block-level elements (headers, lists, etc.) to
 * keep the output safe and predictable inside chat bubbles.
 *
 * @param {string} text Raw text with optional Markdown.
 * @return {string} HTML string.
 */
function renderMarkdown( text ) {
	if ( ! text ) {
		return '';
	}

	return (
		text
			// Escape HTML entities first to prevent XSS.
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			// Code (backticks) — before bold/italic so backtick content isn't processed.
			.replace( /`([^`]+)`/g, '<code>$1</code>' )
			// Bold (**text** or __text__).
			.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
			.replace( /__(.+?)__/g, '<strong>$1</strong>' )
			// Italic (*text* or _text_).
			.replace( /\*(.+?)\*/g, '<em>$1</em>' )
			.replace( /_(.+?)_/g, '<em>$1</em>' )
			// Links [text](url) — only allow http/https URLs.
			.replace(
				/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
				'<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
			)
			// Newlines to <br>.
			.replace( /\n/g, '<br>' )
	);
}

const { state, actions } = store( 'wp-pinch/chat', {
	state: {
		get messageCount() {
			return state.messages.length;
		},

		/**
		 * Characters remaining (reactive computed property).
		 */
		get charsRemaining() {
			return MAX_MESSAGE_LENGTH - ( state.inputValue || '' ).length;
		},

		/**
		 * Whether the character counter should show a warning.
		 */
		get charsWarning() {
			return state.charsRemaining < 200;
		},

		/**
		 * Whether the input is at the character limit.
		 */
		get charsExceeded() {
			return state.charsRemaining <= 0;
		},
	},

	actions: {
		/**
		 * Update the input value from the text field.
		 * Enforces max length client-side.
		 *
		 * @param {Event} event Input event.
		 */
		updateInput( event ) {
			const value = event.target.value;
			if ( value.length <= MAX_MESSAGE_LENGTH ) {
				state.inputValue = value;
			} else {
				state.inputValue = value.slice( 0, MAX_MESSAGE_LENGTH );
				event.target.value = state.inputValue;
			}
		},

		/**
		 * Handle keydown — send on Enter, clear input on Escape.
		 *
		 * @param {KeyboardEvent} event Keyboard event.
		 */
		handleKeyDown( event ) {
			if ( event.key === 'Enter' && ! event.shiftKey ) {
				event.preventDefault();
				actions.sendMessage();
			} else if ( event.key === 'Escape' ) {
				state.inputValue = '';
				event.target.value = '';
			}
		},

		/**
		 * Send a chat message to the WP Pinch REST API.
		 */
		async sendMessage() {
			try {
				const text = state.inputValue.trim();
				if (
					! text ||
					state.isLoading ||
					text.length > MAX_MESSAGE_LENGTH
				) {
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
					let response = await fetch( state.restUrl, {
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

					// Nonce refresh: if we get a 403, fetch a fresh nonce and retry once.
					if ( response.status === 403 ) {
						const refreshed = await actions.refreshNonce();
						if ( refreshed ) {
							response = await fetch( state.restUrl, {
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
						}
					}

					// Track rate limit info from response headers.
					const remaining = response.headers.get(
						'X-RateLimit-Remaining'
					);
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
						const replyText =
							data.reply ||
							data.message ||
							'No response received.';
						const agentMsg = {
							id: 'msg-' + Date.now() + '-' + ++msgCounter,
							text: replyText,
							html: renderMarkdown( replyText ),
							isUser: false,
							timestamp: new Date().toISOString(),
						};
						state.messages = [ ...state.messages, agentMsg ];

						// Announce for screen readers.
						if ( window.wp?.a11y?.speak ) {
							window.wp.a11y.speak( replyText, 'polite' );
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
		 * Refresh the WP REST nonce by fetching /wp-json/ root.
		 *
		 * @return {Promise<boolean>} True if nonce was refreshed.
		 */
		async refreshNonce() {
			try {
				const res = await fetch( '/wp-json/', {
					credentials: 'same-origin',
				} );
				if ( ! res.ok ) {
					return false;
				}
				// The nonce is in the X-WP-Nonce response header when logged in,
				// or we can try fetching the REST root with _wpnonce.
				// Simplest approach: reload nonce from a lightweight endpoint.
				const nonceRes = await fetch(
					'/wp-admin/admin-ajax.php?action=rest-nonce',
					{ credentials: 'same-origin' }
				);
				if ( nonceRes.ok ) {
					const newNonce = await nonceRes.text();
					if (
						newNonce &&
						newNonce.length > 0 &&
						newNonce.length < 20
					) {
						state.nonce = newNonce.trim();
						return true;
					}
				}
				return false;
			} catch ( e ) {
				return false;
			}
		},

		/**
		 * Clear all chat messages and session storage.
		 */
		clearChat() {
			state.messages = [];
			actions.saveSession();
		},

		/**
		 * Copy a message's text to the clipboard.
		 *
		 * @param {Event} event Click event on the copy button.
		 */
		async copyMessage( event ) {
			try {
				const btn = event.target.closest( '.wp-pinch-chat__copy-btn' );
				if ( ! btn ) {
					return;
				}

				const msgEl = btn.closest( '.wp-pinch-chat__message' );
				if ( ! msgEl ) {
					return;
				}

				// Get the plain text content (strip HTML from rendered markdown).
				const textEl = msgEl.querySelector(
					'.wp-pinch-chat__message-text'
				);
				const text = textEl ? textEl.textContent : msgEl.textContent;

				await navigator.clipboard.writeText( text );

				// Visual feedback — briefly change button text.
				const original = btn.textContent;
				btn.textContent = '\u2713'; // Checkmark.
				setTimeout( () => {
					btn.textContent = original;
				}, 1500 );
			} catch ( e ) {
				// Clipboard API may not be available in all contexts.
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
						// Re-render markdown for any agent messages.
						state.messages = parsed.map( ( msg ) => {
							if ( ! msg.isUser && msg.text && ! msg.html ) {
								msg.html = renderMarkdown( msg.text );
							}
							return msg;
						} );
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
