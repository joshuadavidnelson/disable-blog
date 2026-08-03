/**
 * Admin-side redirect filters exposed by `Disable_Blog_Admin::redirect_admin_pages()`
 * (includes/class-disable-blog-admin.php:200), exercised via the
 * `dwpb-test-redirects.php` mu-plugin fixture -- the admin-screen
 * counterpart to `frontend/redirect-mechanics.spec.ts`, which covers the
 * same fixture's front-end-only filters.
 *
 * COVERAGE:
 *  - `dwpb_redirect_admin` -- the admin kill switch, forced off by the
 *    `adminRedirectsOff` toggle. Applied at
 *    includes/class-disable-blog-admin.php:306
 *    (`if ( $redirect_url && apply_filters( 'dwpb_redirect_admin', true, $redirect_url ) )`),
 *    gating the call into `Disable_Blog_Functions::redirect()` regardless of
 *    which per-page check decided a redirect was warranted (test 1).
 *  - `dwpb_redirect_status_code` -- proven, here, to ALSO govern admin
 *    redirects, not just front-end ones (test 2; see "DEVIATION FROM THE
 *    BRIEF" below for why this replaces a `dwpb_admin_redirect_url` test
 *    this file was originally asked to contain).
 *  - Isolation between the front-end and admin kill switches: the
 *    `frontEndRedirectsOff` toggle (`dwpb_redirect_front_end`) is only ever
 *    read by `Disable_Blog_Public::redirect_public_pages()`
 *    (includes/class-disable-blog-public.php:137) and has no reach into
 *    `redirect_admin_pages()` at all -- test 3 is the isolation control
 *    proving that.
 *
 * DEVIATION FROM THE BRIEF: this file was specced to include a test named
 * "the custom admin redirect URL filter is honored", using the
 * `customRedirectUrl` toggle against a `dwpb_admin_redirect_url` filter.
 * That filter genuinely exists (includes/class-disable-blog-admin.php:296,
 * `apply_filters( 'dwpb_admin_redirect_url', $redirect_url )`, applied
 * globally to every admin screen `redirect_admin_pages()` considers) --
 * but `tests/e2e/fixtures/dwpb-test-redirects.php` does NOT wire
 * `customRedirectUrl` (`dwpb_test_custom_redirect_url`) to it. Per that
 * fixture's own option -> filter table, the toggle only ever filters
 * `dwpb_front_end_redirect_url` (the front-end target), which
 * `frontend/redirect-mechanics.spec.ts` already covers. Writing the
 * originally-specced test as-is would exercise nothing (the toggle has no
 * observable effect on any admin screen) and silently pass for the wrong
 * reason. Per this task's own instruction that "the source [and fixture]
 * wins" over the brief, test 2 below asserts a DIFFERENT, genuinely-wired
 * piece of the same admin redirect filter API instead:
 * `dwpb_redirect_status_code` is read by the single `redirect()` method
 * both the front-end and admin redirect paths share
 * (includes/class-disable-blog-functions.php:29, :161), so the
 * `redirectStatusCode` toggle -- already proven front-end-only in
 * `frontend/redirect-mechanics.spec.ts` -- is shown here to affect admin
 * redirects identically. Flagged for the coordinator: if a later fixture
 * revision adds an admin-specific custom-URL filter hook, the originally
 * -specced test can be added back; nothing here should be read as that
 * filter not existing in the plugin, only as it not being reachable from
 * this fixture today.
 *
 * REQUEST LAYER, NOT NAVIGATION: every assertion goes through
 * `expectRedirect()`, or a direct `request.get( ..., { maxRedirects: 0 } )`
 * where the expected outcome isn't a clean redirect/200 pair (test 1's kill
 * switch -- see its inline comment), for the same Chromium 301-caching
 * reason documented in `config/redirects.ts` and every other redirect spec
 * in this suite.
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override -- every screen here
 * requires an authenticated admin, which the project default already is
 * (see `admin/admin-redirects.spec.ts`'s docblock for why: an anonymous
 * visitor never reaches `redirect_admin_pages()` at all, since core's own
 * `auth_redirect()` fires first).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig } from '../../config/seed';
import { adminUrl } from '../../config/admin';
import { expectRedirect } from '../../config/redirects';
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

/**
 * Toggles this spec uses, reset together in `afterEach` so a failing
 * assertion mid-test can never leak a fixture into the next test.
 */
const TOGGLES_USED = [
	FIXTURE_TOGGLES.adminRedirectsOff,
	FIXTURE_TOGGLES.redirectStatusCode,
	FIXTURE_TOGGLES.frontEndRedirectsOff,
];

test.describe( 'filters: admin redirect filters (fixture-driven)', () => {
	let editPageTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );

		// Same construction as admin/admin-redirects.spec.ts's editPageTarget:
		// admin_url() is always absolute, so the expected Location must be
		// built the same way.
		editPageTarget = `${ config.homeUrl }${ adminUrl( 'edit.php?post_type=page' ) }`;
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetFixtures( requestUtils, TOGGLES_USED );
	} );

	test( 'the admin kill switch disables admin redirects', async ( {
		request,
		requestUtils,
	} ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.adminRedirectsOff ]: true,
		} );

		// edit.php normally 301s to edit.php?post_type=page (see
		// admin/admin-redirects.spec.ts, "the posts list redirects to the
		// pages list") -- with dwpb_redirect_admin forced false, the
		// `$redirect_url && apply_filters( 'dwpb_redirect_admin', ... )`
		// gate at includes/class-disable-blog-admin.php:306 never calls
		// into redirect(), so the plugin's own 301 to the pages list never
		// fires. Assert that directly, with redirect-following disabled so
		// the response inspected is the one WordPress actually sent.
		const response = await request.get( adminUrl( 'edit.php' ), {
			maxRedirects: 0,
		} );

		expect( response.status(), 'plugin redirect must not fire' ).not.toBe( 301 );
		expect(
			response.headers()[ 'location' ] ?? null,
			'plugin redirect must not fire'
		).not.toBe( editPageTarget );

		// "No redirect" does NOT mean "200": Disable_Blog_Admin::
		// modify_post_type_arguments() (includes/class-disable-blog-admin.php)
		// separately sets `show_ui => false` on the 'post' post type,
		// independently of dwpb_redirect_admin -- so edit.php is inaccessible
		// regardless of whether the plugin's redirect ran. Core's own
		// current_user_can() check then wp_die()s, which defaults to a 500
		// response. That is the toggle's genuine, honest observable effect
		// here: the plugin's 301 is gone, but the screen underneath it was
		// never reachable in the first place. Kept on its own line so the
		// expected status is trivially adjustable if core's wp_die() default
		// ever changes.
		const status = response.status();
		expect( status ).toBe( 500 );

		const body = await response.text();
		expect( body ).toContain(
			'Sorry, you are not allowed to edit posts in this post type.'
		);

		// Control, same test: reset the toggle and confirm the redirect
		// returns, proving the assertions above were caused by the fixture
		// and not some unrelated change to edit.php's default behaviour.
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.adminRedirectsOff ] );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );
	} );

	test( 'the redirect status code filter also governs admin redirects', async ( {
		request,
		requestUtils,
	} ) => {
		// See the file docblock's "DEVIATION FROM THE BRIEF" section: this
		// replaces an originally-specced dwpb_admin_redirect_url test that
		// the fixture does not actually wire up. dwpb_redirect_status_code
		// is read inside the single Disable_Blog_Functions::redirect()
		// method both the front-end and admin paths call through, so the
		// override proven front-end-only in
		// frontend/redirect-mechanics.spec.ts ("the status code filter is
		// honored") must equally affect this admin redirect.
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.redirectStatusCode ]: 302,
		} );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 302 );
	} );

	test( 'the front-end kill switch leaves admin redirects intact', async ( {
		request,
		requestUtils,
	} ) => {
		// Isolation control: dwpb_redirect_front_end is only ever read by
		// Disable_Blog_Public::redirect_public_pages()
		// (includes/class-disable-blog-public.php:137), which
		// redirect_admin_pages() never calls into -- flipping only this
		// toggle must leave the admin redirect completely unaffected.
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.frontEndRedirectsOff ]: true,
		} );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );
	} );
} );
