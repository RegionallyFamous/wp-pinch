/**
 * WP Pinch admin settings JavaScript.
 *
 * Handles AJAX test-connection button on the settings page.
 */

import './admin.css';

( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		const $button = $( '#wp-pinch-test-connection' );
		const $result = $( '#wp-pinch-connection-result' );

		$button.on( 'click', function () {
			$button.prop( 'disabled', true );
			$result
				.text( 'Testing...' )
				.css( 'color', '#666' );

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
							.text( '✗ ' + ( response.data || 'Connection failed.' ) )
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
	} );
} )( jQuery );
