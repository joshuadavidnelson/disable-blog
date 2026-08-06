/**
 * `dwpb_remove_pingback_header`, via the generic filter-override mechanism.
 *
 * Two call sites strip the X-Pingback header: `filter_wp_headers()` on
 * `wp_headers`, and `remove_pingback_header_fallback()` on `wp` (added to
 * catch core < 6.2, which sent X-Pingback via a direct `header()` call in
 * `WP::handle_404()` that bypassed `wp_headers` entirely).
 *
 * On the core version this suite runs against, `WP::send_headers()` only
 * ever adds X-Pingback for `is_singular()` requests, and `handle_404()`
 * makes no `header()` call of its own -- so no request can isolate the
 * fallback from `filter_wp_headers()`; the single-page test below covers
 * both call sites at once.
 *
 * Control: frontend/search-and-head.spec.ts asserts the header is stripped
 * at the filter's shipped default (`true`).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { createContentTracker } from '../../config/content-tracker';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

test.describe( 'filters: X-Pingback header override (dwpb_remove_pingback_header)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let pageWithPingsOpen: SeededPost;

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		// pingStatus 'open' so pings_open() is true and core would set
		// X-Pingback absent the plugin's filter.
		pageWithPingsOpen = await content.seedPage( requestUtils, {
			title: uniqueTitle( 'pingback header override page' ),
			pingStatus: 'open',
		} );

		await setFilterOverrides( requestUtils, {
			dwpb_remove_pingback_header: false,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
		await content.cleanup( requestUtils );
	} );

	test( 'the X-Pingback header survives on a normal single-page request', async ( {
		request,
	} ) => {
		const response = await request.get( pageWithPingsOpen.permalink );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'x-pingback' ] ).toContain( 'xmlrpc.php' );
	} );
} );
