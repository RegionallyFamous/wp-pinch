/**
 * E2E tests for the WP Pinch settings page.
 *
 * Requires wp-env to be running.
 *
 * @package
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

async function ensureConnectionTabReady( page, admin ) {
	await admin.visitAdminPage( 'admin.php', 'page=wp-pinch' );

	const wizard = page.locator( '#wp-pinch-wizard' );
	if ( await wizard.isVisible() ) {
		const skipLink = page.locator(
			'a:has-text("I\'ll scuttle back later")'
		);
		const href = await skipLink.getAttribute( 'href' );
		if ( href ) {
			await page.goto( href );
		}
	}

	await admin.visitAdminPage( 'admin.php', 'page=wp-pinch&tab=connection' );
}

test.describe( 'WP Pinch Settings Page', () => {
	test.beforeEach( async ( { admin, page } ) => {
		await ensureConnectionTabReady( page, admin );
	} );

	test( 'should display the settings page', async ( { page } ) => {
		await expect( page.locator( 'h1' ) ).toContainText( 'WP Pinch' );
	} );

	test( 'should have gateway URL input', async ( { page } ) => {
		const input = page.locator( '#wp_pinch_gateway_url' );
		await expect( input ).toBeVisible();
	} );

	test( 'should have API token input of type password', async ( {
		page,
	} ) => {
		const input = page.locator( '#wp_pinch_api_token' );
		await expect( input ).toBeVisible();
		await expect( input ).toHaveAttribute( 'type', 'password' );
	} );

	test( 'should have autocomplete off on API token', async ( { page } ) => {
		const input = page.locator( '#wp_pinch_api_token' );
		await expect( input ).toHaveAttribute( 'autocomplete', 'off' );
	} );

	test( 'should have a test connection button', async ( { page } ) => {
		const button = page.locator( '#wp-pinch-test-connection' );
		await expect( button ).toBeVisible();
	} );

	test( 'should have a save changes button', async ( { page } ) => {
		const button = page.locator( '#submit' );
		await expect( button ).toBeVisible();
	} );
} );
