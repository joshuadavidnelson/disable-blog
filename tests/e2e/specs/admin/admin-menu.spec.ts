/**
 * The wp-admin menu (`#adminmenu`), default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::remove_menu_pages()`, hooked on
 * `admin_menu`, unconditionally `remove_menu_page( 'edit.php' )`s (the Posts
 * top-level menu) and `remove_submenu_page( 'tools.php', 'tools.php' )`s
 * (the "Available Tools" entry). Everything else it touches is gated:
 *  - `edit-comments.php` (top-level Comments menu) and
 *    `options-general.php > options-discussion.php` (Settings > Discussion)
 *    are only removed when `! dwpb_post_types_with_feature( 'comments' )`.
 *  - `options-general.php > options-writing.php` (Settings > Writing) is
 *    only removed when `Disable_Blog_Admin::remove_writing_options()`
 *    (the `dwpb_remove_options_writing` filter) is true.
 * `Disable_Blog_Admin::modify_taxonomies_arguments()`, hooked on `init`,
 * separately sets `show_in_menu` to `false` on the `category` and `post_tag`
 * taxonomies whenever `dwpb_post_types_with_tax( $tax )` is false — i.e.
 * whenever no post type other than the disabled `post` still uses them —
 * which removes their menu links as a side effect of the taxonomy's own
 * registration rather than an explicit `remove_submenu_page()` call.
 *
 * GATING CONDITIONS THAT SHAPE THIS SPEC's DEFAULT STATE:
 *  - Comments remain supported by `page`/`attachment` in this environment
 *    (`dwpb_post_types_with_feature( 'comments' )` is truthy), so the
 *    Comments menu and the Discussion settings link both stay (tests 3, 6).
 *  - `dwpb_remove_options_writing` defaults to `false`, so the Writing
 *    settings link stays (test 5).
 *  - `category` and `post_tag` are, by default, used only by the disabled
 *    `post` post type (pages carry neither), so `dwpb_post_types_with_tax()`
 *    is false for both and their menu links are gone (test 2).
 *
 * NAVIGATION TARGET: every test visits `edit.php?post_type=page` — the Pages
 * list table — because it is provably NOT one of the admin screens
 * `redirect_admin_pages()` redirects (see `admin-redirects.spec.ts`, test
 * "the pages list is not redirected"). A screen that could itself be
 * mid-redirect would make every menu assertion here meaningless.
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override — the project default
 * is already the administrator (see `playwright.config.ts`), and the admin
 * menu is not rendered at all for a signed-out visitor.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { MENU_POSTS, MENU_PAGES, MENU_COMMENTS, MENU_TOOLS, menuLink } from '../../config/admin';

/**
 * The "Available Tools" submenu link specifically, scoped to `.wp-submenu`.
 *
 * Not just `#adminmenu a[href="tools.php"]` (what the removed submenu item's
 * own href is): the top-level Tools menu-top anchor ALSO links to
 * `tools.php` — WP core's convention is that a top-level menu's first
 * submenu item duplicates the parent's own slug — so an unscoped selector
 * would still find one match (the top-level link) even after
 * `remove_submenu_page( 'tools.php', 'tools.php' )` correctly removed only
 * the submenu entry, producing a false failure on a healthy site. Scoping to
 * `.wp-submenu` excludes that top-level anchor, which core renders as a
 * direct child of `#menu-tools`, not inside its `.wp-submenu` list.
 */
const TOOLS_AVAILABLE_TOOLS_LINK = '#menu-tools .wp-submenu a[href="tools.php"]';

test.describe( 'admin: adminmenu (default state)', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );
	} );

	test( 'the Posts menu is removed', async ( { page } ) => {
		await expect( page.locator( MENU_POSTS ) ).toHaveCount( 0 );

		// Control: proves the admin menu rendered at all, rather than the
		// zero count above being trivially true against a blank page.
		await expect( page.locator( MENU_PAGES ) ).toBeVisible();
	} );

	test( 'no Categories or Tags submenu links remain', async ( { page } ) => {
		// menuLink() matches on href$= (suffix), which is exactly as targeted
		// as an href*= substring match for these two full, query-string-bearing
		// hrefs — see config/admin.ts.
		await expect( menuLink( page, 'edit-tags.php?taxonomy=category' ) ).toHaveCount( 0 );
		await expect( menuLink( page, 'edit-tags.php?taxonomy=post_tag' ) ).toHaveCount( 0 );

		// Control: proves the menu itself rendered, so the two zero-count
		// assertions above aren't vacuously true against a menu that never
		// loaded.
		await expect( page.locator( '#adminmenu' ) ).toBeVisible();
	} );

	test( 'the Comments menu is present by default', async ( { page } ) => {
		// Gated on dwpb_post_types_with_feature( 'comments' ): pages and
		// attachments support comments by default in this environment, so
		// remove_menu_pages() never adds 'edit-comments.php' to the removal
		// list — see the file docblock.
		await expect( page.locator( MENU_COMMENTS ) ).toBeVisible();
	} );

	test( 'Available Tools is removed from the Tools menu', async ( { page } ) => {
		await expect( page.locator( TOOLS_AVAILABLE_TOOLS_LINK ) ).toHaveCount( 0 );

		// Control: the Tools top-level menu itself is untouched —
		// remove_menu_pages() only ever removes the 'tools.php' SUBmenu entry,
		// never calls remove_menu_page( 'tools.php' ).
		await expect( page.locator( MENU_TOOLS ) ).toBeVisible();
	} );

	test( 'Settings has a Writing link by default', async ( { page } ) => {
		// dwpb_remove_options_writing defaults to false (see
		// Disable_Blog_Admin::remove_writing_options()'s docblock: "defaults to
		// false because other plugins often extend this page"), so the
		// options-writing.php submenu link is never removed out of the box.
		await expect( menuLink( page, 'options-writing.php' ) ).toBeVisible();
	} );

	test( 'Settings has a Discussion link by default', async ( { page } ) => {
		// Same gate as the top-level Comments menu (test 3):
		// dwpb_post_types_with_feature( 'comments' ) is truthy by default, so
		// remove_menu_pages() never adds options-discussion.php to the
		// options-general.php submenu removal list.
		await expect( menuLink( page, 'options-discussion.php' ) ).toBeVisible();
	} );
} );
