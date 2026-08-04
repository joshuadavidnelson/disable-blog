/**
 * Admin-screen 301 redirects, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::redirect_admin_pages()`, hooked on
 * `current_screen`, walks a fixed list of admin page slugs and calls the
 * matching `redirect_admin_<slug>()` method. `redirect_admin_edit()` and
 * `redirect_admin_post_new()` redirect to the `page` equivalent screen
 * whenever `post_type` is absent or `'post'`, via `wp_safe_redirect()` at
 * 301. `redirect_admin_edit_comments()` (and its `options-discussion`
 * wrapper) redirect only when `! dwpb_post_types_with_feature( 'comments' )`
 * — false here, since `page`/`attachment` support comments by default, so
 * neither screen redirects (test 6).
 *
 * Since v0.5.5: `post.php`, `edit-tags.php`, `term.php`, and `tools.php` are
 * covered here as regression guards for three fixed bugs —
 * `redirect_admin_term()` was misspelled (never matched, so term.php never
 * redirected); the `tools` loop entry expected `redirect_admin_tools()` but
 * the method was named `redirect_admin_options_tools()` (same effect, now
 * renamed, with the old `dwpb_redirect_admin_options_tools` filter name kept
 * via `apply_filters_deprecated()`); and `esc_url_raw( true )` for a boolean
 * `$redirect` resolved to the non-empty `"http://1"`, sending
 * `wp_safe_redirect()` to bare `/wp-admin/` instead of the dashboard (fixed
 * by checking `true === $redirect` first).
 *
 * Assertions go through `expectRedirect()`/`expectStatus()` from
 * `config/redirects.ts` rather than `page.goto()`, to avoid Chromium's 301
 * caching serving a stale redirect (see `frontend/redirects.spec.ts`).
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
	uniqueTitle,
} from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { adminUrl, postPhp, editTagsPhp, termPhp } from '../../config/admin';
import { expectRedirect, expectStatus } from '../../config/redirects';

test.describe( 'admin: redirects (default state)', () => {
	let editPageTarget: string;
	let postNewPageTarget: string;
	let dashboardTarget: string;
	let seededPost: SeededPost;
	let seededPage: SeededPost;
	let categoryTerm: { termId: number; slug: string; link: string };

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );

		editPageTarget = `${ config.homeUrl }${ adminUrl( 'edit.php?post_type=page' ) }`;
		postNewPageTarget = `${ config.homeUrl }${ adminUrl( 'post-new.php?post_type=page' ) }`;
		dashboardTarget = `${ config.homeUrl }${ adminUrl( 'index.php' ) }`;

		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'admin redirects post' ),
		} );
		seededIds.push( seededPost.id );

		seededPage = await seedPage( requestUtils, {
			title: uniqueTitle( 'admin redirects page' ),
		} );
		seededIds.push( seededPage.id );

		categoryTerm = await seedTerm( requestUtils, {
			taxonomy: 'category',
			name: uniqueTitle( 'admin redirects category' ),
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// The seeded category term is left in place, wiped only by the next
		// run's global-setup reset.
		await deletePosts( requestUtils, seededIds );
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
			adminUrl( 'edit.php?post_type=post' ),
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
			adminUrl( 'post-new.php?post_type=post' ),
			postNewPageTarget,
			301
		);
	} );

	// Controls: prove the redirect is targeted, not a blanket catch-all

	test( 'the pages list is not redirected', async ( { request } ) => {
		await expectStatus( request, adminUrl( 'edit.php?post_type=page' ), 200 );
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
		// redirect_admin_tools() only targets the bare tools.php screen
		// (`! isset( $_GET['page'] )`), so a subpage must stay untouched.
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
