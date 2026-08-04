/**
 * `dwpb_disable_dashboard_quick_press`, via the generic filter-override
 * mechanism (`config/filter-overrides.ts` + `dwpb-test-filters.php`) — one of
 * the four filters `remove_dashboard_widgets()` builds as
 * `"dwpb_disable_{$metabox_id}"`, all defaulting `true` (see
 * admin/dashboard.spec.ts for the default-removed state).
 *
 * Forced `false`, `remove_meta_box( 'dashboard_quick_press', ... )` never
 * runs, so Quick Draft reappears on the Dashboard.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { DASHBOARD_QUICK_PRESS, DASHBOARD_ACTIVITY } from '../../config/admin';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

test.describe( 'filters: dashboard quick press override (dwpb_disable_dashboard_quick_press)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_disable_dashboard_quick_press: false,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'the Quick Draft widget reappears', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( DASHBOARD_QUICK_PRESS ) ).toBeVisible();

		// Control: the sibling widget built by the same foreach loop, under
		// its own filter name, is still removed -- proves the override
		// targets only its own metabox id.
		await expect( page.locator( DASHBOARD_ACTIVITY ) ).toHaveCount( 0 );
	} );
} );
