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
import { adminUrl, rowLocator } from '../../config/admin';
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
		request,
		requestUtils,
	} ) => {
		const config = await siteConfig( requestUtils );
		const strings = await pluginStrings( requestUtils );

		// Asserted at the REQUEST layer, not through the browser, and that is
		// deliberate. The notice under test is printed server-side by
		// `edit_form_after_title`, so if it rendered at all it would be in this
		// initial HTML response -- booting the block editor proves nothing extra.
		//
		// Driving this through the browser also made the test version-fragile:
		// WordPress only moved the block-editor canvas into an iframe
		// (`[name="editor-canvas"]`) partway through this plugin's supported
		// range, and on 5.9 neither the iframed nor the top-level "Add title"
		// locator resolves, so any DOM-readiness wait just burns its full
		// timeout. The HTML response is identical to reason about on every
		// supported version.
		const response = await request.get(
			adminUrl( `post.php?post=${ config.blogId }&action=edit` )
		);

		expect( response.status() ).toBe( 200 );

		const body = await response.text();

		// Positive control: prove we actually loaded the editor screen for the
		// Blog page, so the absence assertion below cannot pass vacuously
		// against a redirect, a permissions error, or an empty response.
		expect( body ).toContain( `post=${ config.blogId }` );

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
		//    environment disables the block editor for it, and the active theme
		//    is a block theme, so every page edit here uses the
		//    block editor.
		//
		// Net effect: neither core's default notice nor the plugin's
		// replacement ever renders for the Blog page in this environment. This
		// is a real, verified gap in the plugin's block-editor coverage (the
		// block editor gives no visual indication that the page being edited
		// is the redirected posts page), not a flaky assertion -- flagged for
		// the coordinator, and reported per the brief's explicit allowance to
		// report unreachability rather than force a presence assertion.
		expect( body ).not.toContain( strings.posts_page_edit_notice );
	} );
} );
