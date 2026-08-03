/**
 * The `dwpb_feed_message` / `dwpb_feed_die_message` filter pair, exercised
 * via the `dwpb-test-feeds.php` mu-plugin fixture rather than the plugin's
 * shipped default (`dwpb_feed_message` returns `false`, so a disabled feed
 * always redirects -- see `feeds/feeds.spec.ts` for that default-state
 * coverage).
 *
 * COVERAGE: `Disable_Blog_Public::disable_feed()`
 * (includes/class-disable-blog-public.php), once `dwpb_feed_message` returns
 * truthy, builds a message, runs it through `dwpb_feed_die_message`, and
 * calls `wp_die()` with the result INSTEAD OF calling
 * `Disable_Blog_Functions::redirect()`. The fixture forces both filters at
 * once: `dwpb_feed_message` to `true`, and `dwpb_feed_die_message` to the
 * fixed literal `DWPB_TEST_FEED_DIE_MESSAGE_LITERAL` below -- which MUST
 * match `DWPB_TEST_FEED_DIE_MESSAGE` in `dwpb-test-feeds.php` verbatim, kept
 * here as a named constant (rather than inlined in the test) so the two
 * files' literals are easy to compare and cannot silently drift, the same
 * convention `redirect-mechanics.spec.ts`'s `LANDING_PATH` follows for
 * `dwpb-test-redirects.php`.
 *
 * NO SEEDED CONTENT NEEDED: `disable_feed()` gates on
 * `Disable_Blog_Public::is_post_feed_request()`, which reads the raw
 * request-derived query vars (`$wp->query_vars`) rather than the global
 * `$post` -- see that method's docblock (DEFECT N1's fix). A bare `/feed/`
 * request has no singular query var and no `post_type` query var, so it
 * reads as a 'post' feed request regardless of whether any post exists.
 * Unlike `feeds/feeds.spec.ts`, this file's tests do not depend on seeded
 * content.
 *
 * ⚠️ WHY THE EXACT STATUS CODE IS ASSERTED ON ITS OWN LINE: `wp_die()` with
 * no explicit `response` arg defaults to `500` (`_wp_die_process_input()`'s
 * `$defaults` in `wp-includes/functions.php`), UNLESS WordPress dispatches to
 * a different die handler first -- `wp_is_xml_request()` would route to
 * `_xml_wp_die_handler()` instead if the request carried an `Accept`/
 * `Content-Type` header WordPress recognizes as XML, which this plain
 * `request.get( '/feed/' )` does not send. That reasoning was not verified
 * against a live response (this task runs no wp-env/Playwright), so `500` is
 * this file's best-evidence assumption, not a confirmed value -- it is
 * isolated onto its own assertion, separate from the "not a redirect" check,
 * so it is the one line to touch if a live run says otherwise.
 *
 * ANONYMOUS CONTEXT: feeds are a public-facing surface, so the whole describe
 * block runs with an empty `storageState`, same as `feeds/feeds.spec.ts`. The
 * worker-scoped `requestUtils` fixture stays admin-authenticated regardless
 * (see that file's docblock), so fixture toggling still works.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

/**
 * Must match `DWPB_TEST_FEED_DIE_MESSAGE` in `dwpb-test-feeds.php` verbatim
 * -- see the file docblock.
 */
const DWPB_TEST_FEED_DIE_MESSAGE_LITERAL = 'DWPB test fixture: feed die message.';

test.describe( 'filters: feed die message (dwpb_feed_message / dwpb_feed_die_message)', () => {
	// Public-facing behaviour -- every request in this block is anonymous.
	// The worker-scoped requestUtils fixture is unaffected (see the file
	// docblock).
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.beforeAll( async ( { requestUtils } ) => {
		await setFixtures( requestUtils, { [ FIXTURE_TOGGLES.feedDieMessage ]: true } );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.feedDieMessage ] );
	} );

	test( 'the feed dies with the custom message instead of redirecting', async ( {
		request,
	} ) => {
		const response = await request.get( '/feed/', { maxRedirects: 0 } );
		const status = response.status();

		// Not a redirect -- proves dwpb_feed_message genuinely short-circuited
		// disable_feed()'s normal Disable_Blog_Functions::redirect() branch,
		// independent of exactly which non-redirect status wp_die() used.
		expect(
			status >= 300 && status < 400,
			`expected a non-redirect status for /feed/, got ${ status }`
		).toBe( false );

		// See the file docblock's "WHY THE EXACT STATUS CODE..." note -- on
		// its own line, easy to adjust in isolation.
		expect( status ).toBe( 500 );

		const body = await response.text();
		expect( body ).toContain( DWPB_TEST_FEED_DIE_MESSAGE_LITERAL );
	} );

	test( 'comment feeds are unaffected', async ( { request } ) => {
		// Control: disable_feed()'s very first check bails for comment feeds
		// whenever another post type supports comments -- true by default in
		// this environment ('page'/'attachment') -- BEFORE dwpb_feed_message is
		// ever consulted. This proves the fixture, left on, does not leak into
		// a feed disable_feed() was never going to touch in the first place.
		const response = await request.get( '/comments/feed/', { maxRedirects: 0 } );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );
} );
