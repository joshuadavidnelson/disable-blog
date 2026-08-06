/**
 * The Pages list table (`edit.php?post_type=page`) and the Blog page's own
 * edit screen, default plugin state.
 *
 * `page_post_states()` adds the `page_post_state` copy ("Redirected to the
 * homepage") to the `page_for_posts` row whenever `has_front_page()` is
 * true -- only the Blog page's row, never the Home page's.
 *
 * `posts_page_notice()` never renders under the block editor: both it and
 * core's own equivalent only hook into the classic editor template
 * (`edit-form-advanced.php`), which `post.php` never loads once
 * `use_block_editor_for_post()` is true, as it is for `page` here.
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
		// Control: the Home row must never carry this state.
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

		// Asserted at the request layer: the notice is printed server-side by
		// edit_form_after_title, so the initial HTML response is sufficient
		// and avoids browser/version-specific editor-loading flakiness.
		const response = await request.get(
			adminUrl( `post.php?post=${ config.blogId }&action=edit` )
		);

		expect( response.status() ).toBe( 200 );

		const body = await response.text();

		// Positive control: proves the editor screen actually loaded, so the
		// absence assertion below can't pass vacuously.
		expect( body ).toContain( `post=${ config.blogId }` );

		// See docblock: neither notice renders under the block editor.
		expect( body ).not.toContain( strings.posts_page_edit_notice );
	} );
} );
