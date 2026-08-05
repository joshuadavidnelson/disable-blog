/**
 * Harness smoke test.
 *
 * Proves what every later spec assumes without re-checking: the plugin is
 * active, the pinned theme (`global-setup.ts`) is actually active, the
 * `dwpb-test/v1` mu-plugin fixture is reachable with real data, and the
 * per-role storage-state mechanism signs in as the right role. If this file
 * fails, fix the harness before trusting any other spec's result.
 *
 * Not read-only: the bootstrap check calls `setupSite()`, which is
 * idempotent and self-healing (see `config/seed.ts`), restoring the
 * canonical baseline every other spec assumes.
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
		// Calls setupSite() directly rather than the memoized siteConfig(),
		// since a memoized read can't guarantee the live site is bootstrapped
		// when specs share one worker process.
		const config = await setupSite( requestUtils );

		expect( config.permalinkStructure ).toBe( '/%postname%/' );

		expect( config.homeId ).toBeGreaterThan( 0 );
		expect( config.blogId ).toBeGreaterThan( 0 );
		expect( config.homeId ).not.toBe( config.blogId );

		// The front page's permalink is the site root, not "/home/" -- so
		// frontPageUrl is homeUrl with a trailing slash (see config/redirects.ts).
		expect( config.frontPageUrl ).toBe( `${ config.homeUrl }/` );

		await expectStatus( request, '/', 200 );
	} );

	test( 'the expected theme is active', async ( { requestUtils } ) => {
		// global-setup.ts pins THEME_SLUG so every spec's DOM assertions run
		// against the same markup; this is the one test that should fail if
		// that pinning silently breaks.
		const activeThemes = await requestUtils.rest< Array< { stylesheet: string } > >( {
			path: '/wp/v2/themes',
			params: { status: 'active' },
		} );

		expect( activeThemes ).toHaveLength( 1 );
		expect( activeThemes[ 0 ].stylesheet ).toBe( THEME_SLUG );
	} );

	test( 'the test API exposes plugin copy', async ( { requestUtils } ) => {
		const strings = await pluginStrings( requestUtils );

		// pluginStrings() already guards against empty values, so this asserts
		// a distinctive fragment of each string instead, catching wording
		// drift or a copy-paste swap between the hand-synced literals in
		// dwpb_test_api_strings().
		const expectedFragments: Record< keyof PluginStrings, string > = {
			no_front_page_notice: 'not fully active',
			front_equals_posts_notice: 'different from the post page',
			posts_page_edit_notice: 'shows your latest posts',
			press_this_disabled: 'Press This',
			page_post_state: 'Redirected to the homepage',
			users_pages_column_label: 'Pages',
			homepage_settings_text: 'You can choose',
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
