/**
 * Front-end redirect mechanics: the filters `Disable_Blog_Functions::redirect()`
 * and `Disable_Blog_Public::redirect_public_pages()` expose, exercised via the
 * `dwpb-test-redirects.php` mu-plugin fixture rather than the plugin's shipped
 * defaults.
 *
 * COVERAGE:
 *  - `dwpb_redirect_front_end` — the kill switch that turns the whole
 *    front-end redirect off (test 1).
 *  - `dwpb_redirect_status_code` — overrides the redirect's HTTP status, but
 *    only within the 300-399 range; `Disable_Blog_Functions::get_redirect_status_code()`
 *    clamps anything outside that range back to 301 (tests 2 and 3).
 *  - `dwpb_pass_query_string_on_redirect` + `dwpb_allowed_query_vars` —
 *    together they carry an allow-listed subset of the original request's
 *    query string onto the redirect target; both default off, so with neither
 *    set the query string is simply dropped (see `redirects.spec.ts`'s
 *    "the query string is dropped on redirect" test for that default). Here
 *    the fixture turns passthrough on and allow-lists exactly `utm_source`
 *    (test 4).
 *  - `dwpb_front_end_redirect_url` — overrides the redirect target itself,
 *    independent of which `$public_redirects` branch matched (test 5).
 *
 * This is the one spec in Phase 2 that talks to `dwpb-test-redirects.php`; see
 * that file's docblock for the full option -> filter map.
 *
 * REQUEST LAYER, NOT NAVIGATION: see `redirects.spec.ts`'s docblock for why
 * every assertion goes through `expectRedirect()` / `expectStatus()` rather
 * than `page.goto()`.
 *
 * ANONYMOUS CONTEXT: same as `redirects.spec.ts` — the describe block runs
 * with an empty `storageState`, while the worker-scoped `requestUtils`
 * fixture stays admin-authenticated for seeding and fixture toggling.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, seedPost, deletePosts, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { expectRedirect, expectStatus } from '../../config/redirects';
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

/**
 * Landing path the `customRedirectUrl` fixture toggle points at. Must match
 * `DWPB_TEST_REDIRECT_LANDING_PATH` in `dwpb-test-redirects.php` verbatim —
 * kept here as a named constant, rather than inlined in the test, so the two
 * files' literals are easy to compare and cannot silently drift.
 */
const LANDING_PATH = '/dwpb-test-landing/';

/**
 * Toggles this spec uses, reset together in `afterEach` so a failing
 * assertion mid-test can never leak a fixture into the next test.
 */
const TOGGLES_USED = [
	FIXTURE_TOGGLES.frontEndRedirectsOff,
	FIXTURE_TOGGLES.redirectStatusCode,
	FIXTURE_TOGGLES.queryStringPassthrough,
	FIXTURE_TOGGLES.customRedirectUrl,
];

test.describe( 'frontend: redirect mechanics (fixture-driven)', () => {
	// Public-facing behaviour — every request in this block is anonymous.
	// The worker-scoped `requestUtils` fixture stays admin-authenticated
	// regardless (see the file docblock), so seeding/fixture toggling still works.
	test.use( { storageState: { cookies: [], origins: [] } } );

	let frontPageUrl: string;
	let homeUrl: string;
	let seededPost: SeededPost;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		frontPageUrl = config.frontPageUrl;
		homeUrl = config.homeUrl;

		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'redirect mechanics post' ),
		} );
		seededIds.push( seededPost.id );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetFixtures( requestUtils, TOGGLES_USED );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Best-effort — deletePosts() never throws, see its docblock.
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'the front-end kill switch disables redirects', async ( {
		request,
		requestUtils,
	} ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.frontEndRedirectsOff ]: true,
		} );

		// Proves the page actually rendered, not merely that the redirect
		// vanished (e.g. a bug that turned the redirect into a silent 404
		// would also produce "no Location header", but not a 200 with the
		// post's own title in the body).
		await expectStatus( request, seededPost.permalink, 200 );

		const response = await request.get( seededPost.permalink );
		const body = await response.text();

		expect( body ).toContain( seededPost.title );
	} );

	test( 'the status code filter is honored', async ( { request, requestUtils } ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.redirectStatusCode ]: 302,
		} );

		await expectRedirect( request, seededPost.permalink, frontPageUrl, 302 );
	} );

	test( 'an out-of-range status code clamps back to 301', async ( {
		request,
		requestUtils,
	} ) => {
		// 200 is outside the 300-399 range Disable_Blog_Functions::redirect()
		// requires (via get_redirect_status_code()), so the plugin falls back
		// to its own default of 301 rather than passing 200 through to
		// wp_safe_redirect() (which would itself reject a non-3xx code).
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.redirectStatusCode ]: 200,
		} );

		await expectRedirect( request, seededPost.permalink, frontPageUrl, 301 );
	} );

	test( 'query-string passthrough honors the allowlist', async ( {
		request,
		requestUtils,
	} ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.queryStringPassthrough ]: true,
		} );

		// Disable_Blog_Functions::parse_query_string() intersects the
		// incoming query string against dwpb_allowed_query_vars (here fixed
		// to [ 'utm_source' ] by the fixture) and only then add_query_arg()s
		// the survivors onto the redirect target. `evil` is not allow-listed,
		// so it must be dropped while `utm_source` survives verbatim.
		await expectRedirect(
			request,
			`${ seededPost.permalink }?utm_source=x&evil=1`,
			`${ frontPageUrl }?utm_source=x`
		);
	} );

	test( 'the custom front-end redirect URL filter is honored', async ( {
		request,
		requestUtils,
	} ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.customRedirectUrl ]: true,
		} );

		// home_url() carries no trailing slash (see the file docblock in
		// redirects.ts on this suite's trailing-slash convention), so the
		// expected target is the concatenation, not a joined/normalized path.
		await expectRedirect( request, seededPost.permalink, `${ homeUrl }${ LANDING_PATH }` );
	} );
} );
