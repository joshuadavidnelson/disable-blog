/**
 * Back-compat coverage for the two `dpwb_`-prefixed filters
 * `Disable_Blog_Admin::manage_users_columns()` fires on `users.php`:
 * `dpwb_disable_user_post_column` and `dpwb_create_user_{$post_type}_column`
 * were misspelled -- the plugin's prefix is `dwpb_` everywhere else. Both now
 * have a correctly-spelled primary filter, with the old name still honored
 * via `apply_filters_deprecated()` (see `class-disable-blog-admin.php`).
 *
 * Each pair below is proven with the generic filter-override mechanism
 * (`config/filter-overrides.ts` + `dwpb-test-filters.php`, whose security
 * guard allows both the `dwpb_` and `dpwb_` prefixes) plus a control test
 * confirming the un-overridden default, so the override assertions can't
 * pass vacuously (e.g. if the column were unconditionally present/absent
 * regardless of any filter).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	setFilterOverrides,
	resetFilterOverrides,
} from '../../config/filter-overrides';

test.afterEach( async ( { requestUtils } ) => {
	await resetFilterOverrides( requestUtils );
} );

test.describe( 'filters: dwpb_disable_user_post_column (users.php Posts column)', () => {
	test( 'control: the Posts column is removed with no override', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'users.php' );

		await expect( page.locator( '#posts' ) ).toHaveCount( 0 );
	} );

	test( 'the corrected filter name keeps the Posts column when false', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_disable_user_post_column: false,
		} );

		await admin.visitAdminPage( 'users.php' );

		await expect( page.locator( '#posts' ) ).toHaveCount( 1 );
	} );

	test( 'the deprecated filter name also keeps the Posts column when false', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		// Only the deprecated alias is overridden -- the modern
		// dwpb_disable_user_post_column filter is untouched, so this can only
		// come from the apply_filters_deprecated() forwarding.
		await setFilterOverrides( requestUtils, {
			dpwb_disable_user_post_column: false,
		} );

		await admin.visitAdminPage( 'users.php' );

		await expect( page.locator( '#posts' ) ).toHaveCount( 1 );
	} );
} );

test.describe( 'filters: dwpb_create_user_{$post_type}_column (users.php Pages column)', () => {
	test( 'control: the Pages column is present with no override', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'users.php' );

		await expect( page.locator( 'th#page' ) ).toHaveCount( 1 );
	} );

	test( 'the corrected filter name removes the Pages column when false', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_create_user_page_column: false,
		} );

		await admin.visitAdminPage( 'users.php' );

		await expect( page.locator( 'th#page' ) ).toHaveCount( 0 );
	} );

	test( 'the deprecated filter name also removes the Pages column when false', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		// Only the deprecated alias is overridden -- the modern
		// dwpb_create_user_{$post_type}_column filter is untouched, so this
		// can only come from the apply_filters_deprecated() forwarding.
		await setFilterOverrides( requestUtils, {
			dpwb_create_user_page_column: false,
		} );

		await admin.visitAdminPage( 'users.php' );

		await expect( page.locator( 'th#page' ) ).toHaveCount( 0 );
	} );
} );
