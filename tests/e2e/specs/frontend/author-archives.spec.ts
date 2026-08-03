/**
 * Author archives and the user sitemap, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Functions::disable_author_archives()` gates the
 * `'author_archive'` entry in `Disable_Blog_Public::redirect_public_pages()`'s
 * `$public_redirects` map, and `Disable_Blog_Public::wp_author_sitemaps()`
 * gates whether the `users` provider is removed from `wp-sitemap.xml`. Both
 * read `dwpb_disable_author_archives`, which defaults to `false` — "many
 * plugins and themes use author archives for profile pages" per that
 * function's own docblock — so out of the box author archives render exactly
 * like a stock WordPress install.
 *
 * `redirects.spec.ts` already proves the archive is not redirected (its
 * "an author archive is not redirected by default" test); this spec covers
 * the content side that a redirect-focused spec has no reason to check: that
 * the archive actually renders the author's posts, and that the sitemap
 * mirrors the same default.
 *
 * GATING CONDITION THAT SHAPES THE SITEMAP TEST: `wp_author_sitemaps()`
 * disables the `users` sitemap provider whenever
 * `Disable_Blog_Functions::author_archive_post_types()` returns `false` — i.e.
 * whenever no post type has been added via the `dwpb_author_archive_post_types`
 * filter (empty by default). So, counter-intuitively, the `users` sitemap is
 * absent by default even though the author archive *page* itself is not
 * redirected: those are two independently-gated code paths reading different
 * functions, not a contradiction.
 *
 * ANONYMOUS CONTEXT: same convention as `redirects.spec.ts` — the whole
 * describe block runs with an empty `storageState`, while the worker-scoped
 * `requestUtils` fixture stays admin-authenticated for seeding.
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

test.describe( 'frontend: author archives (default state)', () => {
	// Public-facing behaviour — every request in this block is anonymous.
	// The worker-scoped `requestUtils` fixture stays admin-authenticated
	// regardless (see the file docblock), so seeding in beforeAll still works.
	test.use( { storageState: { cookies: [], origins: [] } } );

	let seededPost: SeededPost;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		// No `author` param: dwpb_test_api_create_post() defaults post_author
		// to get_current_user_id(), and requestUtils authenticates as the
		// wp-env admin (username 'admin') for every worker-scoped call — so
		// this post is authored by admin without needing to look up an id.
		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'author archive post' ),
		} );
		seededIds.push( seededPost.id );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Best-effort — deletePosts() never throws, see its docblock.
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'an author archive lists posts by default', async ( { page } ) => {
		const response = await page.goto( '/author/admin/' );

		expect( response?.status() ).toBe( 200 );
		await expect( page.getByText( seededPost.title ) ).toBeVisible();
	} );

	test( 'an author archive is not a 404', async ( { request } ) => {
		// Explicit control, independent of the content assertion above: this
		// is the plain status check that a future regression narrowing to
		// "the archive still 200s but stops listing posts" would still catch.
		await expectStatus( request, '/author/admin/', 200 );
	} );

	test( 'the users sitemap is absent by default', async ( { request } ) => {
		const response = await request.get( '/wp-sitemap.xml' );
		const body = await response.text();

		expect( body ).not.toContain( 'wp-sitemap-users-1.xml' );

		// Positive control: proves the sitemap index itself rendered (with the
		// Home/Blog pages global setup always seeds backing the pages
		// sub-sitemap), so the missing users entry above is a targeted
		// removal, not evidence the whole document failed to render.
		expect( body ).toContain( 'wp-sitemap-posts-page-1.xml' );
	} );
} );
