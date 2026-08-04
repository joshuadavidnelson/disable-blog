/**
 * Front-end 301 redirect behaviour (`Disable_Blog_Public::redirect_public_pages()`
 * on `template_redirect`), with every plugin filter at its shipped default.
 * Filter-driven variations live under tests/e2e/specs/filters/.
 *
 * Assertions run at the request layer via expectRedirect/expectStatus/
 * expectNoRedirect (config/redirects.ts) rather than by navigating, since
 * Chromium caches 301s and could observe a stale redirect. The describe
 * block uses an anonymous storageState; requestUtils stays admin-authenticated
 * regardless, so beforeAll/afterAll can still seed and tear down content.
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
	deleteTerms,
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
	test.use( { storageState: { cookies: [], origins: [] } } );

	let frontPageUrl: string;
	let seededPost: SeededPost;
	let secondPost: SeededPost;
	let seededPage: SeededPost;
	let tagSlug: string;

	// posts_per_page as read before beforeAll forces it to 1; restored in afterAll.
	let originalPostsPerPage: number | undefined;

	const seededIds: number[] = [];
	const seededTermIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		frontPageUrl = config.frontPageUrl;

		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'redirects post' ),
			postDate: POST_DATE,
		} );
		seededIds.push( seededPost.id );

		// Second post gives the blog a real page 2 to paginate to (see below).
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
		seededTermIds.push( term.termId );

		// Force a real page 2 to exist: with the default posts_per_page (10)
		// and only two seeded posts, /blog/page/2/ 404s before
		// template_redirect ever runs, so the pagination test below would
		// silently assert a 404 instead of the 301. Restored in afterAll.
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
		// Guarded in case beforeAll threw before reading the original value.
		if ( undefined !== originalPostsPerPage ) {
			await requestUtils.rest( {
				method: 'PUT',
				path: '/wp/v2/settings',
				data: { posts_per_page: originalPostsPerPage },
			} );
		}

		await deletePosts( requestUtils, seededIds );
		await deleteTerms( requestUtils, seededTermIds );
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
		// Must be a real post id: an empty/nonexistent ?p= also 301s (as an
		// empty query resolving to is_home()), which would prove nothing.
		await expectRedirect( request, `/?p=${ seededPost.id }`, frontPageUrl );
	} );

	test( 'the posts page redirects to the front page', async ( { request } ) => {
		await expectRedirect( request, '/blog/', frontPageUrl );
	} );

	test( 'posts page pagination redirects to the front page', async ( { request } ) => {
		// Relies on the forced posts_per_page=1 from beforeAll for a real page 2.
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
		// Guard in Disable_Blog_Functions::redirect(): bails when
		// $redirect_url === $current_url instead of 301-ing to itself.
		await expectStatus( request, '/', 200 );
	} );

	test( 'a regular page is not redirected', async ( { request } ) => {
		// Pages aren't in $public_redirects at all.
		await expectStatus( request, seededPage.permalink, 200 );
	} );

	test( 'a post_type=post query exposes no post archive', async ( {
		request,
	} ) => {
		// 'post' is registered non-publicly-queryable, so WP strips the
		// post_type query var entirely; the request collapses to the static
		// front page (200, not a redirect). Only worth checking no seeded
		// post content leaks through.
		const response = await expectStatus( request, '/?post_type=post', 200 );
		const body = await response.text();

		expect( body ).not.toContain( seededPost.title );
		expect( body ).not.toContain( secondPost.title );
	} );

	test( 'an author archive is not redirected by default', async ( { request } ) => {
		// dwpb_disable_author_archives defaults to false, so author archives
		// are deliberately excluded from $public_redirects out of the box.
		await expectNoRedirect( request, '/author/admin/' );
	} );

	test( 'an unknown URL still returns 404', async ( { request } ) => {
		// Unique, never-seeded slug so no other spec's content can collide.
		const unknownPath = `/${ uniqueTitle( 'no-such-page' ).replace( /\s+/g, '-' ) }/`;

		await expectStatus( request, unknownPath, 404 );
	} );

	/* -------------------------------------------------------------------
	 * Redirect target details
	 * ---------------------------------------------------------------- */

	test( 'the query string is dropped on redirect', async ( { request } ) => {
		// dwpb_pass_query_string_on_redirect and dwpb_allowed_query_vars both
		// default to empty, so Location must be exactly frontPageUrl.
		await expectRedirect(
			request,
			`${ seededPost.permalink }?utm_source=x&foo=1`,
			frontPageUrl
		);
	} );
} );
