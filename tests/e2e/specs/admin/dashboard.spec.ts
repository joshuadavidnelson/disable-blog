/**
 * The wp-admin Dashboard (`index.php`), default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::remove_dashboard_widgets()`, hooked on
 * `admin_init`, removes `dashboard_quick_press`, `dashboard_activity`,
 * `dashboard_recent_drafts`, and `dashboard_incoming_links` unconditionally
 * (each via its own `dwpb_disable_{$metabox_id}` filter, all defaulting to
 * true) — only the first two are asserted here. Separately,
 * `disable-blog-admin.css` unconditionally hides `#dashboard_right_now`'s
 * post/comment counts (not the page count) regardless of the
 * `.disabled-blog` body class.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	DASHBOARD_QUICK_PRESS,
	DASHBOARD_ACTIVITY,
	DASHBOARD_PRIMARY,
	DASHBOARD_SITE_HEALTH,
	DASHBOARD_RIGHT_NOW,
	rightNowPostCount,
	rightNowPageCount,
	rightNowCommentCount,
} from '../../config/admin';

test.describe( 'admin: dashboard (default state)', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.visitAdminPage( 'index.php' );
	} );

	test( 'the Quick Draft and Activity widgets are removed', async ( { page } ) => {
		await expect( page.locator( DASHBOARD_QUICK_PRESS ) ).toHaveCount( 0 );
		await expect( page.locator( DASHBOARD_ACTIVITY ) ).toHaveCount( 0 );
	} );

	test( 'other core dashboard widgets remain', async ( { page } ) => {
		// Control: proves the Dashboard itself rendered.
		await expect( page.locator( DASHBOARD_RIGHT_NOW ) ).toBeVisible();

		// Neither widget is in the plugin's removal list; which one actually
		// renders varies by WP version, so this checks "at least one exists".
		const untouchedCoreWidgets = page.locator(
			`${ DASHBOARD_SITE_HEALTH }, ${ DASHBOARD_PRIMARY }`
		);

		await expect( untouchedCoreWidgets.first() ).toBeVisible();
	} );

	test( 'At a Glance hides the post and comment counts', async ( { page } ) => {
		// CSS-hidden, not removed, so toBeHidden() rather than toHaveCount( 0 ).
		await expect( rightNowPostCount( page ) ).toBeHidden();
		await expect( rightNowCommentCount( page ) ).toBeHidden();
	} );

	test( 'At a Glance still shows the page count', async ( { page } ) => {
		// Control: proves the CSS rule targets posts/comments specifically.
		await expect( rightNowPageCount( page ) ).toBeVisible();
	} );
} );
