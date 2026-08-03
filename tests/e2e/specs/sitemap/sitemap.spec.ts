/**
 * Core XML sitemap index behaviour, default plugin state.
 *
 * COVERAGE: three `Disable_Blog_Public` filters on the core sitemaps
 * providers (`wp-includes/sitemaps/`), all left at their shipped defaults:
 *  - `wp_sitemaps_post_types()`, on the `wp_sitemaps_post_types` filter,
 *    unconditionally `unset()`s `'post'` from the post types sitemaps are
 *    built for. 'page' is untouched, so it stays.
 *  - `wp_sitemaps_taxonomies()`, on the `wp_sitemaps_taxonomies` filter,
 *    unsets `'category'`/`'post_tag'` when `dwpb_post_types_with_tax( $tax )`
 *    is falsy — true by default in a stock install, since only 'post'
 *    supports either taxonomy out of the box. So both built-in taxonomy
 *    sitemaps are removed here too.
 *  - `wp_author_sitemaps()`, on the `wp_sitemaps_add_provider` filter,
 *    returns `false` for the `'users'` provider whenever
 *    `dwpb_disable_user_sitemap` resolves truthy, which it does by default
 *    (no post types support author archives once 'post' is excluded from
 *    consideration — see `Disable_Blog_Functions::author_archive_post_types()`).
 *
 * All three filters act on core's own sitemap *index* generation
 * (`WP_Sitemaps_Registry`/`WP_Sitemaps_Renderer::render_index()`,
 * `wp-includes/sitemaps/class-wp-sitemaps-renderer.php`), which sets
 * `Content-Type: application/xml; charset=UTF-8` on every response — hence
 * this file's content-type assertions check for `xml` rather than
 * hardcoding the exact charset string, so a WordPress core version bump that
 * reorders or re-cases that header can't break this suite for no reason.
 *
 * D5 FIX (`Disable_Blog_Public::disable_removed_sitemaps()`, hooked on
 * `template_redirect`): requesting a removed provider's sitemap sub-file
 * directly was originally audited as a blanket leak across posts,
 * taxonomies, AND users. Verified live against a real site with a published
 * post seeded, that assumption was too broad:
 *  - `/wp-sitemap-posts-post-1.xml`, `/wp-sitemap-taxonomies-category-1.xml`,
 *    and `/wp-sitemap-taxonomies-post_tag-1.xml` already `404` correctly on
 *    their own, with no plugin involvement. `wp_sitemaps_post_types()` /
 *    `wp_sitemaps_taxonomies()` both `unset()` their entries from the arrays
 *    `WP_Sitemaps_Registry` walks, so `WP_Sitemaps::render_sitemaps()` has no
 *    registered provider left for that sub-file and 404s on its own. These
 *    three are covered below as regression guards, and were never D5 defects.
 *  - `/wp-sitemap-users-1.xml` was the one genuine D5 defect. `wp_author_sitemaps()`
 *    removes the whole `'users'` *provider* via a different code path — the
 *    `wp_sitemaps_add_provider` filter, rather than unset()-ing an array
 *    entry — so core had no provider left to 404 against. The request
 *    instead fell through to the normal template and served the blog index
 *    as HTML at `200`, leaking the seeded post's title into the response
 *    body. `disable_removed_sitemaps()` fixes this by resolving the
 *    requested sitemap against the live provider registry and 404ing it when
 *    the provider is gone, gated behind the `dwpb_disable_removed_sitemaps`
 *    filter (default `true`). Both assertions covering
 *    `/wp-sitemap-users-1.xml` below (the `404` and the no-leak check)
 *    exercise that corrected behaviour and are now regression guards. A
 *    `/wp-sitemap-posts-page-1.xml` control (a sub-file for a provider the
 *    plugin does NOT remove) is asserted to stay `200` alongside them —
 *    without it, a blanket "404 everything" over-fix would pass this file
 *    just as easily as a targeted one.
 *
 * REQUEST LAYER: plain `request.get()` calls, same as every other spec in
 * this phase — see the docblock in `config/redirects.ts` for why navigation
 * is avoided for response-inspection assertions in this suite generally.
 *
 * ANONYMOUS CONTEXT: sitemaps are a public-facing surface, so the whole
 * describe block runs with an empty `storageState`. The worker-scoped
 * `requestUtils` fixture stays admin-authenticated regardless, so
 * `beforeAll`/`afterAll` can still seed and tear down content.
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
	// Public-facing behaviour — every request in this block is anonymous. The
	// worker-scoped requestUtils fixture is unaffected (see the file docblock).
	test.use( { storageState: { cookies: [], origins: [] } } );

	let seededPost: SeededPost;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		// So the exclusion assertions below (tests 2-4) are proving a real
		// post/taxonomy exists to be excluded, not passing vacuously against
		// an already-empty site.
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
	 * Regression guards (currently passing): already-correct 404s
	 *
	 * Verified live: these three sub-files already 404 today with no plugin
	 * fix involved — wp_sitemaps_post_types()/wp_sitemaps_taxonomies() both
	 * unset() their entries, leaving WP_Sitemaps::render_sitemaps() with no
	 * registered provider to fall back on. Not D5 defects; kept here so a
	 * future regression re-introducing them gets caught.
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
	 * Regression guards: corrected behaviour for the D5 fix
	 *
	 * The users sitemap was the one genuine D5 defect (see the file
	 * docblock): removing the 'users' provider goes through
	 * wp_sitemaps_add_provider rather than unset()-ing an array entry, so
	 * core doesn't 404 it on its own — disable_removed_sitemaps(), hooked on
	 * template_redirect, is what catches the request and 404s it instead of
	 * letting it fall through to the normal template and leak the seeded
	 * post's title into the HTML response.
	 * ---------------------------------------------------------------- */

	test( 'regression guard: a removed users sitemap sub-file 404s', async ( { request } ) => {
		// wp_author_sitemaps() removes the 'users' provider via
		// wp_sitemaps_add_provider rather than unset()-ing an array entry the
		// way the post types/taxonomies filters do, so core has nothing
		// registered to 404 against on its own —
		// Disable_Blog_Public::disable_removed_sitemaps() (hooked on
		// template_redirect) is what resolves the request against the live
		// provider registry and 404s it.
		await expectStatus( request, '/wp-sitemap-users-1.xml', 404 );
	} );

	test( 'regression guard control: the still-supported pages sitemap sub-file still renders', async ( {
		request,
	} ) => {
		// Proves the D5 fix is targeted at the removed users provider
		// specifically, not a blanket 404 of every sitemap sub-file — a fix
		// that 404s everything would pass the test above just as easily as a
		// correct, targeted one, but would fail this one.
		const response = await request.get( '/wp-sitemap-posts-page-1.xml', {
			maxRedirects: 0,
		} );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'xml' );
	} );

	test( 'regression guard: a removed users sitemap sub-file never leaks post content', async ( {
		request,
	} ) => {
		// The leak used to happen here, not on posts-post-1: that sub-file
		// already 404s on its own (see the regression guard above) with no
		// body to leak from, so a leak assertion attached to it would pass
		// vacuously. /wp-sitemap-users-1.xml is where the fallback-to-template
		// HTML used to contain the seeded post's title before the D5 fix.
		const response = await request.get( '/wp-sitemap-users-1.xml' );
		const body = await response.text();

		expect( body ).not.toContain( seededPost.title );
	} );
} );
