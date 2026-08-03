/**
 * Front-end 301 redirect behaviour, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Public::redirect_public_pages()`, hooked on
 * `template_redirect`, is the single function behind every front-end
 * redirect this plugin performs. It walks a fixed list of "public page"
 * checks (singular post, tag archive, category archive, the posts page,
 * date archive, author archive) and, on the first match, 301s to
 * `get_permalink( page_on_front ) `. This spec exercises that function with
 * every plugin filter left at its shipped default — no
 * `dwpb_redirect_front_end`, `dwpb_disable_author_archives`,
 * `dwpb_pass_query_string_on_redirect`, or `dwpb_allowed_query_vars`
 * override — so the behaviour proven here is exactly what a fresh install
 * does out of the box. Filter-driven variations belong in later Phase 2
 * specs, not this one.
 *
 * GATING CONDITIONS THAT SHAPE THIS SPEC's DEFAULT STATE:
 *  - `dwpb_disable_author_archives` defaults to `false`, so author archives
 *    are deliberately NOT part of the `$public_redirects` match today (see
 *    test 15 below) — that is documented default behaviour, not a bug.
 *  - `dwpb_pass_query_string_on_redirect` defaults to `false` and
 *    `dwpb_allowed_query_vars` defaults to an empty array, so any query
 *    string on the original request is dropped, never carried onto the
 *    redirect target (see test 17 below).
 *
 * REQUEST LAYER, NOT NAVIGATION: every assertion goes through
 * `expectRedirect()` / `expectStatus()` / `expectNoRedirect()` from
 * `config/redirects.ts`, which issue a single `request.get()` with
 * redirect-following disabled. See that file's docblock for why: Chromium
 * caches 301s hard enough that a `page.goto()`-based assertion could observe
 * a stale cached redirect instead of the live one.
 *
 * ANONYMOUS CONTEXT: these are public-facing behaviours a signed-out visitor
 * hits, so the whole describe block runs with an empty `storageState`. The
 * worker-scoped `requestUtils` fixture is unaffected by that — it always
 * authenticates via `STORAGE_STATE_PATH` (the admin state; see
 * `playwright.config.ts` and `@wordpress/e2e-test-utils-playwright`'s
 * `requestUtils` fixture) regardless of what a test's `storageState` is set
 * to — so `beforeAll`/`afterAll` below can still seed and tear down content
 * as an administrator while every in-test `request` call is anonymous.
 *
 * Content is seeded once in `beforeAll` and read-only for the rest of the
 * spec — no test here mutates seeded content or plugin options — so a single
 * shared fixture set is safe across all 17 tests. The one exception is
 * `posts_per_page`, which `beforeAll`/`afterAll` deliberately read and
 * restore around the pagination test — see the comment there for why.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	siteConfig,
	seedPost,
	seedPage,
	seedTerm,
	deletePosts,
	uniqueTitle,
} from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { expectRedirect, expectStatus, expectNoRedirect } from '../../config/redirects';

/**
 * Fixed, known post date for the seeded post, so the year/month archive URLs
 * in tests 5 and 6 are deterministic instead of depending on "today".
 */
const POST_YEAR = '2022';
const POST_MONTH = '03';
const POST_DATE = `${ POST_YEAR }-${ POST_MONTH }-14 09:30:00`;

test.describe( 'frontend: redirects (default state)', () => {
	// Public-facing behaviour — every request in this block is anonymous.
	// The worker-scoped `requestUtils` fixture stays admin-authenticated
	// regardless (see the file docblock), so seeding in beforeAll still works.
	test.use( { storageState: { cookies: [], origins: [] } } );

	let frontPageUrl: string;
	let seededPost: SeededPost;
	let secondPost: SeededPost;
	let seededPage: SeededPost;
	let tagSlug: string;

	// The core `posts_per_page` reading setting, as it stood before this spec
	// forced it down to 1 — see the comment in beforeAll below for why, and
	// afterAll for the restore.
	let originalPostsPerPage: number | undefined;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		frontPageUrl = config.frontPageUrl;

		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'redirects post' ),
			postDate: POST_DATE,
		} );
		seededIds.push( seededPost.id );

		// A second published post purely so the blog has a real page 2 to
		// paginate to — see the posts_per_page comment just below.
		secondPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'redirects post 2' ),
			postDate: POST_DATE,
		} );
		seededIds.push( secondPost.id );

		seededPage = await seedPage( requestUtils, {
			title: uniqueTitle( 'redirects page' ),
		} );
		seededIds.push( seededPage.id );

		const term = await seedTerm( requestUtils, {
			taxonomy: 'post_tag',
			name: uniqueTitle( 'redirects tag' ),
			assignTo: seededPost.id,
		} );
		tagSlug = term.slug;

		// Force a real page 2 to exist. With the site's default
		// posts_per_page (10) and only two seeded posts, /blog/page/2/ is a
		// genuine 404: WordPress's main query runs handle_404() and flips
		// is_home() to false / is_404() to true *before* template_redirect
		// fires, so the plugin's blog_page branch — which tests is_home() —
		// never gets a chance to match. That is not a plugin bug, it's just
		// nothing to paginate. Do NOT delete this posts_per_page juggling as
		// dead weight; without it the pagination test below silently goes
		// back to asserting a 404 as if it were a 301.
		//
		// Read the current value first — never assume it's the default 10,
		// some other spec or environment could have changed it — and restore
		// exactly that value in afterAll.
		const settings = await requestUtils.rest< { posts_per_page: number } >( {
			path: '/wp/v2/settings',
		} );
		originalPostsPerPage = settings.posts_per_page;

		await requestUtils.rest( {
			method: 'PUT',
			path: '/wp/v2/settings',
			data: { posts_per_page: 1 },
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Restore posts_per_page independently of content teardown below, so
		// a failing deletePosts() call can never leave every later spec
		// paginating at 1 post per page. Guarded on originalPostsPerPage
		// actually having been read, in case beforeAll threw before it got
		// that far.
		if ( undefined !== originalPostsPerPage ) {
			await requestUtils.rest( {
				method: 'PUT',
				path: '/wp/v2/settings',
				data: { posts_per_page: originalPostsPerPage },
			} );
		}

		// Best-effort — deletePosts() never throws, see its docblock.
		await deletePosts( requestUtils, seededIds );
	} );

	/* -------------------------------------------------------------------
	 * Redirected: the plugin's `$public_redirects` matches
	 * ---------------------------------------------------------------- */

	test( 'a single post redirects to the front page', async ( { request } ) => {
		await expectRedirect( request, seededPost.permalink, frontPageUrl );
	} );

	test( 'a plain ?p=<id> permalink redirects to the front page', async ( {
		request,
	} ) => {
		// Same is_singular( 'post' ) branch as the pretty-permalink test just
		// above, exercised via the non-pretty query-var form instead. This is
		// the coverage a since-removed `?post_type=post` test was reaching
		// for; that query var gets stripped before WordPress ever builds a
		// query (see the leak-check test in the Controls section below), so
		// `?p=<id>` is the URL that actually proves a single post still
		// resolves and redirects with pretty permalinks off the table.
		//
		// Must be a real, seeded post id. `?p=` with an empty/nonexistent id
		// *also* 301s to the front page — collapsing to an empty query that
		// resolves as is_home() — so a test using a fake id would pass for
		// the wrong reason and prove nothing about is_singular( 'post' ).
		await expectRedirect( request, `/?p=${ seededPost.id }`, frontPageUrl );
	} );

	test( 'the posts page redirects to the front page', async ( { request } ) => {
		await expectRedirect( request, '/blog/', frontPageUrl );
	} );

	test( 'posts page pagination redirects to the front page', async ( { request } ) => {
		// Requires a real page 2 to exist, which the site's default
		// posts_per_page (10) can't give us from two seeded posts alone —
		// see the posts_per_page comment in beforeAll above. Without that
		// forcing, this URL 404s (nothing to paginate) rather than 301s, and
		// the plugin's blog_page branch never runs because WordPress's own
		// handle_404() has already flipped is_home() to false before
		// template_redirect fires.
		await expectRedirect( request, '/blog/page/2/', frontPageUrl );
	} );

	test( 'a year archive redirects to the front page', async ( { request } ) => {
		await expectRedirect( request, `/${ POST_YEAR }/`, frontPageUrl );
	} );

	test( 'a month archive redirects to the front page', async ( { request } ) => {
		await expectRedirect(
			request,
			`/${ POST_YEAR }/${ POST_MONTH }/`,
			frontPageUrl
		);
	} );

	test( 'a category archive redirects to the front page', async ( { request } ) => {
		await expectRedirect( request, '/category/uncategorized/', frontPageUrl );
	} );

	test( 'a tag archive redirects to the front page', async ( { request } ) => {
		await expectRedirect( request, `/tag/${ tagSlug }/`, frontPageUrl );
	} );

	test( 'a post embed URL redirects to the front page', async ( { request } ) => {
		await expectRedirect( request, `${ seededPost.permalink }embed/`, frontPageUrl );
	} );

	test( 'a post comment-page URL redirects to the front page', async ( {
		request,
	} ) => {
		await expectRedirect(
			request,
			`${ seededPost.permalink }comment-page-1/`,
			frontPageUrl
		);
	} );

	test( 'a post trackback URL redirects to the front page', async ( {
		request,
	} ) => {
		await expectRedirect(
			request,
			`${ seededPost.permalink }trackback/`,
			frontPageUrl
		);
	} );

	/* -------------------------------------------------------------------
	 * Controls: prove the redirect is targeted, not a blanket catch-all
	 * ---------------------------------------------------------------- */

	test( 'the front page itself is not redirected', async ( { request } ) => {
		// Control for the plugin's own redirect-loop guard in
		// Disable_Blog_Functions::redirect(): if $redirect_url === $current_url
		// it bails instead of 301-ing a page to itself.
		await expectStatus( request, '/', 200 );
	} );

	test( 'a regular page is not redirected', async ( { request } ) => {
		// Control: pages are not in $public_redirects at all — only 'post'
		// singulars, tag/category archives, the posts page, date archives,
		// and (conditionally) author archives are.
		await expectStatus( request, seededPage.permalink, 200 );
	} );

	test( 'a post_type=post query exposes no post archive', async ( {
		request,
	} ) => {
		// register_post_type() forces query_var to `false` on a post type
		// that is not publicly_queryable outside of admin, and Disable Blog
		// marks 'post' as such. WP::parse_request() intersects the incoming
		// `post_type` query var against the set of registered query_vars, so
		// `?post_type=post` gets stripped entirely rather than filtering the
		// query — the request collapses to an empty main query, which
		// resolves to the *static front page* (is_front_page() true,
		// is_home() false), not the plugin's blog_page branch. So this is
		// NOT a redirect — the front page renders directly at 200 — which
		// makes it a control, not a $public_redirects match: the only thing
		// worth proving is that no seeded post content leaks through it.
		await expectStatus( request, '/?post_type=post', 200 );

		const response = await request.get( '/?post_type=post' );
		const body = await response.text();

		expect( body ).not.toContain( seededPost.title );
		expect( body ).not.toContain( secondPost.title );
	} );

	test( 'an author archive is not redirected by default', async ( { request } ) => {
		// This looks like a bug but is documented default behaviour:
		// `dwpb_disable_author_archives` defaults to `false`
		// (Disable_Blog_Functions::disable_author_archives()), so the
		// 'author_archive' entry in $public_redirects evaluates to false and
		// author archives are deliberately left un-redirected out of the box.
		// wp-env's built-in administrator has the username 'admin'.
		await expectNoRedirect( request, '/author/admin/' );
	} );

	test( 'an unknown URL still returns 404', async ( { request } ) => {
		// Control: proves the plugin has not turned every request into a
		// redirect. A random, never-seeded slug so no other spec's content
		// could coincidentally make this URL resolve to something real.
		const unknownPath = `/${ uniqueTitle( 'no-such-page' ).replace( /\s+/g, '-' ) }/`;

		await expectStatus( request, unknownPath, 404 );
	} );

	/* -------------------------------------------------------------------
	 * Redirect target details
	 * ---------------------------------------------------------------- */

	test( 'the query string is dropped on redirect', async ( { request } ) => {
		// dwpb_pass_query_string_on_redirect defaults to false and
		// dwpb_allowed_query_vars defaults to an empty array, so the
		// Location header must be exactly frontPageUrl, with nothing from
		// the original request's query string carried across.
		// expectRedirect() compares the Location header exactly (no trailing
		// slash or query-string normalization), which is exactly what this
		// test needs to prove.
		await expectRedirect(
			request,
			`${ seededPost.permalink }?utm_source=x&foo=1`,
			frontPageUrl
		);
	} );
} );
