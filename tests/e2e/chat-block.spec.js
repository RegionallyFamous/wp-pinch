/**
 * E2E tests for the Pinch Chat block.
 *
 * Requires wp-env to be running.
 *
 * @package WP_Pinch
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Pinch Chat Block', () => {
	test( 'should be insertable in the editor', async ( {
		admin,
		editor,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( { name: 'wp-pinch/chat' } );

		// Verify the block was inserted.
		const block = editor.canvas.locator(
			'[data-type="wp-pinch/chat"]'
		);
		await expect( block ).toBeVisible();
	} );

	test( 'should show chat preview in editor', async ( {
		admin,
		editor,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( { name: 'wp-pinch/chat' } );

		const block = editor.canvas.locator(
			'[data-type="wp-pinch/chat"]'
		);

		// Check for header.
		await expect(
			block.locator( '.wp-pinch-chat__header-title' )
		).toContainText( 'Pinch Chat' );

		// Check for input area (disabled in editor).
		await expect(
			block.locator( '.wp-pinch-chat__input' )
		).toBeDisabled();

		// Check for send button (disabled in editor).
		await expect(
			block.locator( '.wp-pinch-chat__send' )
		).toBeDisabled();
	} );

	test( 'should have block settings in sidebar', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( { name: 'wp-pinch/chat' } );

		// Open sidebar settings.
		await editor.openDocumentSettingsSidebar();

		// Check for Chat Settings panel.
		await expect(
			page.locator( 'text=Chat Settings' )
		).toBeVisible();
	} );

	test( 'should save and render on frontend', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( { name: 'wp-pinch/chat' } );

		// Publish the post.
		const postId = await editor.publishPost();

		// Navigate to the post.
		await page.goto( `/?p=${ postId }` );

		// The chat block should render.
		const chat = page.locator( '.wp-pinch-chat' );
		await expect( chat ).toBeVisible();
	} );
} );
