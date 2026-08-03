/**
 * Admin-screen 301 redirects, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::redirect_admin_pages()`, hooked on
 * `current_screen`, walks a fixed list of admin page slugs
 * (`post`, `edit`, `post-new`, `edit-tags`, `term`, `edit-comments`,
 * `options-discussion`, `options-writing`, `tools`) and, on the first match,
 * calls the corresponding `redirect_admin_<slug>()` method to decide whether
 * (and where) to redirect. This spec exercises only `edit.php` and
 * `post-new.php` — see the SCOPE LIMIT below for why the rest are excluded —
 * plus the `edit-comments.php` / `options-discussion.php` pair, which are
 * proven to NOT redirect under this plugin's default state.
 *
 * `redirect_admin_edit()` and `redirect_admin_post_new()` both redirect
 * whenever `$_GET['post_type']` is absent OR equals `'post'`, always to the
 * `page` equivalent screen (`edit.php?post_type=page` /
 * `post-new.php?post_type=page`). `Disable_Blog_Functions::redirect()`
 * issues that redirect via `wp_safe_redirect()` at `301` by default (see
 * `get_redirect_status_code()`), which is what every assertion below checks.
 *
 * SCOPE LIMIT: this spec deliberately does NOT test `post.php`,
 * `edit-tags.php`, `term.php`, or `tools.php`. Their corresponding
 * `redirect_admin_*()` methods have known defects slated for a fix in a
 * later phase (e.g. `reidrect_admin_term()` — note the typo in the method
 * name itself — and `redirect_admin_options_tools()`'s
 * `! isset( $_GET['page'] )` check, which mis-redirects any tools.php
 * subpage added by a third-party plugin). Writing coverage for those now
 * would bake today's incorrect behaviour in as "expected".
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
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig } from '../../config/seed';
import { adminUrl } from '../../config/admin';
import { expectRedirect, expectStatus } from '../../config/redirects';

test.describe( 'admin: redirects (default state)', () => {
	let editPageTarget: string;
	let postNewPageTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );

		// admin_url() always returns an absolute URL, so the expected
		// Location header must be built the same way — homeUrl carries no
		// trailing slash (see dwpb-test-api.php's setup route), matching
		// admin_url()'s own output exactly.
		editPageTarget = `${ config.homeUrl }${ adminUrl( 'edit.php?post_type=page' ) }`;
		postNewPageTarget = `${ config.homeUrl }${ adminUrl( 'post-new.php?post_type=page' ) }`;
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
} );
