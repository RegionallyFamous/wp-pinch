/**
 * Tests for the Pinch Chat edit.js block editor component.
 *
 * @package
 */

describe( 'Pinch Chat block editor', () => {
	describe( 'blockId generation', () => {
		it( 'should generate a string starting with wp-pinch-chat-', () => {
			const blockId =
				'wp-pinch-chat-' + Math.random().toString( 36 ).slice( 2, 10 );
			expect( blockId ).toMatch( /^wp-pinch-chat-[a-z0-9]+$/ );
		} );

		it( 'should generate unique IDs', () => {
			const ids = new Set();
			for ( let i = 0; i < 100; i++ ) {
				ids.add(
					'wp-pinch-chat-' +
						Math.random().toString( 36 ).slice( 2, 10 )
				);
			}
			expect( ids.size ).toBe( 100 );
		} );

		it( 'should produce IDs of consistent length', () => {
			const id =
				'wp-pinch-chat-' + Math.random().toString( 36 ).slice( 2, 10 );
			// wp-pinch-chat- is 15 chars, random part is up to 8 chars.
			expect( id.length ).toBeGreaterThanOrEqual( 16 );
			expect( id.length ).toBeLessThanOrEqual( 23 );
		} );
	} );

	describe( 'block attributes defaults', () => {
		const defaults = {
			placeholder:
				'Pinch in a question — what do you want to know about this site?',
			showHeader: true,
			maxHeight: '400px',
			blockId: '',
		};

		it( 'should have expected default placeholder', () => {
			expect( defaults.placeholder ).toBe(
				'Pinch in a question — what do you want to know about this site?'
			);
		} );

		it( 'should show header by default', () => {
			expect( defaults.showHeader ).toBe( true );
		} );

		it( 'should have 400px max height by default', () => {
			expect( defaults.maxHeight ).toBe( '400px' );
		} );

		it( 'should have empty blockId by default', () => {
			expect( defaults.blockId ).toBe( '' );
		} );
	} );

	describe( 'maxHeight validation', () => {
		const validPattern = /^\d+(\.\d+)?(px|em|rem|vh|%)$/;

		it.each( [ '400px', '100vh', '50%', '20em', '15rem', '1.5em' ] )(
			'should accept valid CSS dimension: %s',
			( val ) => {
				expect( val ).toMatch( validPattern );
			}
		);

		it.each( [
			'400',
			'px',
			'100vw',
			'calc(100vh - 50px)',
			'<script>alert(1)</script>',
			'100px; background: red',
		] )( 'should reject invalid CSS dimension: %s', ( val ) => {
			expect( val ).not.toMatch( validPattern );
		} );
	} );
} );
