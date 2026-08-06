/**
 * `dwpb_menu_pages_to_remove` and `dwpb_menu_subpages_to_remove`, via the
 * generic filter-override mechanism, with a filter-supplied addition rather
 * than just the plugin's own defaults (admin-menu.spec.ts).
 *
 * `dwpb_menu_pages_to_remove` filters a flat, numerically-indexed array, so
 * `{ append: [...] }` (`array_merge()`) adds a new entry cleanly.
 *
 * `dwpb_menu_subpages_to_remove` filters a `'parent.php' => [subpages]` map
 * instead. `array_merge()`'s append semantics only add new *integer* keys,
 * so appending here would land the pair under a numeric key rather than the
 * intended parent slug, and `remove_submenu_page()` would then be called
 * with that integer as the parent -- matching nothing. This block uses
 * `{ set: ... }` instead, supplying the whole map explicitly, which also
 * means the override REPLACES the plugin's default map rather than adding
 * to it -- deliberately omitting its `'tools.php' => [ 'tools.php' ]` entry
 * to prove that replacement below.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { MENU_MEDIA, MENU_PAGES, MENU_TOOLS, MENU_SETTINGS, menuLink } from '../../config/admin';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

// Scoped to `.wp-submenu`: the top-level Tools anchor also links to
// tools.php (core duplicates the parent slug in its first submenu item), so
// an unscoped selector would still match even once the submenu entry itself
// is gone. Mirrors admin-menu.spec.ts.
const TOOLS_AVAILABLE_TOOLS_LINK = '#menu-tools .wp-submenu a[href="tools.php"]';

test.describe( 'filters: dwpb_menu_pages_to_remove (append)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_menu_pages_to_remove: { append: [ 'upload.php' ] },
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'the appended page is removed from the admin menu', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( page.locator( MENU_MEDIA ) ).toHaveCount( 0 );

		// Control: a top-level page not named in the override stays untouched.
		await expect( page.locator( MENU_PAGES ) ).toBeVisible();
	} );
} );

test.describe( 'filters: dwpb_menu_subpages_to_remove (set)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			// Deliberately omits the plugin's own default
			// 'tools.php' => [ 'tools.php' ] entry -- see the file docblock.
			dwpb_menu_subpages_to_remove: {
				set: {
					'options-general.php': [ 'options-permalink.php' ],
				},
			},
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'the overridden subpage is removed from Settings', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( menuLink( page, 'options-permalink.php' ) ).toHaveCount( 0 );

		// Control: Settings itself, and an entry the override left out
		// (Writing, which the plugin's own defaults keep -- see
		// filters/options-writing.spec.ts), stay visible.
		await expect( page.locator( MENU_SETTINGS ) ).toBeVisible();
		await expect( menuLink( page, 'options-writing.php' ) ).toBeVisible();
	} );

	test( 'omitting the default entry reinstates Available Tools', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		// Removed by the plugin's own default (admin-menu.spec.ts), but with
		// no 'tools.php' key left in the replacement map, it comes back.
		await expect( page.locator( TOOLS_AVAILABLE_TOOLS_LINK ) ).toBeVisible();
		await expect( page.locator( MENU_TOOLS ) ).toBeVisible();
	} );
} );
