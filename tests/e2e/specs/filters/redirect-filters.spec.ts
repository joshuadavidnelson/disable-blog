/**
 * Admin-side redirect filters exposed by `redirect_admin_pages()`, via the
 * `dwpb-test-redirects.php` mu-plugin fixture — the admin-screen counterpart
 * to `frontend/redirect-mechanics.spec.ts`. Covers `dwpb_redirect_admin` (the
 * admin kill switch), `dwpb_redirect_status_code` (proven here to also
 * govern admin redirects, since both paths share the same `redirect()`
 * method), and isolation showing the front-end kill switch
 * (`dwpb_redirect_front_end`) has no reach into admin redirects.
 *
 * Note: `dwpb_admin_redirect_url` exists in the plugin but isn't wired by
 * this fixture (`customRedirectUrl` only filters the front-end target), so
 * it has no dedicated test here.
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
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

// Reset together in afterEach so a failing assertion can't leak a fixture.
const TOGGLES_USED = [
	FIXTURE_TOGGLES.adminRedirectsOff,
	FIXTURE_TOGGLES.redirectStatusCode,
	FIXTURE_TOGGLES.frontEndRedirectsOff,
];

test.describe( 'filters: admin redirect filters (fixture-driven)', () => {
	let editPageTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );

		editPageTarget = `${ config.homeUrl }${ editPhp( 'page' ) }`;
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
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.adminRedirectsOff ] );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );
	} );

	test( 'the redirect status code filter also governs admin redirects', async ( {
		request,
		requestUtils,
	} ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.redirectStatusCode ]: 302,
		} );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 302 );
	} );

	test( 'the front-end kill switch leaves admin redirects intact', async ( {
		request,
		requestUtils,
	} ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.frontEndRedirectsOff ]: true,
		} );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );
	} );
} );
