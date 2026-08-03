/**
 * Front-end feed behaviour, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Public::disable_feed()`, hooked at priority 1 onto
 * `do_feed`, `do_feed_rdf`, `do_feed_rss`, `do_feed_rss2`, and `do_feed_atom`
 * (see `Disable_Blog::define_public_hooks()`), with two accepted args so it
 * receives the `$is_comment_feed` flag core passes. As of 0.5.6 it no longer
 * decides whether to act by inspecting the global `$post`; it delegates to
 * the private `is_post_feed_request()`, which reads `$wp->query_vars` (the
 * raw, request-derived query vars populated once in `WP::parse_request()`)
 * to determine whether the current request is for the site's 'post' feed,
 * as opposed to a feed scoped to a specific singular object or another post
 * type — see that method's own docblock for why it deliberately avoids
 * `$post` and `$wp_query`. This spec exercises `disable_feed()` with every
 * plugin filter left at its shipped default (no `dwpb_disable_feed`,
 * `dwpb_redirect_feeds`, or `dwpb_feed_message` override), so the behaviour
 * proven here is exactly what a fresh install does out of the box.
 *
 * WHY THIS FILE SEEDS A POST: `is_post_feed_request()` decides purely from
 * the request's own query vars, so the main-feed redirect tests below don't
 * actually need a post to exist for the redirect logic itself — see
 * `feeds-empty-site.spec.ts`, which proves `/feed/` still redirects on a
 * site with zero posts. The seeded post here instead serves two narrower,
 * unrelated purposes: "a post's own feed redirects" needs a real permalink
 * to request, and the leak-specific assertion in the query-string block
 * needs a known title to confirm the response body does *not* contain. Do
 * not delete that seeded post as "unused" — those two tests depend on it,
 * even though most of the other tests in this block never reference it by
 * variable name.
 *
 * REQUEST LAYER, NOT NAVIGATION: redirect assertions go through
 * `expectRedirect()` from `config/redirects.ts`, for the same reason
 * `frontend/redirects.spec.ts` does — see that file's docblock. The
 * comment-feed tests inspect status/content-type directly off a plain
 * `request.get()` instead, since there is no redirect to assert there.
 *
 * ANONYMOUS CONTEXT: feeds are a public-facing surface, so the whole describe
 * block runs with an empty `storageState`. The worker-scoped `requestUtils`
 * fixture stays admin-authenticated regardless, so `beforeAll`/`afterAll` can
 * still seed and tear down content.
 *
 * REGRESSION GUARD, N1 (includes/class-disable-blog-public.php, see
 * `is_post_feed_request()`): query-string feed URLs (`/?feed=rss2` and
 * friends) are covered below, alongside the pretty-permalink forms. Before
 * the fix landed in this same PR, `disable_feed()` guarded on
 * `isset( $post->post_type ) && 'post' === $post->post_type`. With a static
 * front page, the global `$post` WordPress resolves for a query-string feed
 * request is the Home *page*, not a post, so that guard bailed and core
 * rendered the feed — leaking real post content (a `200` with an `<item>`
 * containing the post title) instead of redirecting. The fix replaced that
 * guard with `is_post_feed_request()`, which decides from the request's own
 * query vars instead of the global `$post`. The tests below now guard
 * against a regression back to the old behaviour: a `301` to `homeUrl`,
 * same as the pretty-permalink forms, plus a leak-specific assertion that
 * the response body never contains a seeded post's title, independent of
 * status code.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, seedPost, seedPage, deletePosts, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { expectRedirect } from '../../config/redirects';

test.describe( 'feeds: default state', () => {
	// Public-facing behaviour — every request in this block is anonymous. The
	// worker-scoped requestUtils fixture is unaffected (see the file docblock).
	test.use( { storageState: { cookies: [], origins: [] } } );

	let homeUrl: string;
	let frontPageUrl: string;
	let seededPost: SeededPost;
	let seededPage: SeededPost;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		homeUrl = config.homeUrl;
		frontPageUrl = config.frontPageUrl;

		// See the file docblock's "WHY THIS FILE SEEDS A POST" section — this
		// is load-bearing for "a post's own feed redirects" (needs a real
		// permalink) and the query-string leak assertion below (needs a
		// known title), not for the redirect logic itself.
		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'feeds post' ),
		} );
		seededIds.push( seededPost.id );

		// Control content for the comment-feed tests: comments are supported
		// by the 'page' post type by default, which is what makes the
		// site-wide and per-page comment feeds render instead of redirect.
		seededPage = await seedPage( requestUtils, {
			title: uniqueTitle( 'feeds page' ),
		} );
		seededIds.push( seededPage.id );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'the main feed redirects to the home URL', async ( { request } ) => {
		// disable_feed() redirects to home_url() (no trailing slash), NOT to
		// get_permalink( page_on_front ) (frontPageUrl, WITH a trailing
		// slash) the way frontend/redirects.spec.ts's page redirects do. That
		// is a real, intentional difference between the two redirect
		// targets, not an inconsistency this suite is glossing over — see
		// the docblock in `config/redirects.ts` for why both are asserted
		// verbatim, with no trailing-slash normalization either direction.
		await expectRedirect( request, '/feed/', homeUrl );
	} );

	test( "a post's own feed redirects", async ( { request } ) => {
		// This one is NOT disable_feed() at work. `template_redirect` (which
		// runs redirect_public_pages()) fires unconditionally before
		// template-loader.php ever checks is_feed() and calls do_feed() —
		// see wp-includes/template-loader.php. A single post's own feed URL
		// resolves as both is_singular( 'post' ) AND is_feed() at once, and
		// $post is already populated by that point (WP::register_globals()
		// runs before template_redirect fires). So
		// redirect_public_pages()'s 'post' branch matches first and 301s to
		// the front page (frontPageUrl, WITH the trailing slash) before
		// do_feed()/disable_feed() ever get a chance to run. Do not "fix"
		// this to expect homeUrl — that would be asserting the wrong
		// function's behaviour.
		await expectRedirect( request, `${ seededPost.permalink }feed/`, frontPageUrl );
	} );

	test( 'feed format variants all redirect', async ( { request } ) => {
		// Pretty-permalink forms only — the `/?feed=...` query-string form has
		// its own coverage in the "query-string feed URLs" block below, see
		// the file docblock's "REGRESSION GUARD, N1" note.
		const feedPaths = [ '/feed/', '/feed/rss2/', '/feed/atom/', '/feed/rdf/' ];

		for ( const feedPath of feedPaths ) {
			await expectRedirect( request, feedPath, homeUrl );
		}
	} );

	test( 'the site comments feed still renders', async ( { request } ) => {
		// disable_feed()'s very first check: if this is a comment feed and
		// another post type supports the 'comments' feature, it bails
		// before ever looking at $post. 'page' supports comments by default
		// in a stock install (dwpb_post_types_with_feature( 'comments' ) is
		// truthy), so the site-wide comments feed is left alone.
		const response = await request.get( '/comments/feed/', { maxRedirects: 0 } );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );

	test( 'a page comment feed still renders', async ( { request } ) => {
		// Control for the test above: a real per-page comment feed, proving
		// the "other post types support comments" bail-out isn't specific to
		// the site-wide comments feed URL.
		const response = await request.get( `${ seededPage.permalink }feed/`, {
			maxRedirects: 0,
		} );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );

	/* -------------------------------------------------------------------
	 * Query-string feed URLs — regression guard for N1
	 * ---------------------------------------------------------------- */

	test( 'a query-string feed URL redirects to the home URL', async ( {
		request,
	} ) => {
		// Regression guard for N1 — see the file docblock. Before the fix
		// landed in this PR, disable_feed() guarded on
		// 'post' === $post->post_type, and with a static front page $post
		// resolves to the Home page (not a post) for a query-string feed
		// request, so that guard bailed and this 200'd with real feed
		// content instead of redirecting.
		await expectRedirect( request, '/?feed=rss2', homeUrl );
	} );

	test( 'every query-string feed format redirects to the home URL', async ( {
		request,
	} ) => {
		const feedFormats = [ 'feed', 'rdf', 'rss2', 'atom' ];

		for ( const format of feedFormats ) {
			await expectRedirect( request, `/?feed=${ format }`, homeUrl );
		}
	} );

	test( 'a query-string feed URL never leaks post content', async ( {
		request,
	} ) => {
		// The assertion that actually encodes "no content leak", independent
		// of status code — a redirect response that somehow still emitted a
		// body containing the post would still be a leak, which the two
		// tests above (Location-header only) would not catch.
		const response = await request.get( '/?feed=rss2', { maxRedirects: 0 } );
		const body = await response.text();

		expect( body ).not.toContain( seededPost.title );
	} );
} );
