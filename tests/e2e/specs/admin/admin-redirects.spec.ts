/**
 * Admin-screen 301 redirects, default plugin state.
 *
 * The `post.php`/`edit-tags.php`/`term.php`/`tools.php` tests below guard
 * three dispatch hazards: `redirect_admin_term()`'s method name must exactly
 * match the `redirect_admin_<slug>()` pattern the dispatcher builds from the
 * screen slug, or `term.php` silently stops redirecting; the `tools` slug's
 * method is `redirect_admin_options_tools()` (not `redirect_admin_tools()`),
 * exposed under the legacy `dwpb_redirect_admin_options_tools` filter name;
 * and the dashboard branch must check `true === $redirect` before
 * `esc_url_raw()`, since `esc_url_raw( true )` resolves to `"http://1"`,
 * which would send `wp_safe_redirect()` to bare `/wp-admin/` instead of the
 * dashboard.
 *
 * Assertions go through `expectRedirect()`/`expectStatus()` rather than
 * `page.goto()`, to avoid Chromium's 301 caching serving a stale redirect
 * (see `frontend/redirects.spec.ts`).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { createContentTracker } from '../../config/content-tracker';
import { adminUrl, editPhp, postNewPhp, postPhp, editTagsPhp, termPhp } from '../../config/admin';
import { expectRedirect, expectStatus } from '../../config/redirects';

test.describe( 'admin: redirects (default state)', () => {
	let editPageTarget: string;
	let postNewPageTarget: string;
	let dashboardTarget: string;
	let seededPost: SeededPost;
	let seededPage: SeededPost;
	let categoryTerm: { termId: number; slug: string; link: string };

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );

		editPageTarget = `${ config.homeUrl }${ editPhp( 'page' ) }`;
		postNewPageTarget = `${ config.homeUrl }${ postNewPhp( 'page' ) }`;
		dashboardTarget = `${ config.homeUrl }${ adminUrl( 'index.php' ) }`;

		seededPost = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'admin redirects post' ),
		} );

		seededPage = await content.seedPage( requestUtils, {
			title: uniqueTitle( 'admin redirects page' ),
		} );

		categoryTerm = await content.seedTerm( requestUtils, {
			taxonomy: 'category',
			name: uniqueTitle( 'admin redirects category' ),
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await content.cleanup( requestUtils );
	} );

	// redirect_admin_edit() / redirect_admin_post_new()

	test( 'the posts list redirects to the pages list', async ( { request } ) => {
		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );
	} );

	test( 'an explicit post_type=post list redirects to the pages list', async ( {
		request,
	} ) => {
		await expectRedirect(
			request,
			editPhp( 'post' ),
			editPageTarget,
			301
		);
	} );

	test( 'the new post screen redirects to the new page screen', async ( {
		request,
	} ) => {
		await expectRedirect( request, adminUrl( 'post-new.php' ), postNewPageTarget, 301 );
	} );

	test( 'an explicit post_type=post new screen redirects', async ( { request } ) => {
		await expectRedirect(
			request,
			postNewPhp( 'post' ),
			postNewPageTarget,
			301
		);
	} );

	// Controls: prove the redirect is targeted, not a blanket catch-all

	test( 'the pages list is not redirected', async ( { request } ) => {
		await expectStatus( request, editPhp( 'page' ), 200 );
	} );

	test( 'the comments and discussion screens are not redirected by default', async ( {
		request,
	} ) => {
		await expectStatus( request, adminUrl( 'edit-comments.php' ), 200 );
		await expectStatus( request, adminUrl( 'options-discussion.php' ), 200 );
	} );

	// Regression guards (see file docblock)

	test( 'regression guard: term.php redirects to the dashboard', async ( { request } ) => {
		await expectRedirect(
			request,
			termPhp( 'category', categoryTerm.termId ),
			dashboardTarget
		);
	} );

	test( 'regression guard: tools.php redirects to the dashboard', async ( { request } ) => {
		await expectRedirect( request, adminUrl( 'tools.php' ), dashboardTarget );
	} );

	test( 'regression guard control: a third-party tools.php subpage is not redirected by the plugin', async ( {
		request,
	} ) => {
		// Not a bare status check: core itself 302s an unregistered
		// tools.php?page=... slug, so this asserts specifically that the
		// response isn't the plugin's own 301-to-dashboard.
		const response = await request.get(
			adminUrl( 'tools.php?page=some-plugin-page' ),
			{ maxRedirects: 0 }
		);
		const status = response.status();
		const location = response.headers()[ 'location' ] ?? null;
		const isPluginDashboardRedirect = 301 === status && location === dashboardTarget;

		expect(
			isPluginDashboardRedirect,
			'Expected tools.php?page=some-plugin-page NOT to be redirected by the ' +
				`plugin to the dashboard, but got status ${ status } with ` +
				`Location: ${ location ?? '(none)' }`
		).toBe( false );
	} );

	test( 'regression guard: editing a post redirects to the dashboard, not bare /wp-admin/', async ( {
		request,
	} ) => {
		await expectRedirect( request, postPhp( seededPost.id ), dashboardTarget );
	} );

	test( 'regression guard control: editing a page is still not redirected', async ( {
		request,
	} ) => {
		await expectStatus( request, postPhp( seededPage.id ), 200 );
	} );

	test( 'regression guard: a category edit-tags.php screen redirects to the dashboard, not bare /wp-admin/', async ( {
		request,
	} ) => {
		await expectRedirect( request, editTagsPhp( 'category' ), dashboardTarget );
	} );

	test( 'regression guard: a post_tag edit-tags.php screen redirects to the dashboard, not bare /wp-admin/', async ( {
		request,
	} ) => {
		await expectRedirect( request, editTagsPhp( 'post_tag' ), dashboardTarget );
	} );
} );
