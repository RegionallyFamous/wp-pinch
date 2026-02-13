/**
 * Pinch Chat — Frontend Interactivity API store.
 *
 * Features:
 * - Send/receive chat messages via REST API
 * - SSE streaming support with fallback
 * - Session persistence via sessionStorage
 * - Nonce auto-refresh on 403
 * - Character counter (4,000 char limit)
 * - Clear chat / session reset
 * - Copy-to-clipboard on assistant messages
 * - Message feedback (thumbs up/down)
 * - Slash commands (/new, /reset, /status, /compact)
 * - Markdown rendering (bold, italic, code, links, headings, lists, code blocks)
 * - Typing indicator animation
 * - Keyboard shortcuts (Escape to clear input)
 * - Token usage display
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
 * Convert Markdown to HTML (safe subset for chat bubbles).
 *
 * Supports:
 * - **bold**, *italic*, `inline code`
 * - [text](url) links (http/https only)
 * - Fenced code blocks (```...```)
 * - Headings (# through ####)
 * - Unordered lists (- or * prefix)
 * - Ordered lists (1. prefix)
 * - Horizontal rules (--- or ***)
 * - Newlines
 *
 * @param {string} text Raw text with optional Markdown.
 * @return {string} HTML string.
 */
function renderMarkdown( text ) {
	if ( ! text ) {
		return '';
	}

	// Escape HTML entities first to prevent XSS.
	// Double-quotes must also be escaped to prevent attribute breakout
	// (e.g. a crafted link URL containing " could inject onclick handlers).
	let html = text
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );

	// Fenced code blocks (``` ... ```) — extract before other processing.
	html = html.replace(
		/```(?:\w*)\n([\s\S]*?)```/g,
		'<pre><code>$1</code></pre>'
	);

	// Process line-by-line for block-level elements.
	const lines = html.split( '\n' );
	const output = [];
	let inList = false;
	let listType = '';
	let inPre = false;

	for ( let i = 0; i < lines.length; i++ ) {
		const line = lines[ i ];

		// Track whether we're inside a <pre> block.
		// Lines inside code blocks must be passed through unchanged.
		if ( line.includes( '<pre>' ) ) {
			inPre = true;
		}
		if ( inPre ) {
			if ( inList ) {
				output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				inList = false;
			}
			output.push( line );
			if ( line.includes( '</pre>' ) ) {
				inPre = false;
			}
			continue;
		}

		// Horizontal rule.
		if ( /^(?:---|\*\*\*|___)\s*$/.test( line ) ) {
			if ( inList ) {
				output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				inList = false;
			}
			output.push( '<hr>' );
			continue;
		}

		// Headings (# through ####).
		const headingMatch = line.match( /^(#{1,4})\s+(.+)$/ );
		if ( headingMatch ) {
			if ( inList ) {
				output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				inList = false;
			}
			const level = headingMatch[ 1 ].length;
			// Render as h4-h6 within chat to avoid overly large text.
			const tag = 'h' + Math.min( level + 3, 6 );
			output.push( `<${ tag }>${ headingMatch[ 2 ] }</${ tag }>` );
			continue;
		}

		// Unordered list items (- or *).
		const ulMatch = line.match( /^[\s]*[-*]\s+(.+)$/ );
		if ( ulMatch ) {
			if ( ! inList || listType !== 'ul' ) {
				if ( inList ) {
					output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				}
				output.push( '<ul>' );
				inList = true;
				listType = 'ul';
			}
			output.push( `<li>${ ulMatch[ 1 ] }</li>` );
			continue;
		}

		// Ordered list items (1. 2. etc).
		const olMatch = line.match( /^[\s]*\d+\.\s+(.+)$/ );
		if ( olMatch ) {
			if ( ! inList || listType !== 'ol' ) {
				if ( inList ) {
					output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				}
				output.push( '<ol>' );
				inList = true;
				listType = 'ol';
			}
			output.push( `<li>${ olMatch[ 1 ] }</li>` );
			continue;
		}

		// Close open list if line is not a list item.
		if ( inList ) {
			output.push( listType === 'ul' ? '</ul>' : '</ol>' );
			inList = false;
		}

		// Blank line.
		if ( line.trim() === '' ) {
			output.push( '<br>' );
			continue;
		}

		// Regular text line.
		output.push( line );
	}

	// Close any remaining open list.
	if ( inList ) {
		output.push( listType === 'ul' ? '</ul>' : '</ol>' );
	}

	html = output.join( '\n' );

	// Protect <pre><code> blocks from inline formatting by temporarily replacing them.
	const codeBlocks = [];
	html = html.replace( /<pre><code>([\s\S]*?)<\/code><\/pre>/g, ( match ) => {
		const index = codeBlocks.length;
		codeBlocks.push( match );
		return `%%CODEBLOCK_${ index }%%`;
	} );

	// Inline formatting (applied after block-level processing).
	// Inline code (backticks) — before bold/italic so backtick content isn't processed.
	html = html.replace( /`([^`]+)`/g, '<code>$1</code>' );
	// Bold (**text** or __text__).
	html = html.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' );
	html = html.replace( /__(.+?)__/g, '<strong>$1</strong>' );
	// Italic (*text* or _text_) — avoid matching list markers already in <li>.
	html = html.replace( /(?<![<\w])\*(.+?)\*(?![>])/g, '<em>$1</em>' );
	html = html.replace( /(?<![<\w])_(.+?)_(?![>])/g, '<em>$1</em>' );
	// Links [text](url) — only allow http/https URLs.
	html = html.replace(
		/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
		'<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
	);

	// Restore code blocks.
	html = html.replace(
		/%%CODEBLOCK_(\d+)%%/g,
		( _, idx ) => codeBlocks[ idx ]
	);

	// Clean up: replace remaining raw newlines with <br> (outside pre/code blocks).
	html = html.replace(
		/(?!<\/?(pre|code|ul|ol|li|h[4-6]|hr|br)[^>]*>)\n/g,
		'<br>'
	);

	return html;
}

/**
 * Fetch with exponential backoff retry for transient errors.
 *
 * Retries on network errors, 429, 502, 503, and 504 responses.
 * Delays: 1s, 3s, 9s (exponential base-3).
 *
 * @param {string} url        Fetch URL.
 * @param {Object} options    Fetch options.
 * @param {number} maxRetries Maximum retry attempts (default 3).
 * @return {Promise<Response>} Fetch response.
 */
async function fetchWithRetry( url, options, maxRetries = 3 ) {
	let lastError;
	for ( let attempt = 0; attempt <= maxRetries; attempt++ ) {
		try {
			const response = await fetch( url, options );
			// Don't retry client errors (except 429) or successful responses.
			if (
				response.ok ||
				( response.status < 500 && response.status !== 429 )
			) {
				return response;
			}
			// Retry on 429, 5xx.
			if ( attempt < maxRetries ) {
				const retryAfter = response.headers.get( 'Retry-After' );
				const delay = retryAfter
					? Math.min( parseInt( retryAfter, 10 ) * 1000, 30000 )
					: Math.pow( 3, attempt ) * 1000;
				await new Promise( ( r ) => setTimeout( r, delay ) );
				continue;
			}
			return response;
		} catch ( err ) {
			lastError = err;
			if ( attempt < maxRetries ) {
				const delay = Math.pow( 3, attempt ) * 1000;
				await new Promise( ( r ) => setTimeout( r, delay ) );
			}
		}
	}
	throw lastError;
}

const SCROLL_BOTTOM_THRESHOLD = 80;

function getMessagesContainer( rootOrEvent ) {
	if ( ! rootOrEvent ) {
		return null;
	}
	const root =
		rootOrEvent.target?.closest?.( '.wp-pinch-chat' ) ||
		rootOrEvent?.ref?.closest?.( '.wp-pinch-chat' ) ||
		rootOrEvent?.closest?.( '.wp-pinch-chat' );
	return root ? root.querySelector( '.wp-pinch-chat__messages' ) : null;
}

function isAtBottom( container ) {
	if ( ! container ) {
		return true;
	}
	const threshold = SCROLL_BOTTOM_THRESHOLD;
	return (
		container.scrollTop + container.clientHeight >=
		container.scrollHeight - threshold
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

		/**
		 * Formatted token usage string for display.
		 * Only returns a value when the token_display feature flag is on.
		 */
		get tokenDisplay() {
			if ( ! state.tokenDisplayOn || ! state.tokenUsage ) {
				return '';
			}
			const t = state.tokenUsage;
			return ( t.total || t.input + t.output || 0 ) + ' tokens used';
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

				// Intercept slash commands (only when feature flag is enabled).
				if ( state.slashCommandsOn && text.startsWith( '/' ) ) {
					const handled = await actions.handleSlashCommand( text );
					if ( handled ) {
						state.inputValue = '';
						return;
					}
				}

				// Prefer streaming when available.
				if ( state.streamUrl ) {
					return actions.sendMessageStream();
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
					let response = await fetchWithRetry( state.restUrl, {
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
							response = await fetchWithRetry( state.restUrl, {
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

					// Track token usage from response headers.
					const tokenUsageHeader =
						response.headers.get( 'X-Token-Usage' );
					if ( tokenUsageHeader ) {
						try {
							state.tokenUsage = JSON.parse( tokenUsageHeader );
						} catch ( e ) {
							// Ignore malformed token usage header.
						}
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
						text: 'Unable to reach the server after multiple attempts. Please check your connection.',
						isUser: false,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, errorMsg ];
					state.isConnected = false;
				} finally {
					state.isLoading = false;
					actions.saveSession();
					actions.scrollToBottomIfNeeded();
					actions.focusInput();
				}
			} catch ( fatalErr ) {
				handleChatError( fatalErr, 'sendMessage' );
			}
		},

		/**
		 * Send a chat message via SSE streaming.
		 * Falls back to non-streaming fetch on failure.
		 */
		async sendMessageStream() {
			const text = state.inputValue.trim();

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
			actions.saveSession();

			// Create placeholder agent message for streaming.
			const agentMsg = {
				id: 'msg-' + Date.now() + '-' + ++msgCounter,
				text: '',
				html: '',
				isUser: false,
				isStreaming: true,
				timestamp: new Date().toISOString(),
			};
			state.messages = [ ...state.messages, agentMsg ];

			try {
				const body = JSON.stringify( {
					message: text,
					session_key: state.sessionKey,
					model: state.model || undefined,
					agent_id: state.agentId || undefined,
				} );
				const headers = {
					'Content-Type': 'application/json',
					'X-WP-Nonce': state.nonce,
				};

				let response = await fetch( state.streamUrl, {
					method: 'POST',
					headers,
					body,
				} );

				// Nonce refresh on 403.
				if ( response.status === 403 ) {
					const refreshed = await actions.refreshNonce();
					if ( refreshed ) {
						response = await fetch( state.streamUrl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': state.nonce,
							},
							body,
						} );
					}
				}

				if ( ! response.ok ) {
					throw new Error(
						'Stream request failed: ' + response.status
					);
				}

				// Track token usage from response headers.
				const usage = response.headers.get( 'X-Token-Usage' );
				if ( usage ) {
					try {
						state.tokenUsage = JSON.parse( usage );
					} catch ( e ) {
						// Ignore malformed header.
					}
				}

				const reader = response.body.getReader();
				const decoder = new TextDecoder();
				let buffer = '';
				let streamDone = false;

				while ( ! streamDone ) {
					const { done, value } = await reader.read();
					if ( done ) {
						break;
					}

					buffer += decoder.decode( value, { stream: true } );
					const lines = buffer.split( '\n' );
					// Keep incomplete last line in buffer.
					buffer = lines.pop();

					for ( const line of lines ) {
						if ( line.startsWith( 'event: done' ) ) {
							streamDone = true;
							break;
						}
						if ( line.startsWith( 'data: ' ) ) {
							const chunk = line.slice( 6 );
							try {
								const payload = JSON.parse( chunk );
								if ( payload.reply ) {
									agentMsg.text += payload.reply;
								}
							} catch ( e ) {
								// Raw text chunk.
								if ( chunk ) {
									agentMsg.text += chunk;
								}
							}
							agentMsg.html = renderMarkdown( agentMsg.text );
							state.messages = [
								...state.messages.slice( 0, -1 ),
								{ ...agentMsg },
							];
							actions.scrollToBottom();
						}
					}
				}

				// Finalize the streamed message.
				agentMsg.isStreaming = false;
				agentMsg.html = renderMarkdown( agentMsg.text );
				state.messages = [
					...state.messages.slice( 0, -1 ),
					{ ...agentMsg },
				];

				// Announce for screen readers.
				if ( window.wp?.a11y?.speak ) {
					window.wp.a11y.speak( agentMsg.text, 'polite' );
				}
			} catch ( err ) {
				// Remove the streaming placeholder.
				state.messages = state.messages.filter(
					( m ) => m.id !== agentMsg.id
				);

				// Fall back to non-streaming fetch.
				try {
					let response = await fetchWithRetry( state.restUrl, {
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

					if ( response.status === 403 ) {
						const refreshed = await actions.refreshNonce();
						if ( refreshed ) {
							response = await fetchWithRetry( state.restUrl, {
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

					// Track token usage from fallback response.
					const usage = response.headers.get( 'X-Token-Usage' );
					if ( usage ) {
						try {
							state.tokenUsage = JSON.parse( usage );
						} catch ( e ) {
							// Ignore.
						}
					}

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
						const fallbackMsg = {
							id: 'msg-' + Date.now() + '-' + ++msgCounter,
							text: replyText,
							html: renderMarkdown( replyText ),
							isUser: false,
							timestamp: new Date().toISOString(),
						};
						state.messages = [ ...state.messages, fallbackMsg ];

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
				} catch ( fallbackErr ) {
					const errorMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text: 'Unable to reach the server. Please check your connection.',
						isUser: false,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, errorMsg ];
					state.isConnected = false;
				}
			} finally {
				state.isLoading = false;
				actions.saveSession();
				actions.scrollToBottomIfNeeded();
				actions.focusInput();
			}
		},

		/**
		 * Handle slash commands entered in the chat input.
		 *
		 * @param {string} text The full input text starting with '/'.
		 * @return {Promise<boolean>} True if the command was handled.
		 */
		async handleSlashCommand( text ) {
			const cmd = text.trim().toLowerCase().split( /\s+/ )[ 0 ];

			if ( cmd === '/new' || cmd === '/reset' ) {
				await actions.resetSession();
				const sysMsg = {
					id: 'msg-' + Date.now() + '-' + ++msgCounter,
					text: 'Session reset. Starting fresh conversation.',
					isUser: false,
					isSystem: true,
					timestamp: new Date().toISOString(),
				};
				state.messages = [ ...state.messages, sysMsg ];
				actions.saveSession();
				return true;
			}

			if ( cmd === '/status' ) {
				try {
					const statusUrl = state.restUrl.replace(
						/\/chat(\/public)?$/,
						'/status'
					);
					const res = await fetch( statusUrl, {
						headers: state.nonce
							? { 'X-WP-Nonce': state.nonce }
							: {},
					} );
					const data = await res.json();
					const statusText =
						'Plugin v' +
						( data.plugin_version || '?' ) +
						' | Gateway: ' +
						( data.gateway?.connected
							? 'Connected'
							: 'Disconnected' ) +
						' | Circuit: ' +
						( data.circuit?.state || 'n/a' );
					const sysMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text: statusText,
						isUser: false,
						isSystem: true,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, sysMsg ];
					actions.saveSession();
				} catch ( e ) {
					// Ignore status fetch failures.
				}
				return true;
			}

			if (
				cmd === '/ghostwrite' &&
				state.ghostWriterOn &&
				state.ghostWriteUrl
			) {
				const parts = text.trim().split( /\s+/ );
				const postId = parts[ 1 ] ? parseInt( parts[ 1 ], 10 ) : 0;
				const action = postId > 0 ? 'write' : 'list';

				try {
					const loadingMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text:
							action === 'write'
								? 'Ghostwriting draft #' +
								  postId +
								  '... channeling your voice.'
								: 'Searching the draft graveyard...',
						isUser: false,
						isSystem: true,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, loadingMsg ];
					state.isLoading = true;

					const body = { action };
					if ( postId > 0 ) {
						body.post_id = postId;
					}

					const res = await fetch( state.ghostWriteUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							...( state.nonce
								? { 'X-WP-Nonce': state.nonce }
								: {} ),
						},
						body: JSON.stringify( body ),
					} );

					const data = await res.json();
					const replyText =
						data.reply ||
						data.message ||
						'Ghost Writer returned an unexpected response.';

					const replyMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text: replyText,
						isUser: false,
						isSystem: false,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, replyMsg ];
					actions.saveSession();
				} catch ( e ) {
					const errMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text: 'Ghost Writer request failed. The spirits are uncooperative.',
						isUser: false,
						isSystem: true,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, errMsg ];
				} finally {
					state.isLoading = false;
					actions.scrollToBottomIfNeeded();
				}
				return true;
			}

			if ( cmd === '/molt' && state.moltOn && state.moltUrl ) {
				const parts = text.trim().split( /\s+/ );
				const postId = parts[ 1 ] ? parseInt( parts[ 1 ], 10 ) : 0;

				if ( postId < 1 ) {
					const errMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text: 'Usage: /molt [post_id] — e.g. /molt 123',
						isUser: false,
						isSystem: true,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, errMsg ];
					actions.saveSession();
					actions.scrollToBottomIfNeeded();
					return true;
				}

				try {
					const loadingMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text:
							'Molting post #' +
							postId +
							'... shedding one form, emerging in many.',
						isUser: false,
						isSystem: true,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, loadingMsg ];
					state.isLoading = true;

					const res = await fetch( state.moltUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							...( state.nonce
								? { 'X-WP-Nonce': state.nonce }
								: {} ),
						},
						body: JSON.stringify( { post_id: postId } ),
					} );

					const data = await res.json();
					const replyText =
						data.reply ||
						data.message ||
						( data.code === 'rate_limited'
							? 'Too many requests. Please wait a moment.'
							: 'Molt returned an unexpected response.' );

					const replyMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text: replyText,
						isUser: false,
						isSystem: false,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, replyMsg ];
					actions.saveSession();
				} catch ( e ) {
					const errMsg = {
						id: 'msg-' + Date.now() + '-' + ++msgCounter,
						text: 'Molt request failed. The lobster could not shed its shell.',
						isUser: false,
						isSystem: true,
						timestamp: new Date().toISOString(),
					};
					state.messages = [ ...state.messages, errMsg ];
				} finally {
					state.isLoading = false;
					actions.scrollToBottomIfNeeded();
				}
				return true;
			}

			if ( cmd === '/compact' ) {
				// Let it go through as a normal message.
				// The gateway handles /compact as a session command.
				state.inputValue = text;
				return false;
			}

			return false;
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
		 * Clear all chat messages and reset the session.
		 */
		clearChat() {
			actions.resetSession();
		},

		/**
		 * Reset the session — optionally via server, then clear messages.
		 */
		async resetSession() {
			try {
				if ( state.sessionResetUrl && state.nonce ) {
					const res = await fetch( state.sessionResetUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': state.nonce,
						},
					} );
					if ( res.ok ) {
						const data = await res.json();
						state.sessionKey = data.session_key;
					}
				} else {
					// Client-side fallback — use the correct prefix so the
					// server recognises the key format (public vs authenticated).
					const isPublic =
						state.sessionKey &&
						state.sessionKey.startsWith( 'wp-pinch-public-' );
					state.sessionKey = isPublic
						? 'wp-pinch-public-' +
						  crypto.randomUUID().replace( /-/g, '' ).slice( 0, 16 )
						: 'wp-pinch-chat-' + Date.now();
				}
				state.messages = [];
				actions.saveSession();
			} catch ( e ) {
				// Silent fail — just clear locally.
				state.messages = [];
				actions.saveSession();
			}
		},

		/**
		 * Start a new chat session with a fresh session key.
		 * Preserves the old session in sessionStorage under its key.
		 */
		newSession() {
			// Save current session before switching.
			actions.saveSession();

			// Generate a new session key scoped to the user.
			const baseKey = state.sessionKey
				.split( '-' )
				.slice( 0, 4 )
				.join( '-' );
			const newKey = baseKey + '-' + Date.now().toString( 36 );

			state.sessionKey = newKey;
			state.messages = [];
			actions.saveSession();

			// Announce for screen readers.
			if ( window.wp?.a11y?.speak ) {
				window.wp.a11y.speak( 'New conversation started.', 'polite' );
			}
		},

		/**
		 * Switch to an existing session by key.
		 *
		 * @param {string} sessionKey The session key to switch to.
		 */
		switchSession( sessionKey ) {
			if ( ! sessionKey || sessionKey === state.sessionKey ) {
				return;
			}
			// Save current session first.
			actions.saveSession();

			// Switch to the target session.
			state.sessionKey = sessionKey;
			state.messages = [];

			// Restore messages for the target session.
			try {
				const storageKey = 'wp-pinch-msgs-' + sessionKey;
				const saved = sessionStorage.getItem( storageKey );
				if ( saved ) {
					const parsed = JSON.parse( saved );
					if ( Array.isArray( parsed ) ) {
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

			actions.scrollToBottom();
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
		 * Rate a message with thumbs up or down.
		 *
		 * @param {Event} event Click event on a feedback button.
		 */
		rateMessage( event ) {
			try {
				const btn = event.target.closest(
					'.wp-pinch-chat__feedback-btn'
				);
				if ( ! btn ) {
					return;
				}
				const rating = btn.dataset.rating;
				const msgEl = btn.closest( '.wp-pinch-chat__message' );
				if ( ! msgEl ) {
					return;
				}
				const msgId = msgEl.dataset.msgId;
				// Find and update the message.
				state.messages = state.messages.map( ( msg ) => {
					if ( msg.id === msgId ) {
						return { ...msg, rating };
					}
					return msg;
				} );
				actions.saveSession();
			} catch ( e ) {
				// Silent fail.
			}
		},

		/**
		 * Save messages to sessionStorage for persistence across page loads.
		 * Also maintains a session index for multi-session support.
		 */
		saveSession() {
			try {
				// Save messages under the current session key.
				const storageKey =
					'wp-pinch-msgs-' +
					( state.sessionKey || state.blockId || 'default' );
				sessionStorage.setItem(
					storageKey,
					JSON.stringify( state.messages )
				);

				// Maintain a session index for this block.
				const indexKey =
					'wp-pinch-sessions-' + ( state.blockId || 'default' );
				let sessions = [];
				try {
					sessions =
						JSON.parse( sessionStorage.getItem( indexKey ) ) || [];
				} catch ( e ) {
					/* empty */
				}

				// Add current session if not already tracked.
				if ( ! sessions.includes( state.sessionKey ) ) {
					sessions.push( state.sessionKey );
					sessionStorage.setItem(
						indexKey,
						JSON.stringify( sessions )
					);
				}
			} catch ( e ) {
				// Silent fail — sessionStorage may be full or unavailable.
			}
		},

		/**
		 * Restore messages from sessionStorage.
		 */
		restoreSession() {
			try {
				// Try new key format first, fall back to legacy format.
				const newKey =
					'wp-pinch-msgs-' +
					( state.sessionKey || state.blockId || 'default' );
				const legacyKey =
					'wp-pinch-chat-' + ( state.blockId || 'default' );
				const saved =
					sessionStorage.getItem( newKey ) ||
					sessionStorage.getItem( legacyKey );
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
			state.showScrollToBottom = false;
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
		 * If user is at bottom, scroll to bottom; otherwise show "Scroll to bottom" button.
		 */
		scrollToBottomIfNeeded() {
			const el = getElement();
			const root = el?.ref?.closest( '.wp-pinch-chat' );
			const container = root
				? root.querySelector( '.wp-pinch-chat__messages' )
				: null;
			if ( isAtBottom( container ) ) {
				actions.scrollToBottom();
			} else {
				state.showScrollToBottom = true;
			}
		},

		/**
		 * Scroll to bottom (from FAB click) and hide the FAB.
		 *
		 * @param {Event} event Click event from the scroll-to-bottom button.
		 */
		scrollToBottomAndHide( event ) {
			const container = getMessagesContainer( event );
			if ( container ) {
				container.scrollTop = container.scrollHeight;
			}
			state.showScrollToBottom = false;
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
				// When user scrolls to bottom, hide the "Scroll to bottom" FAB.
				const el = getElement();
				if ( el?.ref ) {
					const root = el.ref.closest( '.wp-pinch-chat' );
					const container = root?.querySelector(
						'.wp-pinch-chat__messages'
					);
					if ( container ) {
						container.addEventListener( 'scroll', () => {
							if ( isAtBottom( container ) ) {
								state.showScrollToBottom = false;
							}
						} );
					}
				}

				// Generate unique public session key per browser.
				if ( ! state.sessionKey && state.isConnected ) {
					const storageKey =
						'wp-pinch-public-session-' +
						( state.blockId || 'default' );
					let key = sessionStorage.getItem( storageKey );
					if ( ! key ) {
						key =
							'wp-pinch-public-' +
							crypto
								.randomUUID()
								.replace( /-/g, '' )
								.slice( 0, 16 );
						sessionStorage.setItem( storageKey, key );
					}
					state.sessionKey = key;
				}
				actions.restoreSession();
			} catch ( err ) {
				handleChatError( err, 'init' );
			}
		},
	},
} );
