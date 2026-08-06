/**
 * Front-end feed behaviour (`Disable_Blog_Public::disable_feed()`), every
 * filter at its shipped default. Assertions run request-layer via
 * expectRedirect(), same reasoning as frontend/redirects.spec.ts.
 *
 * @since 0.5.5 Query-string feed URLs (`/?feed=rss2`) redirect too, alongside
 * a leak-specific body check below. Previously `disable_feed()` guarded on
 * the global `$post` being a 'post', which is the Home page rather than a
 * post for a query-string feed request, so real post content leaked. It now
 * delegates to `is_post_feed_request()`, which reads `$wp->query_vars`
 * instead.
 *
 * `is_post_feed_request()` excludes singular query vars (`p`/`name`/etc.) but
 * not `category_name`/`tag`/`author_name`, so term and author feeds are swept
 * into the disabled-feed redirect too. The second describe block below
 * covers the singular-exclusion branch itself, with `redirect_public_pages()`
 * switched off (via a `dwpb_redirect_front_end` override) so the request
 * actually reaches `disable_feed()`. The third isolates the same sweep for a
 * tag feed specifically, since `redirect_public_pages()` otherwise wins that
 * race first and masks it.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { createContentTracker } from '../../config/content-tracker';
import { expectRedirect, expectStatus } from '../../config/redirects';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

test.describe( 'feeds: default state', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let homeUrl: string;
	let frontPageUrl: string;
	let seededPost: SeededPost;
	let seededPage: SeededPost;
	let categoryTerm: { termId: number; slug: string; link: string };
	let tagTerm: { termId: number; slug: string; link: string };

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		homeUrl = config.homeUrl;
		frontPageUrl = config.frontPageUrl;

		// See feeds-empty-site.spec.ts for the zero-post case.
		seededPost = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'feeds post' ),
		} );

		// 'page' supports comments by default, so its comment feed renders.
		seededPage = await content.seedPage( requestUtils, {
			title: uniqueTitle( 'feeds page' ),
		} );

		// For the term-feed assertions below; assigned to seededPost so the
		// archives themselves are never empty.
		categoryTerm = await content.seedTerm( requestUtils, {
			taxonomy: 'category',
			name: uniqueTitle( 'feeds category' ),
			assignTo: seededPost.id,
		} );

		tagTerm = await content.seedTerm( requestUtils, {
			taxonomy: 'post_tag',
			name: uniqueTitle( 'feeds tag' ),
			assignTo: seededPost.id,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await content.cleanup( requestUtils );
	} );

	test( 'the main feed redirects to the home URL', async ( { request } ) => {
		// Feed redirects target home_url() (no trailing slash), unlike page
		// redirects which target get_permalink( page_on_front ) with one;
		// Location is compared exactly, no normalization either way.
		await expectRedirect( request, '/feed/', homeUrl );
	} );

	test( "a post's own feed redirects", async ( { request } ) => {
		// template_redirect's redirect_public_pages() runs before do_feed()
		// ever gets a chance, so a single post's own feed URL matches the
		// 'post' branch first and 301s to the front page, not disable_feed().
		await expectRedirect( request, `${ seededPost.permalink }feed/`, frontPageUrl );
	} );

	test( 'feed format variants all redirect', async ( { request } ) => {
		const feedPaths = [ '/feed/', '/feed/rss2/', '/feed/atom/', '/feed/rdf/' ];

		for ( const feedPath of feedPaths ) {
			await expectRedirect( request, feedPath, homeUrl );
		}
	} );

	test( 'the site comments feed still renders', async ( { request } ) => {
		// disable_feed() bails on comment feeds when another post type (here
		// 'page', by default) supports comments, leaving this one alone.
		const response = await request.get( '/comments/feed/', { maxRedirects: 0 } );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );

	test( 'a page comment feed still renders', async ( { request } ) => {
		const response = await request.get( `${ seededPage.permalink }feed/`, {
			maxRedirects: 0,
		} );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );

	/* -------------------------------------------------------------------
	 * Query-string feed URLs (see @since 0.5.5 note above)
	 * ---------------------------------------------------------------- */

	test( 'a query-string feed URL redirects to the home URL', async ( {
		request,
	} ) => {
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
		// Checks the body itself, independent of status code — the two tests
		// above only check the Location header.
		const response = await request.get( '/?feed=rss2', { maxRedirects: 0 } );
		const body = await response.text();

		expect( body ).not.toContain( seededPost.title );
	} );

	/* -------------------------------------------------------------------
	 * Term and author feeds (see docblock) — swept up via two different
	 * code paths, per test below.
	 * ---------------------------------------------------------------- */

	test( "a category archive's feed redirects, swept in by disable_feed()", async ( {
		request,
	} ) => {
		// modify_taxonomies_arguments() strips 'category''s query_var by
		// default, and WP_Query only sets is_category() through that
		// query_var, so redirect_public_pages()'s category_archive branch
		// never matches; the request falls through to disable_feed() instead.
		await expectRedirect( request, `${ categoryTerm.link }feed/`, homeUrl );
	} );

	test( "a tag archive's feed redirects, caught by redirect_public_pages() first", async ( {
		request,
	} ) => {
		// Unlike 'category_name', WP_Query keeps a legacy code path for the
		// 'tag' query var that ignores the taxonomy's query_var setting, so
		// is_tag() stays true and redirect_public_pages() catches this first
		// -- target is the front page, not home_url(), unlike the category
		// feed above.
		await expectRedirect( request, `${ tagTerm.link }feed/`, frontPageUrl );
	} );

	test( "an author archive's feed redirects, swept in by disable_feed()", async ( {
		request,
	} ) => {
		// dwpb_disable_author_archives defaults to false, so
		// redirect_public_pages()'s author_archive branch never matches here;
		// disable_feed()'s is_post_feed_request() is the only thing that
		// catches this, the same way it catches the category feed above.
		await expectRedirect( request, '/author/admin/feed/', homeUrl );
	} );
} );

test.describe( "feeds: is_post_feed_request()'s singular-exclusion branch", () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let homeUrl: string;
	let seededPost: SeededPost;

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		homeUrl = config.homeUrl;

		seededPost = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'feeds singular post' ),
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await content.cleanup( requestUtils );
	} );

	// Restored in afterEach, not the test body -- see settings-screens.spec.ts.
	test.afterEach( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( "a post's own content feed is not swept into the site-feed redirect", async ( {
		request,
		requestUtils,
	} ) => {
		// redirect_public_pages() would otherwise catch this first via its
		// 'post' branch, so switch it off to let the request reach
		// disable_feed() and actually exercise is_post_feed_request().
		await setFilterOverrides( requestUtils, {
			dwpb_redirect_front_end: false,
		} );

		// withoutcomments=1 is required: a singular feed request otherwise
		// defaults to the comment feed, and disable_feed() bails on that
		// before is_post_feed_request() ever runs, since 'page' supports
		// comments.
		const response = await expectStatus(
			request,
			`${ seededPost.permalink }feed/?withoutcomments=1`,
			200
		);
		const body = await response.text();

		expect( body ).toContain( seededPost.title );

		// Control: the main site feed is still swept in with the same override
		// active, proving the post's own feed above is a targeted exception
		// rather than a side effect of the override disabling redirects generally.
		await expectRedirect( request, '/feed/', homeUrl );
	} );
} );

test.describe( "feeds: disable_feed()'s sweep also catches tag archive feeds", () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let homeUrl: string;
	let seededPost: SeededPost;
	let tagTerm: { termId: number; slug: string; link: string };

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		homeUrl = config.homeUrl;

		seededPost = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'feeds tag sweep post' ),
		} );

		tagTerm = await content.seedTerm( requestUtils, {
			taxonomy: 'post_tag',
			name: uniqueTitle( 'feeds tag sweep' ),
			assignTo: seededPost.id,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await content.cleanup( requestUtils );
	} );

	// Restored in afterEach, not the test body -- see settings-screens.spec.ts.
	test.afterEach( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'a tag feed is independently swept in by disable_feed(), not just caught by redirect_public_pages() first', async ( {
		request,
		requestUtils,
	} ) => {
		// Switching redirect_public_pages() off proves is_post_feed_request()'s
		// sweep independently catches a tag feed too, not just the plain
		// request where redirect_public_pages() wins the race first.
		await setFilterOverrides( requestUtils, {
			dwpb_redirect_front_end: false,
		} );

		await expectRedirect( request, `${ tagTerm.link }feed/`, homeUrl );
	} );
} );
