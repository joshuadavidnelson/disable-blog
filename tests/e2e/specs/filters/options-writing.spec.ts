/**
 * The `dwpb_remove_options_writing` filter (shipped default: `false`), via
 * `setFilterOverrides()`. Once true, `remove_writing_options()` redirects
 * `options-writing.php` to `options-general.php` and drops it from the
 * Settings submenu.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig } from '../../config/seed';
import { adminUrl, menuLink, MENU_SETTINGS } from '../../config/admin';
import { expectRedirect, expectStatus } from '../../config/redirects';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

test.describe( 'filters: remove options-writing (dwpb_remove_options_writing)', () => {
	let generalSettingsTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		generalSettingsTarget = `${ config.homeUrl }${ adminUrl( 'options-general.php' ) }`;
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_remove_options_writing: true,
		} );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'options-writing.php redirects to general settings', async ( { request } ) => {
		await expectRedirect( request, adminUrl( 'options-writing.php' ), generalSettingsTarget );
	} );

	test( 'the Writing submenu is removed', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( menuLink( page, 'options-writing.php' ) ).toHaveCount( 0 );
		await expect( page.locator( MENU_SETTINGS ) ).toBeVisible();
	} );

	test( 'toggling the filter off restores the screen', async ( { request, requestUtils } ) => {
		await resetFilterOverrides( requestUtils );

		await expectStatus( request, adminUrl( 'options-writing.php' ), 200 );
	} );
} );
