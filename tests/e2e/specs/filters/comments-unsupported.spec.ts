/**
 * wp-admin behaviour when `dwpb_post_types_with_feature( 'comments' )` is
 * forced false via the `commentsUnsupported` fixture toggle. `page`/`attachment`
 * support comments by default in this environment, so this spec flips that
 * gate and asserts the opposite of default-state coverage: the Comments menu,
 * Discussion settings submenu, and admin-bar comments bubble disappear, and
 * `edit-comments.php`/`options-discussion.php` redirect to the dashboard
 * (both return a plain boolean `true`, which `redirect_admin_pages()`
 * special-cases to `admin_url( 'index.php' )`).
 *
 * All six tests share one beforeAll/afterAll toggle since none mutate
 * content another test depends on.
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

// Parent "+ New" dropdown node, used as a control that the toolbar rendered.
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
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( page.locator( MENU_COMMENTS ) ).toHaveCount( 0 );
		await expect( page.locator( MENU_PAGES ) ).toBeVisible();
	} );

	test( 'the Discussion settings submenu is removed', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( menuLink( page, 'options-discussion.php' ) ).toHaveCount( 0 );
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
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toBeVisible();
	} );

	test( 'the dashboard loads cleanly in this state', async ( { admin, page } ) => {
		// Fixed since 0.5.5: hideRow() in disable-blog-admin.js threw an
		// uncaught TypeError on WP 6.1+'s Dashboard when commentsSupported was
		// false (querySelector returned null, .parentNode was unguarded),
		// silently killing later cases in the same handler. Now returns null
		// instead. An uncaught exception surfaces as 'pageerror', not
		// 'console', so that's what this listens for.
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
