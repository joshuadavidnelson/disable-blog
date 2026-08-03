/**
 * Main feed behaviour on a site with zero 'post' content.
 *
 * COVERAGE: `Disable_Blog_Public::disable_feed()` now gates on
 * `is_post_feed_request()`, a query-based check of `$wp->query_vars` (see
 * that method's docblock, DEFECT N1) rather than on the global `$post`. That
 * replaced an older `isset( $post->post_type ) && 'post' === $post->post_type`
 * guard which depended on `WP_Query::get_posts()` having actually populated
 * `$this->post` — something it never does when a query returns zero results.
 * With no posts at all, the old guard silently failed and let core's own feed
 * template render a normal, valid, empty feed instead of being redirected.
 * That was an accident of checking post *content* to decide whether to
 * disable a *request* — this file now proves the corrected behaviour: the
 * disable decision is based on what the request asked for, not on how many
 * posts happen to exist, so `/feed/` is disabled (redirected) regardless of
 * post count, including on a genuinely empty site. For a plugin whose whole
 * purpose is disabling the blog, an empty 200 feed was never the intended
 * outcome — a 301 to the home URL is.
 *
 * ⚠️ ISOLATION BY DESIGN, AND WHY THAT IS SAFE: `beforeAll` calls
 * `POST /dwpb-test/v1/delete-all-posts` to wipe every 'post' in any status,
 * so the site genuinely has zero posts for test 1. Do NOT "optimize" that
 * wipe away as redundant with per-spec teardown — every other spec in this
 * suite seeds its own uniquely-titled content via `uniqueTitle()` and tears
 * it down again in its own `afterAll`/`afterEach` (see `redirects.spec.ts`
 * and `feeds.spec.ts`), so no other spec's fixtures are left behind for this
 * wipe to disturb, and this file leaves the site exactly as it found it
 * (test 2 below re-seeds and proves the redirect still holds with a post
 * present). Removing the wipe would make test 1 vacuous — it would pass or
 * fail depending on whatever content some other spec happened to leave
 * behind, rather than proving the actual zero-posts code path.
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
import { test } from '@wordpress/e2e-test-utils-playwright';

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

	test( 'with zero posts the main feed still redirects to the home URL', async ( {
		request,
	} ) => {
		// Regression guard: proves the fix keys off the request (via
		// is_post_feed_request()'s query-based check), not off post count.
		// The old $post-based guard degraded exactly here — zero posts meant
		// $post was never populated, so it silently let core's feed template
		// render a normal empty 200 feed instead of redirecting. If a future
		// change reintroduces a content-based check, this is the test that
		// would catch it going back to that accidental behaviour.
		await expectRedirect( request, '/feed/', homeUrl );
	} );

	test( 'seeding a post does not disturb the redirect', async ( { requestUtils, request } ) => {
		// Doubles as proof this file cleaned up after itself: if the earlier
		// wipe had left some stray post behind, or this test's own teardown
		// failed to run, a later spec's own "at least one post" assumption
		// (see feeds.spec.ts's docblock) would break silently. Asserting the
		// redirect still holds here catches that regression directly instead
		// of leaving it for whatever spec happens to run next.
		const seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'empty-site restore post' ),
		} );
		seededIds.push( seededPost.id );

		await expectRedirect( request, '/feed/', homeUrl );
	} );
} );
