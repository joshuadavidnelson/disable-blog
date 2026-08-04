/**
 * The wp-admin menu (`#adminmenu`), default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::remove_menu_pages()`, hooked on
 * `admin_menu`, unconditionally removes the Posts top-level menu and the
 * "Available Tools" submenu entry. Comments/Discussion links are gated on
 * `! dwpb_post_types_with_feature( 'comments' )`; the Writing settings link
 * on `dwpb_remove_options_writing`. Separately,
 * `modify_taxonomies_arguments()` hides the `category`/`post_tag` menu links
 * whenever `dwpb_post_types_with_tax()` is false for that taxonomy.
 *
 * Default-state gating: comments stay supported by `page`/`attachment`, so
 * Comments/Discussion links remain (tests 3, 6); `dwpb_remove_options_writing`
 * defaults false, so Writing stays (test 5); `category`/`post_tag` are used
 * only by the disabled `post` type, so their menu links are gone (test 2).
 *
 * Every test visits `edit.php?post_type=page`, which is not one of the
 * screens `redirect_admin_pages()` redirects (see `admin-redirects.spec.ts`).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { MENU_POSTS, MENU_PAGES, MENU_COMMENTS, MENU_TOOLS, menuLink } from '../../config/admin';

// Scoped to `.wp-submenu`: the top-level Tools anchor also links to
// tools.php (core duplicates the parent slug in its first submenu item), so
// an unscoped selector would still match after the submenu entry is removed.
const TOOLS_AVAILABLE_TOOLS_LINK = '#menu-tools .wp-submenu a[href="tools.php"]';

test.describe( 'admin: adminmenu (default state)', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );
	} );

	test( 'the Posts menu is removed', async ( { page } ) => {
		await expect( page.locator( MENU_POSTS ) ).toHaveCount( 0 );

		// Control: proves the admin menu rendered at all.
		await expect( page.locator( MENU_PAGES ) ).toBeVisible();
	} );

	test( 'no Categories or Tags submenu links remain', async ( { page } ) => {
		await expect( menuLink( page, 'edit-tags.php?taxonomy=category' ) ).toHaveCount( 0 );
		await expect( menuLink( page, 'edit-tags.php?taxonomy=post_tag' ) ).toHaveCount( 0 );

		// Control: proves the menu itself rendered.
		await expect( page.locator( '#adminmenu' ) ).toBeVisible();
	} );

	test( 'the Comments menu is present by default', async ( { page } ) => {
		await expect( page.locator( MENU_COMMENTS ) ).toBeVisible();
	} );

	test( 'Available Tools is removed from the Tools menu', async ( { page } ) => {
		await expect( page.locator( TOOLS_AVAILABLE_TOOLS_LINK ) ).toHaveCount( 0 );

		// Control: the Tools top-level menu itself is untouched.
		await expect( page.locator( MENU_TOOLS ) ).toBeVisible();
	} );

	test( 'Settings has a Writing link by default', async ( { page } ) => {
		await expect( menuLink( page, 'options-writing.php' ) ).toBeVisible();
	} );

	test( 'Settings has a Discussion link by default', async ( { page } ) => {
		await expect( menuLink( page, 'options-discussion.php' ) ).toBeVisible();
	} );
} );
