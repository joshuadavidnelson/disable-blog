/**
 * The wp-admin Dashboard (`index.php`), default plugin state.
 *
 * COVERAGE:
 *  - `Disable_Blog_Admin::remove_dashboard_widgets()`, hooked on
 *    `admin_init`, loops a fixed metabox list and, for each one, calls
 *    `remove_meta_box()` when its `dwpb_disable_{$metabox_id}` filter is
 *    true — which every one of them defaults to. Of that list, this spec
 *    only asserts on `dashboard_quick_press` (Quick Draft) and
 *    `dashboard_activity` (Activity); `dashboard_recent_drafts` and
 *    `dashboard_incoming_links` are removed by the exact same unconditional
 *    mechanism and are not separately re-proven here.
 *  - The plugin's own admin stylesheet
 *    (`assets/css/disable-blog-admin.css`, enqueued admin-wide via
 *    `admin_enqueue_scripts` — see `class-disable-blog.php`) sets
 *    `#dashboard_right_now .post-count, #dashboard_right_now .comment-count`
 *    to `display: none`. This rule is unconditional — NOT scoped under the
 *    `.disabled-blog` body class `admin_body_class()` conditionally adds —
 *    so it hides those two counters regardless of whether a static front
 *    page is configured. `.page-count` is deliberately absent from that CSS
 *    rule and stays visible.
 *
 * NAVIGATION TARGET: `index.php` is not in `redirect_admin_pages()`'s
 * `$admin_redirects` list at all, so it is safe to visit directly with no
 * redirect risk to account for.
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override — the project default
 * is already the administrator (see `playwright.config.ts`), which is what
 * every dashboard widget here requires to render in the first place.
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
		// Control: proves remove_dashboard_widgets() targeted its fixed
		// metabox list specifically, rather than the Dashboard screen (or its
		// widgets area) failing to render at all.
		await expect( page.locator( DASHBOARD_RIGHT_NOW ) ).toBeVisible();

		// Neither dashboard_site_health nor dashboard_primary is in
		// remove_dashboard_widgets()'s metabox list, so nothing in the plugin
		// removes either — but which of the two actually renders on a given
		// WordPress version/environment isn't asserted by the plugin itself,
		// so this checks "at least one exists" rather than assuming both.
		const untouchedCoreWidgets = page.locator(
			`${ DASHBOARD_SITE_HEALTH }, ${ DASHBOARD_PRIMARY }`
		);

		await expect( untouchedCoreWidgets.first() ).toBeVisible();
	} );

	test( 'At a Glance hides the post and comment counts', async ( { page } ) => {
		// disable-blog-admin.css hides these unconditionally (not gated behind
		// the .disabled-blog body class) — the elements are still rendered by
		// core's "At a Glance" widget, just CSS-hidden, so toBeHidden() (not a
		// toHaveCount( 0 ) presence check) is the correct assertion.
		await expect( rightNowPostCount( page ) ).toBeHidden();
		await expect( rightNowCommentCount( page ) ).toBeHidden();
	} );

	test( 'At a Glance still shows the page count', async ( { page } ) => {
		// Control for the test above: .page-count is deliberately absent from
		// disable-blog-admin.css's hide rule, proving the CSS is targeted at
		// posts/comments specifically, not the whole "At a Glance" widget.
		await expect( rightNowPageCount( page ) ).toBeVisible();
	} );
} );
