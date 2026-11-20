/**
 * Search results, `wp_head` output, the `X-Pingback` header, and the
 * front-end admin bar's "New Post" link — default plugin state.
 *
 * `filter_wp_headers()` unsets X-Pingback, which core would otherwise set
 * for a page with open pings -- `pings_open()` has no post-type gating,
 * unlike `comments_open()`.
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
import { ADMIN_BAR_NEW_POST, ADMIN_BAR_NEW_PAGE } from '../../config/admin';

// Parent "+ New" dropdown node in the admin bar; only this spec needs it.
const ADMIN_BAR_NEW_CONTENT = '#wp-admin-bar-new-content';

test.describe( 'frontend: search results and wp_head output (default state)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	// Shared needle lets one search query positively match both post and page.
	const needle = uniqueTitle( 'searchable' );
	let seededPost: SeededPost;
	let seededPage: SeededPost;
	let searchUrl: string;

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		seededPost = await content.seedPost( requestUtils, {
			title: `${ needle } post`,
		} );

		// pingStatus 'open' on a page (not a post) so the X-Pingback test
		// proves a real removal rather than pings already being closed.
		seededPage = await content.seedPage( requestUtils, {
			title: `${ needle } page`,
			pingStatus: 'open',
		} );

		searchUrl = `/?s=${ encodeURIComponent( needle ) }`;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await content.cleanup( requestUtils );
	} );

	test( 'search results exclude posts', async ( { request } ) => {
		const response = await request.get( searchUrl );

		expect( response.status() ).toBe( 200 );

		const body = await response.text();

		expect( body ).not.toContain( seededPost.title );
	} );

	test( 'search results still include pages', async ( { request } ) => {
		const response = await request.get( searchUrl );
		const body = await response.text();

		expect( body ).toContain( seededPage.title );
	} );

	test( 'wp_head omits feed links', async ( { request } ) => {
		const response = await request.get( '/' );
		const body = await response.text();

		expect( body ).not.toContain( 'application/rss+xml' );
	} );

	test( 'wp_head omits the RSD link', async ( { request } ) => {
		const response = await request.get( '/' );
		const body = await response.text();

		expect( body ).not.toContain( 'rel="EditURI"' );
	} );

	test( 'wp_head keeps the generator, REST and oEmbed links', async ( {
		request,
	} ) => {
		// Negative control: header_feeds() doesn't touch these, so guards
		// against a future over-broad remove_action() widening what's stripped.
		const response = await request.get( '/' );
		const body = await response.text();

		expect( body ).toContain( 'rel="https://api.w.org/"' );
		expect( body ).toContain( 'application/json+oembed' );
	} );

	test( 'the X-Pingback header is absent', async ( { request } ) => {
		const response = await request.get( seededPage.permalink );

		expect( response.headers()[ 'x-pingback' ] ).toBeUndefined();
	} );
} );

test.describe( 'frontend: admin bar (logged in as admin)', () => {
	// Uses the project's default admin storageState (see playwright.config.ts).

	test( 'the front-end admin bar has no New Post link', async ( { page } ) => {
		await page.goto( '/' );

		// Both nodes are CSS-hidden until the "+ New" dropdown is
		// hovered/focused, so assert DOM presence (count) rather than
		// visibility: remove_node( 'new-post' ) means it never exists at all.
		await expect( page.locator( ADMIN_BAR_NEW_POST ) ).toHaveCount( 0 );
		await expect( page.locator( ADMIN_BAR_NEW_PAGE ) ).toHaveCount( 1 );

		// Confirms the dropdown itself rendered, so the counts above aren't
		// trivially passing against an admin bar that never loaded.
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toBeVisible();
	} );
} );
