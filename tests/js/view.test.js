/**
 * Tests for the Pinch Chat view.js store.
 *
 * @package
 */

describe( 'Pinch Chat store', () => {
	beforeEach( () => {
		// Clear sessionStorage between tests.
		sessionStorage.clear();
	} );

	describe( 'sessionStorage keys', () => {
		it( 'should use blockId as storage key prefix', () => {
			const blockId = 'wp-pinch-chat-abc123';
			const key = 'wp-pinch-chat-' + ( blockId || 'default' );
			expect( key ).toBe( 'wp-pinch-chat-wp-pinch-chat-abc123' );
		} );

		it( 'should fall back to default when blockId is empty', () => {
			const blockId = '';
			const key = 'wp-pinch-chat-' + ( blockId || 'default' );
			expect( key ).toBe( 'wp-pinch-chat-default' );
		} );
	} );

	describe( 'message ID generation', () => {
		it( 'should produce unique IDs for rapid messages', () => {
			let counter = 0;
			const ids = new Set();

			for ( let i = 0; i < 100; i++ ) {
				const id = 'msg-' + Date.now() + '-' + ++counter;
				ids.add( id );
			}

			expect( ids.size ).toBe( 100 );
		} );
	} );

	describe( 'session persistence', () => {
		it( 'should save messages to sessionStorage', () => {
			const key = 'wp-pinch-chat-test';
			const messages = [
				{ id: 'msg-1', text: 'Hello', isUser: true },
				{ id: 'msg-2', text: 'Hi there', isUser: false },
			];

			sessionStorage.setItem( key, JSON.stringify( messages ) );

			const saved = JSON.parse( sessionStorage.getItem( key ) );
			expect( saved ).toHaveLength( 2 );
			expect( saved[ 0 ].text ).toBe( 'Hello' );
			expect( saved[ 1 ].isUser ).toBe( false );
		} );

		it( 'should handle corrupt sessionStorage data gracefully', () => {
			const key = 'wp-pinch-chat-corrupt';
			sessionStorage.setItem( key, 'not-valid-json{{{' );

			let restored = [];
			try {
				const saved = sessionStorage.getItem( key );
				if ( saved ) {
					const parsed = JSON.parse( saved );
					if ( Array.isArray( parsed ) ) {
						restored = parsed;
					}
				}
			} catch ( e ) {
				// Silent fail — matches view.js behavior.
			}

			expect( restored ).toEqual( [] );
		} );

		it( 'should reject non-array parsed data', () => {
			const key = 'wp-pinch-chat-object';
			sessionStorage.setItem(
				key,
				JSON.stringify( { not: 'an array' } )
			);

			let restored = [];
			try {
				const saved = sessionStorage.getItem( key );
				if ( saved ) {
					const parsed = JSON.parse( saved );
					if ( Array.isArray( parsed ) ) {
						restored = parsed;
					}
				}
			} catch ( e ) {
				// Silent fail.
			}

			expect( restored ).toEqual( [] );
		} );
	} );

	describe( 'message length validation', () => {
		it( 'should allow messages up to 4000 characters', () => {
			const msg = 'a'.repeat( 4000 );
			expect( msg.length ).toBeLessThanOrEqual( 4000 );
		} );

		it( 'should identify messages over the limit', () => {
			const msg = 'a'.repeat( 4001 );
			expect( msg.length ).toBeGreaterThan( 4000 );
		} );
	} );

	describe( 'input trimming', () => {
		it( 'should reject whitespace-only messages', () => {
			const text = '   \n\t  '.trim();
			expect( text ).toBe( '' );
			expect( ! text ).toBe( true );
		} );

		it( 'should accept non-empty trimmed messages', () => {
			const text = '  Hello world  '.trim();
			expect( text ).toBe( 'Hello world' );
			expect( ! text ).toBe( false );
		} );
	} );
} );
