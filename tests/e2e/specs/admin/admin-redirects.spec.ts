/**
 * Admin-screen 301 redirects, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::redirect_admin_pages()`, hooked on
 * `current_screen`, walks a fixed list of admin page slugs
 * (`post`, `edit`, `post-new`, `edit-tags`, `term`, `edit-comments`,
 * `options-discussion`, `options-writing`, `tools`) and, on the first match,
 * calls the corresponding `redirect_admin_<slug>()` method to decide whether
 * (and where) to redirect. This spec exercises `edit.php` and `post-new.php`
 * for the plugin's correctly-working default behaviour, the
 * `edit-comments.php` / `options-discussion.php` pair (proven to NOT
 * redirect under this plugin's default state), and — see the FIXED
 * DEFECTS section below — `post.php`, `edit-tags.php`, `term.php`, and
 * `tools.php` as regression guards for three confirmed-and-fixed defects.
 *
 * `redirect_admin_edit()` and `redirect_admin_post_new()` both redirect
 * whenever `$_GET['post_type']` is absent OR equals `'post'`, always to the
 * `page` equivalent screen (`edit.php?post_type=page` /
 * `post-new.php?post_type=page`). `Disable_Blog_Functions::redirect()`
 * issues that redirect via `wp_safe_redirect()` at `301` by default (see
 * `get_redirect_status_code()`), which is what every assertion below checks.
 *
 * FIXED DEFECTS (D1-D3): `post.php`, `edit-tags.php`, `term.php`, and
 * `tools.php` are also covered below, exercising the CORRECTED behaviour for
 * three confirmed-and-fixed defects — every assertion in the "Regression
 * guards" block below now passes and must keep passing:
 *  - D1: `reidrect_admin_term()` was misspelled, so the loop's
 *    `is_callable()` check never found it and term.php was silently never
 *    redirected. Renamed to `redirect_admin_term()`.
 *  - D2: the loop's `'tools'` entry builds the function name
 *    `redirect_admin_tools`, but the only method defined was
 *    `redirect_admin_options_tools()` — the same `is_callable()` failure, so
 *    tools.php was never redirected either. Renamed to `redirect_admin_tools()`;
 *    the old `dwpb_redirect_admin_options_tools` filter name is still
 *    honored via `apply_filters_deprecated()`.
 *  - D3: `esc_url_raw( $redirect )` ran unconditionally, even when
 *    `$redirect` was the boolean `true` rather than a URL string.
 *    `esc_url_raw( true )` returns the non-empty string `"http://1"`, which
 *    won the `! empty( $potential_redirect_url )` branch over the intended
 *    `$dashboard_url` fallback. `wp_safe_redirect()` then rejected that
 *    bogus host and fell back to bare `admin_url()` (`/wp-admin/`) instead
 *    of `admin_url( 'index.php' )`. Fixed by testing `true === $redirect`
 *    before ever calling `esc_url_raw()`. Both `redirect_admin_post()` and
 *    `redirect_admin_edit_tags()` return plain booleans, so both post.php
 *    (editing a `post`) and edit-tags.php were affected — and so was
 *    term.php once D1's typo was fixed, since `redirect_admin_term()`
 *    returns the identical boolean expression.
 *
 * GATING CONDITION: `redirect_admin_edit_comments()` (and its
 * `redirect_admin_options_discussion()` wrapper) return
 * `! dwpb_post_types_with_feature( 'comments' )`. Comments remain supported
 * by `page`/`attachment` in this environment's default state, so that
 * expression is `false` and neither screen redirects — see test 6.
 *
 * REQUEST LAYER, NOT NAVIGATION: every assertion goes through
 * `expectRedirect()` / `expectStatus()` from `config/redirects.ts`, for the
 * same reason `frontend/redirects.spec.ts` does — Chromium's aggressive 301
 * caching could otherwise serve a stale redirect to a `page.goto()`-based
 * assertion. See that file's docblock for the full explanation.
 *
 * AUTHENTICATED BY DEFAULT: this spec runs no `test.use( { storageState } )`
 * override, so the `request` fixture inherits the project's default
 * `storageState` — the administrator (see `playwright.config.ts`) — which is
 * required, since every admin screen here 302s to `wp-login.php` for an
 * anonymous visitor before `redirect_admin_pages()` ever gets a chance to
 * run.
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

		// admin_url() always returns an absolute URL, so the expected
		// Location header must be built the same way — homeUrl carries no
		// trailing slash (see dwpb-test-api.php's setup route), matching
		// admin_url()'s own output exactly.
		editPageTarget = `${ config.homeUrl }${ adminUrl( 'edit.php?post_type=page' ) }`;
		postNewPageTarget = `${ config.homeUrl }${ adminUrl( 'post-new.php?post_type=page' ) }`;
		// D1/D2/D3 all redirect to the bare dashboard, not a post-type list —
		// see the file docblock's "FIXED DEFECTS" section.
		dashboardTarget = `${ config.homeUrl }${ adminUrl( 'index.php' ) }`;

		// Content for the D1/D3 regression guards below: a real post
		// (post.php), a real page (D3's "not redirected" control), and a
		// real category term (term.php).
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
		// Best-effort — deletePosts() never throws, see its docblock. The
		// seeded category term is deliberately left in place, matching this
		// suite's existing convention (see frontend/redirects.spec.ts's
		// tagSlug) — terms are only wiped by global-setup's reset-content at
		// the start of the next run.
		await deletePosts( requestUtils, seededIds );
	} );

	/* -------------------------------------------------------------------
	 * Redirected: redirect_admin_edit() / redirect_admin_post_new()
	 * ---------------------------------------------------------------- */

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

	/* -------------------------------------------------------------------
	 * Controls: prove the redirect is targeted, not a blanket catch-all
	 * ---------------------------------------------------------------- */

	test( 'the pages list is not redirected', async ( { request } ) => {
		// Control for tests 1-2: redirect_admin_edit() only matches when
		// post_type is absent or 'post' — an explicit ?post_type=page must
		// fall through untouched.
		await expectStatus( request, adminUrl( 'edit.php?post_type=page' ), 200 );
	} );

	test( 'the comments and discussion screens are not redirected by default', async ( {
		request,
	} ) => {
		// Gated on dwpb_post_types_with_feature( 'comments' ): pages and
		// attachments support comments by default in this environment, so
		// redirect_admin_edit_comments() (and its options-discussion wrapper)
		// evaluate to false and neither screen redirects. This is default-state
		// coverage only — a spec that flips comment support off belongs in a
		// later, filter-driven phase.
		await expectStatus( request, adminUrl( 'edit-comments.php' ), 200 );
		await expectStatus( request, adminUrl( 'options-discussion.php' ), 200 );
	} );

	/* -------------------------------------------------------------------
	 * Regression guards: corrected behaviour for D1, D2, D3
	 * ---------------------------------------------------------------- */

	test( 'regression guard: term.php redirects to the dashboard', async ( { request } ) => {
		// D1: reidrect_admin_term() was misspelled, so the redirect loop's
		// is_callable() check never found it and term.php silently fell
		// through with no redirect at all (200, not 301). Fixed by renaming
		// it to redirect_admin_term().
		await expectRedirect(
			request,
			termPhp( 'category', categoryTerm.termId ),
			dashboardTarget
		);
	} );

	test( 'regression guard: tools.php redirects to the dashboard', async ( { request } ) => {
		// D2: the redirect loop's 'tools' entry builds the function name
		// redirect_admin_tools, but the only method defined was
		// redirect_admin_options_tools() — the same is_callable() failure, so
		// tools.php was never redirected. Fixed by renaming the method to
		// redirect_admin_tools().
		await expectRedirect( request, adminUrl( 'tools.php' ), dashboardTarget );
	} );

	test( 'regression guard control: a third-party tools.php subpage is not redirected by the plugin', async ( {
		request,
	} ) => {
		// redirect_admin_tools() returns ! isset( $_GET['page'] ) specifically
		// so third-party option pages hosted under Tools keep working — this
		// must stay untouched by the plugin now that the D2 fix makes
		// tools.php reachable at all, proving the fix targets the bare
		// tools.php screen, not every tools.php request.
		//
		// NOT A BARE 200/301 CHECK: verified live, core itself returns a 302
		// for `tools.php?page=some-plugin-page` — an unregistered admin page
		// slug redirects on its own in stock WordPress, entirely unrelated to
		// this plugin. So this assertion does not check for a bare status
		// code; it checks specifically that the response is NOT the plugin's
		// own 301-to-dashboard, which is the only thing redirect_admin_tools()
		// could be responsible for.
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
		// D3: redirect_admin_post() returns a plain boolean true for a real
		// 'post' id, not a URL string. esc_url_raw( true ) resolving to the
		// non-empty "http://1" used to send this to bare /wp-admin/
		// (wp_safe_redirect()'s host-rejection fallback) instead of
		// admin_url( 'index.php' ). Fixed by testing true === $redirect
		// before ever calling esc_url_raw().
		await expectRedirect( request, postPhp( seededPost.id ), dashboardTarget );
	} );

	test( 'regression guard control: editing a page is still not redirected', async ( {
		request,
	} ) => {
		// redirect_admin_post() only matches 'post' == get_post_type(...); a
		// page id must fall through untouched, both before and after the D3
		// fix.
		await expectStatus( request, postPhp( seededPage.id ), 200 );
	} );

	test( 'regression guard: a category edit-tags.php screen redirects to the dashboard, not bare /wp-admin/', async ( {
		request,
	} ) => {
		// D3: same esc_url_raw( true ) => "http://1" bug as the post.php case
		// above, since redirect_admin_edit_tags() also returns a plain
		// boolean.
		await expectRedirect( request, editTagsPhp( 'category' ), dashboardTarget );
	} );

	test( 'regression guard: a post_tag edit-tags.php screen redirects to the dashboard, not bare /wp-admin/', async ( {
		request,
	} ) => {
		await expectRedirect( request, editTagsPhp( 'post_tag' ), dashboardTarget );
	} );
} );
