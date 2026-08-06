/**
 * Admin-side redirect filters exposed by `redirect_admin_pages()`, via
 * `setFilterOverrides()` — the admin-screen counterpart to
 * `frontend/redirect-mechanics.spec.ts`. Covers `dwpb_redirect_admin` (the
 * admin kill switch), `dwpb_redirect_status_code` (proven here to also
 * govern admin redirects, since both paths share the same `redirect()`
 * method), and isolation showing the front-end kill switch
 * (`dwpb_redirect_front_end`) has no reach into admin redirects.
 *
 * Uses the project's default authenticated admin session throughout.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig } from '../../config/seed';
import { adminUrl, editPhp } from '../../config/admin';
import { expectRedirect } from '../../config/redirects';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

// Same host as home_url(): wp_safe_redirect() rejects an off-site Location.
const OVERRIDE_PATH = '/dwpb-test-filter-override-admin-url/';

test.describe( 'filters: admin redirect filters (override-driven)', () => {
	let editPageTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );

		editPageTarget = `${ config.homeUrl }${ editPhp( 'page' ) }`;
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'the admin kill switch disables admin redirects', async ( {
		request,
		requestUtils,
	} ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_redirect_admin: false,
		} );

		// With dwpb_redirect_admin forced false, the plugin's usual 301 to
		// the pages list never fires.
		const response = await request.get( adminUrl( 'edit.php' ), {
			maxRedirects: 0,
		} );

		expect( response.status(), 'plugin redirect must not fire' ).not.toBe( 301 );
		expect(
			response.headers()[ 'location' ] ?? null,
			'plugin redirect must not fire'
		).not.toBe( editPageTarget );

		// Not a 200 either: modify_post_type_arguments() separately sets
		// show_ui false on 'post', independently of dwpb_redirect_admin, so
		// edit.php is still inaccessible and core's own capability check
		// wp_die()s with 500.
		const status = response.status();
		expect( status ).toBe( 500 );

		const body = await response.text();
		expect( body ).toContain(
			'Sorry, you are not allowed to edit posts in this post type.'
		);

		// Control: reset and confirm the redirect returns.
		await resetFilterOverrides( requestUtils );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );
	} );

	test( 'the redirect status code filter also governs admin redirects', async ( {
		request,
		requestUtils,
	} ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_redirect_status_code: 302,
		} );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 302 );
	} );

	test( 'the front-end kill switch leaves admin redirects intact', async ( {
		request,
		requestUtils,
	} ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_redirect_front_end: false,
		} );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );
	} );
} );

test.describe( 'filters: global admin redirect override (dwpb_admin_redirect_url)', () => {
	let overrideUrl: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		overrideUrl = `${ config.homeUrl }${ OVERRIDE_PATH }`;

		await setFilterOverrides( requestUtils, {
			dwpb_admin_redirect_url: overrideUrl,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'edit.php redirects to the overridden URL instead of the pages list', async ( {
		request,
	} ) => {
		await expectRedirect( request, adminUrl( 'edit.php' ), overrideUrl );
	} );

	test( 'the override reaches a screen the plugin would not otherwise redirect', async ( {
		request,
	} ) => {
		// dwpb_admin_redirect_url runs unconditionally after the per-page
		// loop in redirect_admin_pages(), even when nothing in that loop
		// matched — unlike the per-page filters, it can force a redirect on
		// a screen the plugin otherwise leaves alone. The pages list is a
		// plain 200 by default (see admin-redirects.spec.ts), so a redirect
		// here can only come from this filter.
		await expectRedirect( request, editPhp( 'page' ), overrideUrl );
	} );
} );
