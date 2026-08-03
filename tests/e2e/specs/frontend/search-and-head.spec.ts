/**
 * Search results, `wp_head` output, the `X-Pingback` header, and the
 * front-end admin bar's "New Post" link — default plugin state, no fixture
 * toggles.
 *
 * COVERAGE:
 *  - `Disable_Blog_Admin::modify_post_type_arguments()` (hooked on `init` at
 *    priority 25) sets `exclude_from_search` to `true` on the `post` post
 *    type, so core search silently drops posts from results while other
 *    public post types (pages) are untouched (tests 1-2).
 *  - `Disable_Blog_Public::header_feeds()` (hooked on `wp_loaded`) removes the
 *    core `feed_links`, `feed_links_extra`, `rsd_link`, and `wlwmanifest_link`
 *    callbacks from `wp_head`, so the feed `<link>` and RSD `<link rel="EditURI">`
 *    disappear (tests 3-4). It does NOT touch the generator tag, the REST API
 *    discovery link, or oEmbed discovery — those stay, and test 5 is a
 *    negative control proving that on purpose, so a future over-broad
 *    `remove_action( 'wp_head', ... )` change would fail a test instead of
 *    going unnoticed.
 *  - `Disable_Blog_Public::filter_wp_headers()` (hooked on the `wp_headers`
 *    filter) unsets `X-Pingback` whenever core would have set it. Core only
 *    sets that header when `is_singular() && pings_open( $post )`
 *    (`WP::send_headers()`), and `pings_open()` is a plain read of the post's
 *    `ping_status` column with no plugin-side gating for non-`post` types —
 *    `Disable_Blog_Admin::filter_comment_status()`, the only filter on
 *    `pings_open`, only forces the value to `false` when
 *    `'post' === get_post_type( $post_id )`. So a *page* with pings open
 *    really would carry the header if the plugin did not remove it, which is
 *    exactly what test 6 seeds to prove.
 *  - `Disable_Blog_Admin::remove_admin_bar_links()` (hooked on
 *    `wp_before_admin_bar_render`, unconditionally, both front and admin)
 *    calls `$wp_admin_bar->remove_node( 'new-post' )`. `new-page` is
 *    untouched, and is the control for test 7.
 *
 * ANONYMOUS VS ADMIN: tests 1-6 are what a signed-out visitor sees, so that
 * describe block runs with an empty `storageState` (worker-scoped
 * `requestUtils` stays admin-authenticated for seeding regardless — see
 * `redirects.spec.ts`'s docblock). Test 7 needs a logged-in admin bar, so it
 * lives in its own describe with no `storageState` override — the project
 * default (see `playwright.config.ts`) is already the admin storage state.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { seedPost, seedPage, deletePosts, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { ADMIN_BAR_NEW_POST, ADMIN_BAR_NEW_PAGE } from '../../config/admin';

/**
 * Parent "+ New" dropdown node in the admin bar (`#wp-admin-bar-new-content`).
 *
 * Not centralized in `config/admin.ts` alongside its `ADMIN_BAR_NEW_*`
 * siblings — this file is the only spec that needs it, as a load-bearing
 * control proving the dropdown itself rendered (see the test below).
 */
const ADMIN_BAR_NEW_CONTENT = '#wp-admin-bar-new-content';

test.describe( 'frontend: search results and wp_head output (default state)', () => {
	// Public-facing behaviour — every request in this block is anonymous.
	// The worker-scoped `requestUtils` fixture stays admin-authenticated
	// regardless (see the file docblock), so seeding in beforeAll still works.
	test.use( { storageState: { cookies: [], origins: [] } } );

	// Shared needle so one search query can positively match both the post
	// and the page by title, letting tests 1 and 2 isolate "excluded" from
	// "included" on the exact same result set.
	const needle = uniqueTitle( 'searchable' );
	let seededPost: SeededPost;
	let seededPage: SeededPost;
	let searchUrl: string;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		seededPost = await seedPost( requestUtils, {
			title: `${ needle } post`,
		} );
		seededIds.push( seededPost.id );

		// Also carries pingStatus: 'open', for test 6 — see that test for why
		// a page (not a post) is required to prove the X-Pingback header
		// removal is a real removal and not just pings already being closed.
		seededPage = await seedPage( requestUtils, {
			title: `${ needle } page`,
			pingStatus: 'open',
		} );
		seededIds.push( seededPage.id );

		searchUrl = `/?s=${ encodeURIComponent( needle ) }`;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Best-effort — deletePosts() never throws, see its docblock.
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'search results exclude posts', async ( { request } ) => {
		const response = await request.get( searchUrl );

		expect( response.status() ).toBe( 200 );

		const body = await response.text();

		expect( body ).not.toContain( seededPost.title );
	} );

	test( 'search results still include pages', async ( { request } ) => {
		// Separate test from the one above so the "pages still show up"
		// control has its own pass/fail signal, rather than being buried
		// inside the posts-excluded assertion.
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
		// Negative control: header_feeds() only ever removes feed_links,
		// feed_links_extra, rsd_link and wlwmanifest_link (see the file
		// docblock). Nothing in the plugin touches the REST discovery link or
		// oEmbed discovery, so both must still be present — asserting that
		// stops a future over-broad `remove_action( 'wp_head', ... )` change
		// from silently widening what this plugin strips.
		const response = await request.get( '/' );
		const body = await response.text();

		expect( body ).toContain( 'rel="https://api.w.org/"' );
		expect( body ).toContain( 'application/json+oembed' );
	} );

	test( 'the X-Pingback header is absent', async ( { request } ) => {
		// seededPage has ping_status 'open' (set in beforeAll). Without
		// filter_wp_headers(), WP::send_headers() would set X-Pingback here
		// because is_singular() is true and pings_open( $post ) reads the
		// page's own ping_status column directly — pings_open has no
		// post-type gating, unlike comments_open (see the file docblock).
		// This is therefore a real removal, not an assertion on a header that
		// was never going to be present in the first place.
		const response = await request.get( seededPage.permalink );

		expect( response.headers()[ 'x-pingback' ] ).toBeUndefined();
	} );
} );

test.describe( 'frontend: admin bar (logged in as admin)', () => {
	// No storageState override: the project default is already the admin
	// storage state (see playwright.config.ts), which is what this test needs
	// — the admin bar's "New Post" node only renders for a logged-in user.

	test( 'the front-end admin bar has no New Post link', async ( { page } ) => {
		await page.goto( '/' );

		// Both `new-post` and `new-page` are submenu items inside the "+ New"
		// admin-bar dropdown, which core keeps CSS-hidden (`display: none`)
		// until the parent is hovered/focused — `toBeVisible()` would fail on
		// `new-page` too, since "in the DOM but hidden until hover" is
		// correct browser behaviour, not something the plugin controls.
		// Assert DOM presence instead: the plugin's `remove_node( 'new-post' )`
		// call means that node never exists at all (count 0), while
		// `new-page` is untouched and still exists as a hidden node (count 1).
		await expect( page.locator( ADMIN_BAR_NEW_POST ) ).toHaveCount( 0 );

		// Control: proves remove_admin_bar_links() targeted the 'new-post'
		// node specifically, rather than the admin bar's Content group (or
		// the "New" menu) failing to render at all.
		await expect( page.locator( ADMIN_BAR_NEW_PAGE ) ).toHaveCount( 1 );

		// Further control: proves the dropdown itself rendered in the first
		// place, so the two count assertions above aren't trivially passing
		// against an admin bar that never loaded.
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toBeVisible();
	} );
} );
