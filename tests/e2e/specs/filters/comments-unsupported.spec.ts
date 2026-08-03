/**
 * wp-admin behaviour when `dwpb_post_types_with_feature( 'comments' )` is
 * forced false, via the `commentsUnsupported` fixture toggle
 * (`dwpb-test-comments-unsupported.php`, which filters
 * `dwpb_post_types_supporting_comments` to `false`).
 *
 * DEFAULT STATE VS. THIS SPEC: `page`/`attachment` support comments by
 * default in this environment, so `admin/admin-menu.spec.ts` and
 * `admin/admin-redirects.spec.ts` both document the Comments menu,
 * Discussion settings, and admin-bar comments bubble as PRESENT, and
 * `edit-comments.php`/`options-discussion.php` as NOT redirecting. This spec
 * flips that gate and asserts the opposite for each of those four surfaces.
 *
 * GATING IS RE-EVALUATED PER REQUEST, NOT FROZEN AT BOOTSTRAP: this toggle
 * only works because the code paths under test read
 * `dwpb_post_types_with_feature( 'comments' )` live, on every request:
 *  - `Disable_Blog_Admin::remove_menu_pages()` (includes/class-disable-blog-admin.php:465),
 *    hooked on `admin_menu`, checks it directly (tests 1-2);
 *  - `Disable_Blog_Admin::redirect_admin_edit_comments()` /
 *    `redirect_admin_options_discussion()` (:397, :414), called from
 *    `redirect_admin_pages()` on `current_screen`, check it directly
 *    (tests 3-4);
 *  - `Disable_Blog_Admin::remove_admin_bar_links()` (:573), hooked on
 *    `wp_before_admin_bar_render`, checks it directly (test 5).
 * By contrast, `includes/class-disable-blog.php` (~:273) gates an unrelated
 * batch of comment-count/comment-filtering hooks behind
 * `if ( dwpb_post_types_with_feature( 'comments' ) )` at PLUGIN CONSTRUCTION
 * time (once per request, during `define_admin_hooks()`) rather than at call
 * time -- but since that value is re-read fresh from the (already-updated)
 * option on every new request regardless, the toggle still takes effect on
 * the very next request either way. None of the five surfaces above are
 * behind that particular `if` block, so this spec does not exercise it
 * directly; it is noted here only because the task brief for this spec
 * flagged it as worth confirming before trusting the toggle.
 *
 * BOOLEAN REDIRECTS TARGET admin_url( 'index.php' ): both
 * `redirect_admin_edit_comments()` and `redirect_admin_options_discussion()`
 * return a plain boolean `true` once comments are unsupported.
 * `redirect_admin_pages()` special-cases a literal `true` return to redirect
 * to `admin_url( 'index.php' )` specifically (rather than running it through
 * `esc_url_raw()`, which would mangle a boolean -- see
 * `admin/admin-redirects.spec.ts`'s DEFECT D3 for the bug that guard fixes)
 * -- matching `admin/admin-redirects.spec.ts`'s `dashboardTarget` construction.
 *
 * REQUEST LAYER FOR REDIRECTS: tests 3-4 use `expectRedirect()`, not
 * `page.goto()`, for the same Chromium 301-caching reason documented in
 * `config/redirects.ts` and every other redirect spec in this suite.
 *
 * SHARED TOGGLE STATE: all six tests run under a single `beforeAll`/`afterAll`
 * toggle rather than a per-test `beforeEach`/`afterEach`, since every test
 * in this file wants the same state and none of them mutate content that a
 * neighboring test depends on.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig } from '../../config/seed';
import {
	MENU_COMMENTS,
	MENU_PAGES,
	MENU_SETTINGS,
	ADMIN_BAR_COMMENTS,
	adminUrl,
	menuLink,
} from '../../config/admin';
import { expectRedirect } from '../../config/redirects';
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

/**
 * Parent "+ New" dropdown node (`#wp-admin-bar-new-content`).
 *
 * Not centralized in `config/admin.ts` -- same reasoning as the identical
 * local constant in `admin/admin-bar.spec.ts` and
 * `frontend/search-and-head.spec.ts`: only needed here as a control proving
 * the toolbar itself rendered.
 */
const ADMIN_BAR_NEW_CONTENT = '#wp-admin-bar-new-content';

test.describe( 'filters: comments unsupported (dwpb_post_types_supporting_comments === false)', () => {
	let dashboardTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		dashboardTarget = `${ config.homeUrl }${ adminUrl( 'index.php' ) }`;

		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.commentsUnsupported ]: true,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.commentsUnsupported ] );
	} );

	test( 'the Comments menu is removed', async ( { admin, page } ) => {
		// edit.php?post_type=page is not itself an admin redirect target
		// (see admin/admin-menu.spec.ts's docblock), so landing here proves
		// the menu assertions below aren't reading a mid-redirect screen.
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( page.locator( MENU_COMMENTS ) ).toHaveCount( 0 );

		// Control: proves the admin menu rendered at all, rather than the
		// zero count above being trivially true against a blank page.
		await expect( page.locator( MENU_PAGES ) ).toBeVisible();
	} );

	test( 'the Discussion settings submenu is removed', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( menuLink( page, 'options-discussion.php' ) ).toHaveCount( 0 );

		// Control: proves the Settings menu itself (and therefore its
		// submenu) rendered, so the zero count above isn't vacuous.
		await expect( page.locator( MENU_SETTINGS ) ).toBeVisible();
	} );

	test( 'edit-comments.php redirects to the dashboard', async ( { request } ) => {
		await expectRedirect( request, adminUrl( 'edit-comments.php' ), dashboardTarget, 301 );
	} );

	test( 'options-discussion.php redirects to the dashboard', async ( { request } ) => {
		await expectRedirect(
			request,
			adminUrl( 'options-discussion.php' ),
			dashboardTarget,
			301
		);
	} );

	test( 'the admin-bar comments bubble is removed', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( ADMIN_BAR_COMMENTS ) ).toHaveCount( 0 );

		// Control: proves the toolbar's "+ New" dropdown rendered, so the
		// zero count above is about the comments node specifically, not a
		// toolbar that failed to render at all.
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toBeVisible();
	} );

	test( 'the dashboard loads cleanly in this state', async ( { admin, page } ) => {
		// REGRESSION GUARD for a fixed defect (D6): assets/js/disable-blog-admin.js's
		// hideRow() used to do document.querySelector( '.welcome-icon.welcome-comments' ).parentNode
		// unguarded on the Dashboard 'index' case whenever commentsSupported
		// was false. WordPress 6.1+ no longer renders those classes, so the
		// selector returned null and the unguarded .parentNode access threw
		// an uncaught TypeError, silently killing every later case in that
		// same DOMContentLoaded handler. hideRow() now returns null instead
		// of throwing when nothing matches (see the function's own docblock)
		// -- this test pins that fix in place.
		//
		// PAGEERROR, NOT CONSOLE: an uncaught exception surfaces to
		// Playwright as a 'pageerror' event, never a 'console' event -- the
		// DevTools protocol keeps Runtime.exceptionThrown (uncaught errors)
		// and Runtime.consoleAPICalled (console.*() calls) distinct, and
		// Playwright's 'console' event only ever carries the latter. A
		// console-only listener would therefore pass here whether or not the
		// TypeError still fired -- it is not a substitute for the
		// 'pageerror' collector this assertion binds to.
		const pageErrors: string[] = [];
		const consoleErrors: string[] = [];

		page.on( 'pageerror', ( error ) => {
			pageErrors.push( error.message );
		} );
		page.on( 'console', ( message ) => {
			if ( 'error' === message.type() ) {
				consoleErrors.push( message.text() );
			}
		} );

		await admin.visitAdminPage( 'index.php' );

		expect(
			pageErrors,
			'Uncaught page error(s) on the Dashboard with commentsSupported === false ' +
				'(regression guard for the fixed D6 TypeError in ' +
				"assets/js/disable-blog-admin.js's hideRow()):\n" +
				pageErrors.join( '\n' ) +
				( consoleErrors.length
					? `\n\nconsole error(s) also seen:\n${ consoleErrors.join( '\n' ) }`
					: '' )
		).toEqual( [] );
	} );
} );
