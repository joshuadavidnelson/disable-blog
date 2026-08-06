/**
 * `dwpb_remove_pingback_header`, via the generic filter-override mechanism.
 *
 * Two call sites strip the X-Pingback header: `filter_wp_headers()` on the
 * `wp_headers` filter (fires while `WP::send_headers()` builds the header
 * array) and `remove_pingback_header_fallback()` on `wp` (fires after
 * `send_headers()`, on every request, not just 404s -- per `WP::main()`'s
 * hook order: `handle_404()` -> `send_headers()` -> the `'wp'` action). The
 * fallback exists for core < 6.2, where `WP::handle_404()` sent X-Pingback
 * via a direct `header()` call that ran after `wp_headers` and so escaped
 * `filter_wp_headers()`.
 *
 * On the core version this suite runs against, `WP::send_headers()` only
 * ever adds X-Pingback for `is_singular()` requests with pings open, and
 * `WP::handle_404()` makes no `header()` call of its own. A 404 therefore
 * never carries the header, with or without this filter -- there is no
 * request that isolates `remove_pingback_header_fallback()`'s branch from
 * `filter_wp_headers()`'s, so the single-page test below is the
 * load-bearing coverage for both call sites (`remove_pingback_header_fallback()`
 * also runs on that same request, redundantly with `filter_wp_headers()`).
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
		// pingStatus 'open' on a page so pings_open() is true and core would
		// set X-Pingback absent the plugin's filter (mirrors
		// frontend/search-and-head.spec.ts).
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
