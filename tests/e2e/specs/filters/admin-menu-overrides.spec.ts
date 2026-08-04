/**
 * `dwpb_menu_pages_to_remove` and `dwpb_menu_subpages_to_remove`, via the
 * generic filter-override mechanism (`config/filter-overrides.ts` +
 * `dwpb-test-filters.php`) — `remove_menu_pages()`'s two filters, already
 * exercised end-to-end by the plugin's own defaults (admin-menu.spec.ts) but
 * never with a filter-supplied addition.
 *
 * `dwpb_menu_pages_to_remove` filters a flat, numerically-indexed array of
 * top-level page files, so the mechanism's `{ append: [...] }` form
 * (`array_merge()`) adds a new entry cleanly.
 *
 * `dwpb_menu_subpages_to_remove` filters a `'parent.php' => array of
 * subpage file` MAP instead. `array_merge()`'s append semantics only ever
 * add new *integer* keys, so appending a page/subpage pair the way
 * `dwpb_menu_pages_to_remove` does above would land under a numeric key
 * instead of the intended parent slug — `remove_menu_pages()`'s foreach
 * would then call `remove_submenu_page()` with that integer as the parent,
 * which matches nothing. This block uses `{ set: ... }` instead, supplying
 * the whole map explicitly — which also means the override REPLACES rather
 * than adds to the plugin's own default map, deliberately omitting its
 * `'tools.php' => [ 'tools.php' ]` entry to prove that replacement (test 2).
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

		// Removed by the plugin's own default (admin-menu.spec.ts) but the
		// override above replaces the whole map rather than adding to it, so
		// with no 'tools.php' key left to iterate, remove_submenu_page() is
		// never called for it and the link comes back -- proof this is a
		// real replacement, not a merge.
		await expect( page.locator( TOOLS_AVAILABLE_TOOLS_LINK ) ).toBeVisible();
		await expect( page.locator( MENU_TOOLS ) ).toBeVisible();
	} );
} );
