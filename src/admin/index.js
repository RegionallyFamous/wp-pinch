/**
 * WP Pinch admin settings JavaScript.
 *
 * Handles:
 * - AJAX test-connection button on the settings page
 * - First-run wizard: step navigation, copy buttons, Test Connection with spinner
 */

/* global jQuery, navigator, wpPinchAdmin */

import './admin.css';

( function ( $ ) {
	'use strict';

	function initWizard() {
		const wizard = document.getElementById( 'wp-pinch-wizard' );
		if ( ! wizard ) {
			return;
		}

		const steps = wizard.querySelectorAll( '.wp-pinch-wizard__step' );
		const stepLabel = document.getElementById(
			'wp-pinch-wizard-step-label'
		);
		const totalSteps = 3;
		const strings = ( wpPinchAdmin && wpPinchAdmin.wizard ) || {};

		function goToStep( stepNum ) {
			steps.forEach( ( el, idx ) => {
				el.style.display = idx + 1 === stepNum ? 'block' : 'none';
			} );
			if ( stepLabel && strings.stepOf ) {
				stepLabel.textContent = strings.stepOf
					.replace( '%1$d', String( stepNum ) )
					.replace( '%2$d', String( totalSteps ) );
			}
		}

		wizard.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( '[data-wizard-action="go"]' );
			if ( ! btn ) {
				return;
			}
			const to = parseInt( btn.getAttribute( 'data-wizard-to' ), 10 );
			if ( to >= 1 && to <= totalSteps ) {
				goToStep( to );
			}
		} );

		// Copy buttons
		const feedbackIds = {
			'wp-pinch-mcp-url': 'wp-pinch-copy-feedback-mcp',
			'wp-pinch-cli-cmd': 'wp-pinch-copy-feedback-cli',
			'wp-pinch-skill-content': 'wp-pinch-wizard-skill-feedback',
		};
		wizard.querySelectorAll( '[data-wizard-copy]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const id = btn.getAttribute( 'data-wizard-copy' );
				const codeEl = document.getElementById( id );
				if ( ! codeEl ) {
					return;
				}
				const feedbackId = feedbackIds[ id ] || null;
				const feedbackEl = feedbackId
					? document.getElementById( feedbackId )
					: null;
				const text = codeEl.textContent.trim();
				navigator.clipboard
					.writeText( text )
					.then( () => {
						if ( feedbackEl ) {
							feedbackEl.textContent =
								strings.copied || 'Snatched!';
							feedbackEl.classList.add( 'is-visible' );
							setTimeout( () => {
								feedbackEl.classList.remove( 'is-visible' );
							}, 2000 );
						}
					} )
					.catch( () => {} );
			} );
		} );

		// Test Connection with spinner and aria-busy
		const testBtn = document.getElementById( 'wp-pinch-wizard-test' );
		const resultEl = document.getElementById(
			'wp-pinch-wizard-test-result'
		);
		if ( testBtn && resultEl ) {
			testBtn.addEventListener( 'click', () => {
				const urlInput = document.getElementById(
					'wp_pinch_gateway_url'
				);
				const url = ( urlInput && urlInput.value ) || '';
				if ( ! url.trim() ) {
					resultEl.textContent =
						strings.pleaseGateway ||
						'Please enter a gateway URL first.';
					resultEl.className = 'wp-pinch-wizard-test-result is-error';
					return;
				}

				// Set loading state without innerHTML so translated text is never parsed as HTML.
				resultEl.textContent = '';
				resultEl.className = 'wp-pinch-wizard-test-result is-loading';
				const spinner = document.createElement( 'span' );
				spinner.className = 'wp-pinch-wizard-spinner';
				spinner.setAttribute( 'aria-hidden', 'true' );
				resultEl.appendChild( spinner );
				resultEl.appendChild(
					document.createTextNode( strings.testing || 'Testing…' )
				);
				testBtn.setAttribute( 'aria-busy', 'true' );
				testBtn.disabled = true;

				const tokenInput =
					document.getElementById( 'wp_pinch_api_token' );
				const token = ( tokenInput && tokenInput.value ) || '';
				const baseUrl = url.replace( /\/+$/, '' );

				fetch( baseUrl + '/api/v1/status', {
					method: 'GET',
					headers: {
						Authorization: 'Bearer ' + token,
					},
				} )
					.then( ( r ) => {
						if ( r.ok ) {
							resultEl.textContent =
								strings.connected || 'Claws at the ready!';
							resultEl.className =
								'wp-pinch-wizard-test-result is-success';
						} else {
							resultEl.textContent = (
								strings.failedHttp ||
								'Connection failed (HTTP %s).'
							).replace( '%s', String( r.status ) );
							resultEl.className =
								'wp-pinch-wizard-test-result is-error';
						}
					} )
					.catch( () => {
						resultEl.textContent =
							strings.unableReach ||
							'Unable to reach gateway. Check the URL.';
						resultEl.className =
							'wp-pinch-wizard-test-result is-error';
					} )
					.finally( () => {
						testBtn.setAttribute( 'aria-busy', 'false' );
						testBtn.disabled = false;
					} );
			} );
		}
	}

	$( document ).ready( function () {
		// Settings page: test connection (only when localized script data exists)
		const $button = $( '#wp-pinch-test-connection' );
		const $result = $( '#wp-pinch-connection-result' );

		if (
			$button.length &&
			typeof wpPinchAdmin !== 'undefined' &&
			wpPinchAdmin.ajaxUrl
		) {
			$button.on( 'click', function () {
				$button.prop( 'disabled', true );
				$result.text( 'Testing...' ).css( 'color', '#666' );

				$.ajax( {
					url: wpPinchAdmin.ajaxUrl,
					method: 'POST',
					data: {
						action: 'wp_pinch_test_connection',
						nonce: wpPinchAdmin.nonce,
					},
					success( response ) {
						if ( response.success ) {
							$result
								.text( '✓ ' + response.data.message )
								.css( 'color', '#46b450' );
						} else {
							$result
								.text(
									'✗ ' +
										( response.data ||
											'Connection failed.' )
								)
								.css( 'color', '#dc3232' );
						}
					},
					error() {
						$result
							.text( '✗ Network error.' )
							.css( 'color', '#dc3232' );
					},
					complete() {
						$button.prop( 'disabled', false );
					},
				} );
			} );
		}

		initWizard();

		// Copy OpenClaw skill to clipboard.
		const copySkillBtn = document.getElementById( 'wp-pinch-copy-skill' );
		const skillFeedback = document.getElementById(
			'wp-pinch-skill-copy-feedback'
		);
		if (
			copySkillBtn &&
			skillFeedback &&
			typeof wpPinchAdmin !== 'undefined' &&
			wpPinchAdmin.openclawSkill
		) {
			copySkillBtn.addEventListener( 'click', () => {
				navigator.clipboard
					.writeText( wpPinchAdmin.openclawSkill )
					.then( () => {
						skillFeedback.textContent =
							( wpPinchAdmin.wizard &&
								wpPinchAdmin.wizard.copied ) ||
							'Snatched!';
						skillFeedback.classList.add( 'is-visible' );
						setTimeout( () => {
							skillFeedback.classList.remove( 'is-visible' );
							skillFeedback.textContent = '';
						}, 2000 );
					} )
					.catch( () => {} );
			} );
		}
	} );
} )( jQuery );
