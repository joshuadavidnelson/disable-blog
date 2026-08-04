/**
 * Main feed behaviour on a site with zero 'post' content.
 *
 * Fixed since 0.5.5: `disable_feed()` used to key off the global `$post`,
 * which `WP_Query` never populates for a zero-result query, so an empty site
 * let core render a normal 200 feed instead of redirecting. It now gates on
 * `is_post_feed_request()`, a query-based check, so `/feed/` redirects
 * regardless of post count.
 *
 * `beforeAll` wipes every post via `delete-all-posts` so test 1 proves a
 * genuinely empty site rather than depending on other specs' cleanup — every
 * other spec tears down its own uniquely-titled content, so this is safe.
 */

/**
 * WordPress dependencies
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, seedPost, deletePosts, deleteAllPosts, uniqueTitle } from '../../config/seed';
import { expectRedirect } from '../../config/redirects';

test.describe( 'feeds: empty site', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let homeUrl: string;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		homeUrl = config.homeUrl;

		await deleteAllPosts( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Cleans up whatever test 2 seeded, leaving the site at zero posts.
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'with zero posts the main feed still redirects to the home URL', async ( {
		request,
	} ) => {
		await expectRedirect( request, '/feed/', homeUrl );
	} );

	test( 'seeding a post does not disturb the redirect', async ( { requestUtils, request } ) => {
		const seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'empty-site restore post' ),
		} );
		seededIds.push( seededPost.id );

		await expectRedirect( request, '/feed/', homeUrl );
	} );
} );
