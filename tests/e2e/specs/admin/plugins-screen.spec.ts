/**
 * The plugin's own row on the Plugins screen (`plugins.php`), default plugin
 * state.
 *
 * COVERAGE: `Disable_Blog_Admin::plugin_links()`, hooked on `plugin_row_meta`
 * and gated on `current_user_can( 'install_plugins' )`, appends Support,
 * Review, Donate, and GitHub links to the plugin's row-meta.
 *
 * Test 2 exercises that capability gate, but an editor is actually stopped
 * earlier by core's own `activate_plugins` check on the whole screen, which
 * `wp_die()`s with an explicit 403 (not core's generic 500 default).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { adminUrl } from '../../config/admin';
import { PLUGIN_SLUG, storageStatePath } from '../../config/roles';

test.describe( 'admin: plugins screen row meta (default state)', () => {
	test( 'the plugin row shows the support links', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'plugins.php' );

		const meta = page.locator( `tr[data-slug="${ PLUGIN_SLUG }"] .plugin-version-author-uri` );

		// Asserted on hrefs (plugin-owned literals), not translated labels.
		await expect(
			meta.locator( 'a[href="https://wordpress.org/support/plugin/disable-blog/"]' )
		).toBeVisible();
		await expect(
			meta.locator(
				'a[href="https://wordpress.org/support/plugin/disable-blog/reviews/#new-post"]'
			)
		).toBeVisible();
		await expect(
			meta.locator( 'a[href="http://joshuadnelson.com/donate/"]' )
		).toBeVisible();
		await expect(
			meta.locator( 'a[href="https://github.com/joshuadavidnelson/disable-blog/"]' )
		).toBeVisible();
	} );
} );

test.describe( 'admin: plugins screen — capability gate (editor)', () => {
	test.use( { storageState: storageStatePath( 'editor' ) } );

	test( 'the links require the install_plugins capability', async ( { request } ) => {
		// Not admin.visitAdminPage(): that helper throws on a PHP-error-shaped
		// page; this needs to inspect the denied response itself.
		const response = await request.get( adminUrl( 'plugins.php' ), { maxRedirects: 0 } );

		expect( response.status() ).toBe( 403 );

		// The generic "access this page" copy from core's screen-level check,
		// not the plugins-specific message reached only once inside plugins.php.
		const body = await response.text();
		expect( body ).toContain( 'Sorry, you are not allowed to access this page.' );
	} );
} );
