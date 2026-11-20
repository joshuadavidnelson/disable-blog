/**
 * Two independent sitemap filters, via the generic filter-override
 * mechanism -- neither is exposed by a fixture toggle already.
 *
 * `dwpb_disable_removed_sitemaps` guards `disable_removed_sitemaps()`'s
 * manual 404 of sub-file requests for a provider the plugin removed
 * entirely. Forced false, the plugin leaves the request to core. Before
 * WordPress 7.1.1, core's renderer (`WP_Sitemaps::render_sitemaps()`) just
 * `return`s on a missing provider, so the request falls through to the normal
 * front-end template; from 7.1.1 core 404s it itself, and the override has no
 * visible effect there.
 *
 * `dwpb_disable_user_sitemap` overrides the computed "should the users
 * sitemap be hidden" value inside `wp_author_sitemaps()`, independent of the
 * two conditions that normally drive it -- see filters/author-archives.spec.ts.
 *
 * Control for both: sitemap/sitemap.spec.ts asserts the shipped defaults.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { expectStatus } from '../../config/redirects';
import { coreVersionAtLeast } from '../../config/wp-cli';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

test.describe( 'filters: removed-sitemap 404 override (dwpb_disable_removed_sitemaps)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_disable_removed_sitemaps: false,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'a removed provider sub-file is left to core', async ( { request } ) => {
		if ( await coreVersionAtLeast( '7.1.1' ) ) {
			await expectStatus( request, '/wp-sitemap-users-1.xml', 404 );
			return;
		}

		const response = await expectStatus( request, '/wp-sitemap-users-1.xml', 200 );

		// The normal HTML template, not some other 200 -- see the file docblock.
		expect( response.headers()[ 'content-type' ] ).not.toContain( 'xml' );
	} );
} );

test.describe( 'filters: users sitemap override (dwpb_disable_user_sitemap)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_disable_user_sitemap: false,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'the users sitemap reappears in the index with neither natural condition touched', async ( {
		request,
	} ) => {
		const response = await request.get( '/wp-sitemap.xml' );

		expect( response.status() ).toBe( 200 );

		const body = await response.text();
		expect( body ).toContain( 'wp-sitemap-users-1.xml' );
	} );
} );
