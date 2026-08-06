/**
 * Author archives and the users sitemap at default state. Both are gated by
 * `dwpb_disable_author_archives`, which defaults to `false`, so archives
 * render normally — `redirects.spec.ts` already covers the (non-)redirect;
 * this spec covers content rendering and the sitemap. The `users` sitemap
 * provider is independently gated by `dwpb_author_archive_post_types` (empty
 * by default), so it's absent even though the archive page itself renders —
 * not a contradiction, just two separately-gated checks.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { createContentTracker } from '../../config/content-tracker';
import { expectStatus } from '../../config/redirects';

test.describe( 'frontend: author archives (default state)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let seededPost: SeededPost;

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		// No author param: requestUtils authenticates as the wp-env admin
		// ('admin'), which post creation defaults post_author to.
		seededPost = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'author archive post' ),
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await content.cleanup( requestUtils );
	} );

	test( 'an author archive lists posts by default', async ( { page } ) => {
		const response = await page.goto( '/author/admin/' );

		expect( response?.status() ).toBe( 200 );
		await expect( page.getByText( seededPost.title ) ).toBeVisible();
	} );

	test( 'an author archive is not a 404', async ( { request } ) => {
		await expectStatus( request, '/author/admin/', 200 );
	} );

	test( 'the users sitemap is absent by default', async ( { request } ) => {
		const response = await request.get( '/wp-sitemap.xml' );
		const body = await response.text();

		expect( body ).not.toContain( 'wp-sitemap-users-1.xml' );

		// Confirms the sitemap index itself rendered, so the missing users
		// entry above is a targeted removal, not a broken document.
		expect( body ).toContain( 'wp-sitemap-posts-page-1.xml' );
	} );
} );
