/**
 * Front-end feed behaviour (`Disable_Blog_Public::disable_feed()`), every
 * filter at its shipped default. Assertions run request-layer via
 * expectRedirect(), same reasoning as frontend/redirects.spec.ts; the describe
 * block is anonymous.
 *
 * Fixed since 0.5.5: query-string feed URLs (`/?feed=rss2`) used to leak real
 * post content, because disable_feed() guarded on the global `$post` being a
 * 'post' — which is the Home page, not a post, for a query-string feed
 * request. It now delegates to `is_post_feed_request()`, which reads
 * `$wp->query_vars` instead, so these now redirect like the pretty-permalink
 * feed forms. Covered below alongside a leak-specific body check.
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

		// Needed for "a post's own feed redirects" (real permalink) and the
		// query-string leak assertion below (known title), not the redirect
		// logic itself — see feeds-empty-site.spec.ts for the zero-post case.
		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'feeds post' ),
		} );
		seededIds.push( seededPost.id );

		// 'page' supports comments by default, so its comment feed renders.
		seededPage = await seedPage( requestUtils, {
			title: uniqueTitle( 'feeds page' ),
		} );
		seededIds.push( seededPage.id );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await deletePosts( requestUtils, seededIds );
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
	 * Query-string feed URLs — regression guard for N1
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
} );
