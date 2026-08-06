/**
 * wp-admin behaviour when `dwpb_post_types_with_feature( 'comments' )` is
 * forced false via a `dwpb_post_types_supporting_comments` override.
 * `page`/`attachment` support comments by default in this environment, so
 * this spec flips that gate and asserts the opposite of default-state
 * coverage: the Comments menu, Discussion settings submenu, and admin-bar
 * comments bubble disappear, and `edit-comments.php`/`options-discussion.php`
 * redirect to the dashboard (both return a plain boolean `true`, which
 * `redirect_admin_pages()` special-cases to `admin_url( 'index.php' )`).
 *
 * All five tests share one beforeAll/afterAll override since none mutate
 * content another test depends on.
 *
 * The Dashboard's JS-console regression guard for this state lives in
 * `admin/dashboard-console.spec.ts` instead of here — that spec binds
 * `pageerror` and pairs it with a control proving the script still runs
 * elsewhere in this state, which this file's other tests don't need to
 * duplicate.
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
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

// Parent "+ New" dropdown node, used as a control that the toolbar rendered.
const ADMIN_BAR_NEW_CONTENT = '#wp-admin-bar-new-content';

test.describe( 'filters: comments unsupported (dwpb_post_types_supporting_comments === false)', () => {
	let dashboardTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		dashboardTarget = `${ config.homeUrl }${ adminUrl( 'index.php' ) }`;

		await setFilterOverrides( requestUtils, {
			dwpb_post_types_supporting_comments: false,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
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
} );
