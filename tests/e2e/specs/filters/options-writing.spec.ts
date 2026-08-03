/**
 * The `dwpb_remove_options_writing` filter, exercised via the
 * `dwpb-test-options-writing.php` mu-plugin fixture rather than the plugin's
 * shipped default (`false`).
 *
 * COVERAGE: `Disable_Blog_Admin::remove_writing_options()`
 * (`dwpb_remove_options_writing`) gates two independent things once it
 * returns `true`:
 *  - `Disable_Blog_Admin::redirect_admin_options_writing()` returns
 *    `admin_url( 'options-general.php' )` -- a URL STRING, not the boolean
 *    `true` `redirect_admin_pages()`'s loop otherwise falls back to
 *    `$dashboard_url` for. That distinction matters: `admin/admin-redirects.spec.ts`'s
 *    DEFECT D3 coverage documents `esc_url_raw( true )` mis-resolving to
 *    `admin_url()` for handlers that return a plain boolean -- this handler
 *    never hits that branch at all, since it always returns either a real URL
 *    string or `false` (test 1).
 *  - `Disable_Blog_Admin::remove_menu_pages()` conditionally adds
 *    `options-writing.php` to `options-general.php`'s submenu removal list
 *    (test 2).
 *
 * REQUEST LAYER FOR REDIRECTS/STATUS: `expectRedirect()` / `expectStatus()`
 * from `config/redirects.ts`, same reasoning as every other spec in this
 * suite -- see that file's docblock.
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override -- every screen here
 * requires an authenticated session, matching `admin/admin-redirects.spec.ts`
 * and `admin/admin-menu.spec.ts`.
 *
 * TEST ORDER MATTERS: test 3 turns the fixture back off mid-file (not just in
 * `afterAll`) specifically to prove the reset mechanism itself restores stock
 * behaviour -- it must run after tests 1 and 2, which depend on the fixture
 * still being on. This suite runs one worker (see `settings-screens.spec.ts`'s
 * docblock), so tests execute in declaration order within a file.
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
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

test.describe( 'filters: remove options-writing (dwpb_remove_options_writing)', () => {
	let generalSettingsTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		generalSettingsTarget = `${ config.homeUrl }${ adminUrl( 'options-general.php' ) }`;

		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.removeOptionsWriting ]: true,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Idempotent even after test 3 already reset this toggle mid-file.
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.removeOptionsWriting ] );
	} );

	test( 'options-writing.php redirects to general settings', async ( { request } ) => {
		await expectRedirect( request, adminUrl( 'options-writing.php' ), generalSettingsTarget );
	} );

	test( 'the Writing submenu is removed', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( menuLink( page, 'options-writing.php' ) ).toHaveCount( 0 );

		// Control: proves the Settings menu itself rendered, so the absence
		// above isn't vacuously true against a menu that never loaded.
		await expect( page.locator( MENU_SETTINGS ) ).toBeVisible();
	} );

	test( 'toggling the filter off restores the screen', async ( { request, requestUtils } ) => {
		// Doubles as proof the reset mechanism (resetFixtures()) genuinely
		// restores stock behaviour, not just that this one toggle happens to
		// default to false.
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.removeOptionsWriting ] );

		await expectStatus( request, adminUrl( 'options-writing.php' ), 200 );
	} );
} );
