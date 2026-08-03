/**
 * Front-end feed behaviour, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Public::disable_feed()`, hooked at priority 1 onto
 * `do_feed`, `do_feed_rdf`, `do_feed_rss`, `do_feed_rss2`, and `do_feed_atom`
 * (see `Disable_Blog::define_public_hooks()`), with two accepted args so it
 * receives the `$is_comment_feed` flag core passes. It only acts when
 * `isset( $post->post_type ) && 'post' === $post->post_type` — i.e. when the
 * global `$post` WordPress resolved for the current feed query is itself a
 * 'post'. This spec exercises that function with every plugin filter left at
 * its shipped default (no `dwpb_disable_feed`, `dwpb_redirect_feeds`, or
 * `dwpb_feed_message` override), so the behaviour proven here is exactly what
 * a fresh install does out of the box.
 *
 * WHY `$post` IS POPULATED FOR A NON-SINGULAR FEED QUERY: this is the one
 * piece of core behaviour every test below leans on, so it is worth stating
 * once here instead of in every test. `WP_Query::get_posts()` sets
 * `$this->post = reset( $this->posts )` whenever the query returns any posts
 * at all — not only for singular queries — and `WP::register_globals()`
 * copies that onto the global `$post`. So a request for the site's main feed
 * (a list query, not a singular one) still populates `$post` with the most
 * recent published post, and `disable_feed()`'s `'post' === $post->post_type`
 * check sees it. That is why this file's `beforeAll` seeds at least one
 * published post: without one, `$post` is null and the main-feed tests below
 * would silently degenerate into the empty-feed edge case covered by
 * `feeds-empty-site.spec.ts` instead of proving the redirect. Do not delete
 * that seeded post as "unused" — every test in the first block depends on it
 * existing, even the ones that never reference it by variable name.
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
 * 🚫 QUERY-STRING FEED URLS (`/?feed=rss2` and friends) ARE DELIBERATELY NOT
 * COVERED HERE: `disable_feed()` guards on
 * `isset( $post->post_type ) && 'post' === $post->post_type`. With a static
 * front page, the global `$post` WordPress resolves for a query-string feed
 * request is the Home *page*, not a post, so the guard bails and core renders
 * the feed — leaking real post content (a `200` with an `<item>` containing
 * the post title) instead of redirecting. That is a real, verified content-
 * leak defect, scheduled to be fixed in a later phase. Only the
 * pretty-permalink feed forms (`/feed/`, `/feed/rss2/`, ...) are asserted
 * below, which redirect correctly today. Do not add query-string feed
 * coverage back until that defect is fixed — it will fail.
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

		// Load-bearing — see the file docblock's "WHY $post IS POPULATED"
		// section. Every main-feed test below depends on $post resolving to
		// a 'post', which requires at least one published post to exist.
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
		// Pretty-permalink forms only — see the file docblock's "QUERY-STRING
		// FEED URLS" note for why the `/?feed=...` query-string form is
		// deliberately excluded (a known, separately-tracked content-leak
		// defect, not an oversight here).
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
} );
