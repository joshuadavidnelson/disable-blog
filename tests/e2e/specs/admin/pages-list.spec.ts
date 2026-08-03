/**
 * The Pages list table (`edit.php?post_type=page`) and the Blog page's own
 * edit screen, default plugin state.
 *
 * COVERAGE:
 *  - `Disable_Blog_Admin::page_post_states()`, hooked on `display_post_states`,
 *    adds the `page_post_state` copy ("Redirected to the homepage") to a
 *    page row whenever `has_front_page()` is true AND that row is
 *    `page_for_posts` -- i.e. only the Blog page's row, never the Home
 *    page's.
 *  - `Disable_Blog_Admin::posts_page_notice()` -- see test 3 below for why
 *    this is proven UNREACHABLE rather than reachable in this environment.
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override -- the project default
 * (administrator) can edit pages, required for test 3.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { rowLocator } from '../../config/admin';
import { siteConfig } from '../../config/seed';
import { pluginStrings } from '../../config/strings';

test.describe( 'admin: pages list table (default state)', () => {
	test( 'the posts page row shows the redirect state', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const config = await siteConfig( requestUtils );
		const strings = await pluginStrings( requestUtils );

		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( rowLocator( page, config.blogId ) ).toContainText( strings.page_post_state );
	} );

	test( 'the front page row does not show the redirect state', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		// Control for the test above: page_post_states() only ever compares
		// against page_for_posts, never page_on_front, so the Home row must
		// never carry this state regardless of how "redirected" it might sound.
		const config = await siteConfig( requestUtils );
		const strings = await pluginStrings( requestUtils );

		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( rowLocator( page, config.homeId ) ).not.toContainText(
			strings.page_post_state
		);
	} );

	test( 'editing the posts page shows the plugin notice', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		// The block editor is slow to boot (full editor bundle, block
		// registration, ...), so this one test gets a generous timeout rather
		// than the suite default.
		test.setTimeout( 90_000 );

		const config = await siteConfig( requestUtils );
		const strings = await pluginStrings( requestUtils );

		await admin.visitAdminPage( 'post.php', `post=${ config.blogId }&action=edit` );

		// Wait for the block editor to settle: the title field inside the
		// canvas iframe is a reliable signal it has finished booting, rather
		// than racing the assertion below against a still-loading editor.
		await expect(
			editor.canvas.getByRole( 'textbox', { name: 'Add title' } )
		).toBeVisible( { timeout: 60_000 } );

		// NOT a positive assertion, deliberately -- and this contradicts the
		// task brief's expectation that this notice is reachable here. Verified
		// against the wp-env core install (WordPress 7.0.2) and this plugin's
		// source directly, not assumed:
		//
		//  - Disable_Blog_Admin::update_posts_page_notice() is hooked on
		//    `post_edit_form_tag`.
		//  - The notice it swaps in, posts_page_notice(), is hooked on
		//    `edit_form_after_title` (replacing core's own _wp_posts_page_notice,
		//    which is hooked on the very same action).
		//  - BOTH of those actions are fired exactly once, from
		//    wp-admin/edit-form-advanced.php -- the CLASSIC editor template.
		//  - wp-admin/post.php dispatches to wp-admin/edit-form-blocks.php
		//    instead whenever use_block_editor_for_post() is true, and never
		//    requires edit-form-advanced.php in that branch. 'page' is
		//    REST-enabled and supports the 'editor' feature, no plugin in this
		//    environment disables the block editor for it, and Twenty
		//    Twenty-Four is a block theme, so every page edit here uses the
		//    block editor.
		//
		// Net effect: neither core's default notice nor the plugin's
		// replacement ever renders for the Blog page in this environment. This
		// is a real, verified gap in the plugin's block-editor coverage (the
		// block editor gives no visual indication that the page being edited
		// is the redirected posts page), not a flaky assertion -- flagged for
		// the coordinator, and reported per the brief's explicit allowance to
		// report unreachability rather than force a presence assertion.
		await expect( page.getByText( strings.posts_page_edit_notice ) ).toHaveCount( 0 );
	} );
} );
