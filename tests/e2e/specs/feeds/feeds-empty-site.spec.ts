/**
 * Main feed behaviour on a site with zero 'post' content.
 *
 * COVERAGE: the guard clause in `Disable_Blog_Public::disable_feed()` —
 * `isset( $post->post_type ) && 'post' === $post->post_type` — for the one
 * state every other feed spec deliberately avoids: no posts at all. With
 * zero posts, `WP_Query::get_posts()` never sets `$this->post`, so the
 * global `$post` `WP::register_globals()` copies onto the request is `null`.
 * `isset( $post->post_type )` on `null` is false, the guard fails, and core's
 * own feed template runs untouched — it renders a normal, valid, empty feed
 * instead of `disable_feed()` redirecting it. That is a genuine edge case in
 * how the plugin's post-type check degrades, not a bug: there is no post to
 * inspect, so the plugin correctly declines to act on one.
 *
 * ⚠️ ISOLATION BY DESIGN, AND WHY THAT IS SAFE: `beforeAll` calls
 * `POST /dwpb-test/v1/delete-all-posts` to wipe every 'post' in any status,
 * so the site genuinely has zero posts for test 1. Do NOT "optimize" that
 * wipe away as redundant with per-spec teardown — every other spec in this
 * suite seeds its own uniquely-titled content via `uniqueTitle()` and tears
 * it down again in its own `afterAll`/`afterEach` (see `redirects.spec.ts`
 * and `feeds.spec.ts`), so no other spec's fixtures are left behind for this
 * wipe to disturb, and this file leaves the site exactly as it found it
 * (test 2 below re-seeds and proves the redirect is restored). Removing the
 * wipe would make test 1 vacuous — it would pass or fail depending on
 * whatever content some other spec happened to leave behind, rather than
 * proving the actual zero-posts code path.
 *
 * REQUEST LAYER, NOT NAVIGATION: see the docblock in `config/redirects.ts`.
 *
 * ANONYMOUS CONTEXT: the whole describe block runs with an empty
 * `storageState`; the worker-scoped `requestUtils` fixture stays
 * admin-authenticated regardless, so `beforeAll`/`afterAll` can still wipe
 * and seed content.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, seedPost, deletePosts, deleteAllPosts, uniqueTitle } from '../../config/seed';
import { expectRedirect } from '../../config/redirects';

test.describe( 'feeds: empty site', () => {
	// Public-facing behaviour — every request in this block is anonymous. The
	// worker-scoped requestUtils fixture is unaffected (see the file docblock).
	test.use( { storageState: { cookies: [], origins: [] } } );

	let homeUrl: string;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		homeUrl = config.homeUrl;

		// Wipe every 'post', in any status. See the file docblock for why
		// this is safe and necessary rather than dead weight.
		await deleteAllPosts( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Best-effort — deletePosts() never throws, see its docblock in
		// config/seed.ts. Cleans up whatever test 2 seeded, so this file
		// leaves the site exactly as it found it: zero posts.
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'with no posts the main feed renders empty instead of redirecting', async ( {
		request,
	} ) => {
		const response = await request.get( '/feed/', { maxRedirects: 0 } );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );

		const body = await response.text();

		expect( body ).not.toContain( '<item>' );
	} );

	test( 'seeding a post restores the redirect', async ( { requestUtils, request } ) => {
		// Doubles as proof this file cleaned up after itself: if the earlier
		// wipe had left some stray post behind, or this test's own teardown
		// failed to run, a later spec's own "at least one post" assumption
		// (see feeds.spec.ts's docblock) would break silently. Asserting the
		// redirect comes back here catches that regression directly instead
		// of leaving it for whatever spec happens to run next.
		const seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'empty-site restore post' ),
		} );
		seededIds.push( seededPost.id );

		await expectRedirect( request, '/feed/', homeUrl );
	} );
} );
