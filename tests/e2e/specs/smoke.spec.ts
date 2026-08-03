/**
 * Harness smoke test.
 *
 * Proves the five things every later spec assumes without re-checking: the
 * plugin under test is active in the running wp-env site, the pinned theme
 * (see `global-setup.ts`) is the one actually active, `dwpb-test/v1` (the
 * mu-plugin fixture) is reachable and returns real data — not empty
 * placeholders — and the per-role storage-state mechanism `test.use(
 * { storageState: storageStatePath( role ) } )` actually signs in as that
 * role. If this file fails, the cause is one of those five things, not a
 * bug in a real feature spec — fix the harness before trusting any other
 * spec's result.
 *
 * Seeds no content and toggles no fixtures, so there is nothing to clean up
 * and no `afterEach`. It is not, however, read-only: the bootstrap check
 * below calls `setupSite()`, which re-asserts the canonical Home/Blog +
 * reading-settings state via four `update_option()` calls and a
 * `flush_rewrite_rules()` on the server, every time this spec runs. That is
 * by design — `setupSite()` is idempotent and self-healing (see its
 * docblock in `config/seed.ts`), so re-running it here restores the exact
 * baseline every other spec already assumes rather than seeding anything new.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { PLUGIN_FILE, ROLE_USERS, THEME_SLUG, storageStatePath } from '../config/roles';
import { setupSite } from '../config/seed';
import { pluginStrings, type PluginStrings } from '../config/strings';
import { expectStatus } from '../config/redirects';

test.describe( 'e2e harness smoke', () => {
	test( 'the plugin is active on the plugins screen', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'plugins.php' );

		const row = page.locator( `tr[data-plugin="${ PLUGIN_FILE }"]` );

		await expect( row ).toBeVisible();
		await expect( row ).toHaveClass( /(^|\s)active(\s|$)/ );
	} );

	test( 'the site is bootstrapped for redirect testing', async ( {
		request,
		requestUtils,
	} ) => {
		// Deliberately bypasses the memoized siteConfig() cache and calls
		// setupSite() directly. With `workers: 1` every spec file shares one
		// worker process, so once sibling specs exist there is no guarantee
		// this file runs first — a memoized read here could silently return
		// another spec's cached-in-memory snapshot instead of proving the
		// live site is actually bootstrapped, defeating the point of a smoke
		// gate. Ordinary specs should keep using the memoized siteConfig();
		// only this harness check needs a guaranteed-live read.
		const config = await setupSite( requestUtils );

		expect( config.permalinkStructure ).toBe( '/%postname%/' );

		expect( config.homeId ).toBeGreaterThan( 0 );
		expect( config.blogId ).toBeGreaterThan( 0 );
		expect( config.homeId ).not.toBe( config.blogId );

		// get_permalink() on the page assigned to page_on_front returns the
		// SITE ROOT, not "/home/" — WordPress special-cases the front page's
		// permalink to be the home URL. So frontPageUrl is exactly homeUrl
		// with a trailing slash appended, both pointing at the same origin.
		// This is the distinction the whole redirect suite depends on: page
		// redirects target frontPageUrl (root, with slash), the feed
		// redirect targets homeUrl (no slash) — see the docblock in
		// `config/redirects.ts`. Asserting the relationship, rather than a
		// hardcoded host, keeps this test correct regardless of which port
		// wp-env happens to be bound to.
		expect( config.frontPageUrl ).toBe( `${ config.homeUrl }/` );

		await expectStatus( request, '/', 200 );
	} );

	test( 'the expected theme is active', async ( { requestUtils } ) => {
		// global-setup.ts pins THEME_SLUG so every spec's DOM assertions run
		// against the same markup regardless of which theme the running
		// WordPress version ships by default. If that pinning ever silently
		// stops working — a failed activation call that got swallowed, a
		// theme that fails to install — this is the one test that should
		// fail, instead of a scattering of confusing DOM assertions across
		// the rest of the suite.
		const activeThemes = await requestUtils.rest< Array< { stylesheet: string } > >( {
			path: '/wp/v2/themes',
			params: { status: 'active' },
		} );

		expect( activeThemes ).toHaveLength( 1 );
		expect( activeThemes[ 0 ].stylesheet ).toBe( THEME_SLUG );
	} );

	test( 'the test API exposes plugin copy', async ( { requestUtils } ) => {
		const strings = await pluginStrings( requestUtils );

		// pluginStrings() already throws if any value is empty, so a
		// toBeTruthy() check per key could never fail — it would be dead
		// weight duplicating that upstream guard. Five of these six values
		// are hand-copied literals in dwpb_test_api_strings() (see that
		// function's docblock in dwpb-test-api.php) that must be kept in
		// sync with the plugin by hand; wording drift, or a copy-paste swap
		// between two keys, would sail through a mere non-emptiness check.
		// Asserting a distinctive fragment of each real string catches that.
		const expectedFragments: Record< keyof PluginStrings, string > = {
			no_front_page_notice: 'not fully active',
			front_equals_posts_notice: 'different from the post page',
			posts_page_edit_notice: 'shows your latest posts',
			press_this_disabled: 'Press This',
			page_post_state: 'Redirected to the homepage',
			users_pages_column_label: 'Pages',
		};

		for ( const [ key, fragment ] of Object.entries(
			expectedFragments
		) as [ keyof PluginStrings, string ][] ) {
			expect( strings[ key ] ).toContain( fragment );
		}
	} );
} );

test.describe( 'e2e harness smoke — per-role storage state', () => {
	test.use( { storageState: storageStatePath( 'editor' ) } );

	test( 'the stored editor state signs in as the editor user', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'profile.php' );

		await expect( page.locator( '#user_login' ) ).toHaveValue(
			ROLE_USERS.editor.username
		);
	} );
} );
