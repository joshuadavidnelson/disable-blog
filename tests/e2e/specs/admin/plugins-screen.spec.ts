/**
 * The plugin's own row on the Plugins screen (`plugins.php`), default plugin
 * state.
 *
 * The second describe block exercises `plugin_links()`'s own
 * `install_plugins` gate directly. Stock WordPress roles grant
 * `install_plugins` and `activate_plugins` together (Administrator only), so
 * an editor never reaches `plugin_links()` at all -- core's own
 * screen-level `activate_plugins` check on `plugins.php` stops them first
 * with a 403. Proving the plugin's own check needs a role that splits the
 * two: `dwpb_activate_only` has `activate_plugins` (clears core's wall) but
 * not `install_plugins` (still blocked by the plugin's own check).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { adminUrl } from '../../config/admin';
import { PLUGIN_SLUG } from '../../config/roles';
import { wpCli, wpCliOk } from '../../config/wp-cli';

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

test.describe( 'admin: plugins screen — plugin_links() capability gate', () => {
	const GATE_ROLE = 'dwpb_activate_only';
	const GATE_USERNAME = 'dwpb_activate_only_user';
	const GATE_EMAIL = 'dwpb_activate_only_user@example.com';
	const GATE_PASSWORD = 'dwpb-activate-only-password';

	test.beforeAll( async () => {
		// Tolerates a leftover role/user from a prior run that crashed before
		// afterAll ran: `wp role create`/`wp user create` exit non-zero (and
		// wpCliOk() throws) if either already exists, so clear them first.
		await wpCli( [ 'user', 'delete', GATE_USERNAME, '--yes' ] );
		await wpCli( [ 'role', 'delete', GATE_ROLE ] );

		await wpCliOk( [ 'role', 'create', GATE_ROLE, 'DWPB Activate Only' ] );
		await wpCliOk( [ 'cap', 'add', GATE_ROLE, 'activate_plugins' ] );
		await wpCliOk( [ 'cap', 'add', GATE_ROLE, 'read' ] );
		await wpCliOk( [
			'user',
			'create',
			GATE_USERNAME,
			GATE_EMAIL,
			`--role=${ GATE_ROLE }`,
			`--user_pass=${ GATE_PASSWORD }`,
		] );
	} );

	test.afterAll( async () => {
		await wpCli( [ 'user', 'delete', GATE_USERNAME, '--yes' ] );
		await wpCli( [ 'role', 'delete', GATE_ROLE ] );
	} );

	test( "activate_plugins without install_plugins clears core's wall but not plugin_links()'s own gate", async ( {
		request,
	} ) => {
		// A raw wp-login.php POST rather than a stored storageState: this role
		// exists only for this file, so there's nothing to persist.
		await request.post( '/wp-login.php', {
			form: {
				log: GATE_USERNAME,
				pwd: GATE_PASSWORD,
				'wp-submit': 'Log In',
				redirect_to: adminUrl( 'index.php' ),
				testcookie: '1',
			},
		} );

		const response = await request.get( adminUrl( 'plugins.php' ) );

		// Control: core's screen-level activate_plugins check passed -- this is
		// the real plugin list table, not a 403 permission-error page.
		expect( response.status() ).toBe( 200 );
		const body = await response.text();
		expect( body ).toContain( 'id="the-list"' );

		// plugin_links()'s own install_plugins check: the row-meta links a
		// signed-in administrator sees (test above) are absent for this role.
		expect( body ).not.toContain( 'https://wordpress.org/support/plugin/disable-blog/' );
	} );
} );
