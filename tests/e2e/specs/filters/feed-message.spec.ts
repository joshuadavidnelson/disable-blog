/**
 * The `dwpb_feed_message` / `dwpb_feed_die_message` filter pair, via
 * `setFilterOverrides()`. Once `dwpb_feed_message` is truthy, `disable_feed()`
 * calls `wp_die()` with a message run through `dwpb_feed_die_message`,
 * instead of redirecting.
 *
 * No seeded content needed: `is_post_feed_request()` reads request query vars
 * rather than the global `$post`, so a bare `/feed/` reads as a 'post' feed
 * request regardless of whether any post exists.
 *
 * The exact status code (500, wp_die()'s default when no XML-recognized
 * Accept/Content-Type header is sent) is asserted on its own line, separate
 * from the "not a redirect" check, so it's the one line to adjust if wrong.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

const DWPB_TEST_FEED_DIE_MESSAGE_LITERAL = 'DWPB test fixture: feed die message.';

test.describe( 'filters: feed die message (dwpb_feed_message / dwpb_feed_die_message)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_feed_message: true,
			dwpb_feed_die_message: DWPB_TEST_FEED_DIE_MESSAGE_LITERAL,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'the feed dies with the custom message instead of redirecting', async ( {
		request,
	} ) => {
		const response = await request.get( '/feed/', { maxRedirects: 0 } );
		const status = response.status();

		expect(
			status >= 300 && status < 400,
			`expected a non-redirect status for /feed/, got ${ status }`
		).toBe( false );

		expect( status ).toBe( 500 );

		const body = await response.text();
		expect( body ).toContain( DWPB_TEST_FEED_DIE_MESSAGE_LITERAL );
	} );

	test( 'comment feeds are unaffected', async ( { request } ) => {
		// Control: disable_feed() bails on comment feeds before dwpb_feed_message
		// is ever consulted, since 'page' supports comments by default here.
		const response = await request.get( '/comments/feed/', { maxRedirects: 0 } );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );
} );
