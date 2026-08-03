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
 * 🚫 NOT COVERED HERE, ON PURPOSE: requesting a sitemap sub-file directly
 * (e.g. `/wp-sitemap-posts-post-1.xml`, which core still serves live content
 * for despite `post` being excluded from the *index*). That mismatch is a
 * known, tracked defect slated for a later phase's fix — asserting today's
 * (wrong) behaviour here would bake the bug into the suite as if it were a
 * spec. This file only asserts what the *index* document at `/wp-sitemap.xml`
 * lists and omits.
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
} );
