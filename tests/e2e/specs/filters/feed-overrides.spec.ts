/**
 * Two independent feed filters, via the generic filter-override mechanism --
 * neither is exposed by a fixture toggle already.
 *
 * `dwpb_disable_feed` gates `disable_feeds()`'s "should this feed be
 * disabled at all" check; forced false, `disable_feed()`'s guard clause never
 * matches, so no redirect fires and core renders the feed itself.
 *
 * `dwpb_redirect_feeds` supplies the redirect target once a feed IS disabled;
 * forced to a same-host URL (`wp_safe_redirect()` drops an off-site target --
 * see filters/filter-overrides.spec.ts), `/feed/` redirects there instead of
 * `home_url()`.
 *
 * Control for both: feeds/feeds.spec.ts asserts the shipped defaults --
 * `/feed/` 301s to `home_url()`.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig } from '../../config/seed';
import { expectRedirect, expectStatus } from '../../config/redirects';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

// Same host as home_url(): wp_safe_redirect() rejects an off-site Location.
const OVERRIDE_PATH = '/dwpb-test-filter-override-feed/';

test.describe( 'filters: feed disable override (dwpb_disable_feed)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, { dwpb_disable_feed: false } );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( '/feed/ renders instead of redirecting', async ( { request } ) => {
		const response = await expectStatus( request, '/feed/', 200 );

		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );
} );

test.describe( 'filters: feed redirect target override (dwpb_redirect_feeds)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let overrideUrl: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		overrideUrl = `${ config.homeUrl }${ OVERRIDE_PATH }`;

		await setFilterOverrides( requestUtils, {
			dwpb_redirect_feeds: overrideUrl,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( '/feed/ redirects to the overridden URL instead of home_url()', async ( {
		request,
	} ) => {
		await expectRedirect( request, '/feed/', overrideUrl );
	} );
} );
