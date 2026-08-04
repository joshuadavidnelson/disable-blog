/**
 * Core XML sitemap index behaviour, default plugin state. `wp_sitemaps_post_types()`
 * unsets 'post'; `wp_sitemaps_taxonomies()` unsets category/post_tag (default,
 * since only 'post' supports them); `wp_author_sitemaps()` removes the
 * 'users' provider via `wp_sitemaps_add_provider`. Content-type checks look
 * for `xml` rather than the exact charset string, so a core version bump
 * can't break this suite for no reason.
 *
 * Fixed since 0.5.5: requesting `/wp-sitemap-users-1.xml` directly used to
 * fall through to the normal template and leak post content as HTML 200,
 * because removing the 'users' provider (via wp_sitemaps_add_provider)
 * leaves core nothing to 404 against, unlike posts/taxonomies which unset()
 * array entries and 404 on their own. `disable_removed_sitemaps()` now
 * resolves the request against the live provider registry and 404s it.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { seedPost, deletePosts, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { expectStatus } from '../../config/redirects';

test.describe( 'sitemap: default state', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let seededPost: SeededPost;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		// So the exclusion assertions below prove a real post exists to be
		// excluded, rather than passing vacuously against an empty site.
		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'sitemap post' ),
		} );
		seededIds.push( seededPost.id );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'the sitemap index renders', async ( { request } ) => {
		const response = await request.get( '/wp-sitemap.xml', { maxRedirects: 0 } );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );

	test( 'the index lists pages but not posts', async ( { request } ) => {
		const response = await request.get( '/wp-sitemap.xml' );
		const body = await response.text();

		expect( body ).toContain( 'wp-sitemap-posts-page-1.xml' );
		expect( body ).not.toContain( 'wp-sitemap-posts-post-1.xml' );
	} );

	test( 'the index omits category and tag sitemaps', async ( { request } ) => {
		const response = await request.get( '/wp-sitemap.xml' );
		const body = await response.text();

		expect( body ).not.toContain( 'wp-sitemap-taxonomies-category-1.xml' );
		expect( body ).not.toContain( 'wp-sitemap-taxonomies-post_tag-1.xml' );
	} );

	test( 'the index omits the users sitemap', async ( { request } ) => {
		const response = await request.get( '/wp-sitemap.xml' );
		const body = await response.text();

		expect( body ).not.toContain( 'wp-sitemap-users-1.xml' );
	} );

	/* -------------------------------------------------------------------
	 * Regression guards: sub-files that already 404 with no plugin fix
	 * involved (unset() leaves no provider for core to fall back on)
	 * ---------------------------------------------------------------- */

	test( 'regression guard: a removed posts sitemap sub-file still 404s', async ( {
		request,
	} ) => {
		await expectStatus( request, '/wp-sitemap-posts-post-1.xml', 404 );
	} );

	test( 'regression guard: a removed category taxonomy sitemap sub-file still 404s', async ( {
		request,
	} ) => {
		await expectStatus( request, '/wp-sitemap-taxonomies-category-1.xml', 404 );
	} );

	test( 'regression guard: a removed post_tag taxonomy sitemap sub-file still 404s', async ( {
		request,
	} ) => {
		await expectStatus( request, '/wp-sitemap-taxonomies-post_tag-1.xml', 404 );
	} );

	/* -------------------------------------------------------------------
	 * Regression guards: the users sitemap fix (see file docblock)
	 * ---------------------------------------------------------------- */

	test( 'regression guard: a removed users sitemap sub-file 404s', async ( { request } ) => {
		await expectStatus( request, '/wp-sitemap-users-1.xml', 404 );
	} );

	test( 'regression guard control: the still-supported pages sitemap sub-file still renders', async ( {
		request,
	} ) => {
		// Proves the fix targets the removed users provider specifically, not
		// a blanket 404 of every sitemap sub-file.
		const response = await request.get( '/wp-sitemap-posts-page-1.xml', {
			maxRedirects: 0,
		} );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );

	test( 'regression guard: a removed users sitemap sub-file never leaks post content', async ( {
		request,
	} ) => {
		const response = await request.get( '/wp-sitemap-users-1.xml' );
		const body = await response.text();

		expect( body ).not.toContain( seededPost.title );
	} );
} );
