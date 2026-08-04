/**
 * The wp-admin toolbar (`#wpadminbar`), default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::remove_admin_bar_links()`, hooked on
 * `wp_before_admin_bar_render`, always removes the 'new-post' node and, when
 * comments are unsupported, would also remove the comments menu — not the
 * case here (see `admin-menu.spec.ts`).
 *
 * The comments bubble count comes from `filter_wp_count_comments()`, which
 * reads `wp_count_comments()->moderated` (pending, not approved/total
 * comments), scoped to comment-supporting post types — so tests below seed
 * *pending* comments on posts vs. pages to prove that scoping.
 *
 * Dropdown children (`new-post`/`new-page`) are CSS-hidden until the parent
 * is hovered, so presence is asserted with `toHaveCount()`, not
 * `toBeVisible()`.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import type { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	seedPost,
	seedPage,
	seedComment,
	deletePosts,
	uniqueTitle,
} from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { ADMIN_BAR_NEW_POST, ADMIN_BAR_NEW_PAGE } from '../../config/admin';
import { storageStatePath } from '../../config/roles';

// Parent "+ New" dropdown node; used as a control that the dropdown rendered.
const ADMIN_BAR_NEW_CONTENT = '#wp-admin-bar-new-content';

// Core renders the count as a dynamic `count-{n}` class, not a bare `count`
// class, so match on `.ab-label` text instead.
const ADMIN_BAR_COMMENTS_LABEL = '#wp-admin-bar-comments .ab-label';

/**
 * Read the comments bubble's current numeric label.
 *
 * @param page Page under test, already navigated to an admin screen.
 */
async function readCommentsBubbleCount( page: Page ): Promise< number > {
	const text = await page.locator( ADMIN_BAR_COMMENTS_LABEL ).textContent();

	return Number( text?.trim() );
}

test.describe( 'admin: admin bar — New Post (administrator)', () => {
	test( 'the New Post link is removed for administrators', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( ADMIN_BAR_NEW_POST ) ).toHaveCount( 0 );

		// Control: proves the "+ New" dropdown itself rendered.
		await expect( page.locator( ADMIN_BAR_NEW_PAGE ) ).toHaveCount( 1 );
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toBeVisible();
	} );
} );

test.describe( 'admin: admin bar — New Post (editor)', () => {
	test.use( { storageState: storageStatePath( 'editor' ) } );

	test( 'the New Post link is removed for editors', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( ADMIN_BAR_NEW_POST ) ).toHaveCount( 0 );

		// Control: editors can also publish pages, so the dropdown rendering
		// isn't itself role-gated.
		await expect( page.locator( ADMIN_BAR_NEW_PAGE ) ).toHaveCount( 1 );
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toBeVisible();
	} );
} );

test.describe( 'admin: admin bar — comments bubble (administrator)', () => {
	test.describe( 'pending comment on a post (unsupported type)', () => {
		let seededPost: SeededPost;
		let before: number;

		test.beforeEach( async ( { admin, page, requestUtils } ) => {
			await admin.visitAdminPage( 'index.php' );

			await expect( page.locator( '#wp-admin-bar-comments' ) ).toBeVisible();

			before = await readCommentsBubbleCount( page );

			seededPost = await seedPost( requestUtils, {
				title: uniqueTitle( 'admin bar comments post' ),
			} );
			await seedComment( requestUtils, { postId: seededPost.id, approved: false } );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await deletePosts( requestUtils, [ seededPost.id ] );
		} );

		test( 'the comments bubble excludes pending comments on posts', async ( {
			admin,
			page,
		} ) => {
			await admin.visitAdminPage( 'index.php' );
			const after = await readCommentsBubbleCount( page );

			expect( after ).toBe( before );
		} );
	} );

	test.describe( 'pending comment on a page (supported type)', () => {
		let seededPage: SeededPost;
		let before: number;

		test.beforeEach( async ( { admin, page, requestUtils } ) => {
			await admin.visitAdminPage( 'index.php' );

			before = await readCommentsBubbleCount( page );

			seededPage = await seedPage( requestUtils, {
				title: uniqueTitle( 'admin bar comments page' ),
			} );
			await seedComment( requestUtils, { postId: seededPage.id, approved: false } );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			// Force-deleting the post/page also deletes its comments.
			await deletePosts( requestUtils, [ seededPage.id ] );
		} );

		test( 'page comments are counted in the bubble', async ( { admin, page } ) => {
			// Control for the sibling describe above: proves the bubble isn't
			// simply frozen.
			await admin.visitAdminPage( 'index.php' );
			const after = await readCommentsBubbleCount( page );

			expect( after ).toBe( before + 1 );
		} );
	} );
} );
