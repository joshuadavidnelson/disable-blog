/**
 * The plugin's own row on the Plugins screen (`plugins.php`), default plugin
 * state.
 *
 * COVERAGE: `Disable_Blog_Admin::plugin_links()`, hooked on `plugin_row_meta`
 * and gated on `current_user_can( 'install_plugins' )`, appends Support,
 * Review, Donate, and GitHub links to the plugin's row-meta
 * (`.plugin-version-author-uri`).
 *
 * CAPABILITY GATE, VERIFIED NOT ASSUMED: test 2 exercises that
 * `install_plugins` check, but an editor can't reach it at all -- core's own
 * `current_user_can( 'activate_plugins' )` check at the top of
 * wp-admin/plugins.php denies the ENTIRE screen first (an editor has neither
 * capability by default). That check calls
 * `wp_die( $message, $title, array( 'response' => 403 ) )` -- an explicit
 * `403`, not core's generic `wp_die()` 500 default -- verified directly
 * against wp-admin/plugins.php on the wp-env core install backing this suite,
 * not assumed.
 *
 * AUTHENTICATED BY DEFAULT (test 1): no `storageState` override -- the
 * project default (administrator) has `install_plugins`.
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

		// Asserted on hrefs, not labels -- more stable against copy changes,
		// and every href here is a plugin-owned literal straight out of
		// plugin_links() rather than translated text.
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
		// Deliberately not admin.visitAdminPage(): that helper throws on a
		// PHP-error-shaped page, but what's needed here is to inspect the
		// actual denied response, not navigate past it -- same reasoning as
		// frontend/redirects.spec.ts's request-layer assertions.
		const response = await request.get( adminUrl( 'plugins.php' ), { maxRedirects: 0 } );

		expect( response.status() ).toBe( 403 );

		// Verified against the live site: an editor is stopped by the
		// screen-level capability check, which emits the generic
		// "access this page" copy -- not the plugins-specific
		// "manage plugins for this site" message that only appears once a
		// user has reached plugins.php itself.
		const body = await response.text();
		expect( body ).toContain( 'Sorry, you are not allowed to access this page.' );
	} );
} );
