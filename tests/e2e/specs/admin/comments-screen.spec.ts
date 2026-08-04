/**
 * The admin comments list table (`edit-comments.php`), default plugin state.
 *
 * COVERAGE:
 *  - `Disable_Blog_Admin::comment_filter()`, hooked on `pre_get_comments`,
 *    scopes the query's `post_type` to `dwpb_post_types_with_feature(
 *    'comments' )` -- 'page'/'attachment' by default -- structurally
 *    excluding every comment on a 'post'.
 *  - `Disable_Blog_Admin::filter_admin_table_comment_count()`, hooked on
 *    `views_edit-comments`, rewrites each view's count using the same
 *    post-type-scoped SQL rather than trusting `wp_count_comments()`'s cache.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { seedPage, seedPost, seedComment, deletePosts, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';

test.describe( 'admin: comments list table (default state)', () => {
	test.describe( 'filtering by post type', () => {
		let seededPage: SeededPost;
		let seededPost: SeededPost;
		let pageCommentContent: string;
		let postCommentContent: string;

		const seededIds: number[] = [];

		test.beforeAll( async ( { requestUtils } ) => {
			seededPage = await seedPage( requestUtils, {
				title: uniqueTitle( 'comments screen page' ),
			} );
			seededIds.push( seededPage.id );

			seededPost = await seedPost( requestUtils, {
				title: uniqueTitle( 'comments screen post' ),
			} );
			seededIds.push( seededPost.id );

			pageCommentContent = uniqueTitle( 'page comment body' );
			postCommentContent = uniqueTitle( 'post comment body' );

			await seedComment( requestUtils, {
				postId: seededPage.id,
				content: pageCommentContent,
				approved: true,
			} );

			await seedComment( requestUtils, {
				postId: seededPost.id,
				content: postCommentContent,
				approved: true,
			} );
		} );

		test.afterAll( async ( { requestUtils } ) => {
			// Force-deleting the post/page also deletes its comments.
			await deletePosts( requestUtils, seededIds );
		} );

		test( 'post comments are filtered out of the comments list', async ( { admin, page } ) => {
			await admin.visitAdminPage( 'edit-comments.php' );

			// Scoped to #the-comment-list rows: core also emits a hidden
			// <textarea class="comment"> per row (inline-edit's data carrier)
			// duplicating the body, which an unscoped getByText() would also match.

			// Control: the page's comment is a supported post type, so it must
			// still be listed.
			await expect(
				page.locator( '#the-comment-list tr', { hasText: pageCommentContent } )
			).toHaveCount( 1 );

			await expect(
				page.locator( '#the-comment-list tr', { hasText: postCommentContent } )
			).toHaveCount( 0 );
		} );
	} );

	test.describe( 'empty state (only unsupported-type comments exist)', () => {
		let seededPage: SeededPost;
		let seededPost: SeededPost;
		let pageCommentId: number;

		test.beforeEach( async ( { requestUtils } ) => {
			seededPage = await seedPage( requestUtils, {
				title: uniqueTitle( 'comments screen empty-state page' ),
			} );
			seededPost = await seedPost( requestUtils, {
				title: uniqueTitle( 'comments screen empty-state post' ),
			} );

			const pageComment = await seedComment( requestUtils, {
				postId: seededPage.id,
				approved: true,
			} );
			pageCommentId = pageComment.id;

			await seedComment( requestUtils, { postId: seededPost.id, approved: true } );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			// Force-deleting the post/page also deletes its comments.
			await deletePosts( requestUtils, [ seededPage.id, seededPost.id ] );
		} );

		test( 'the list reports no comments when only post comments exist', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			// Force-delete the page's comment, leaving only the 'post' comment,
			// which comment_filter() excludes from every view's query entirely.
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/comments/${ pageCommentId }?force=true`,
			} );

			await admin.visitAdminPage( 'edit-comments.php' );

			// Core's own empty-state copy. Scoped to #the-comment-list: core also
			// renders a hidden #the-extra-comment-list clone repeating this same
			// text, which an unscoped match would also hit.
			await expect(
				page.locator( '#the-comment-list' ).getByText( 'No comments found.' )
			).toBeVisible();
		} );
	} );

	test.describe( 'view counts', () => {
		const seededIds: number[] = [];

		test.afterEach( async ( { requestUtils } ) => {
			await deletePosts( requestUtils, seededIds.splice( 0 ) );
		} );

		test( 'the view counts exclude post comments', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			// A before/after delta, not an absolute count: the "All" count is
			// site-wide, so a fresh pair of comments (one supported type, one
			// 'post') proves the post comment contributes zero to the delta.
			await admin.visitAdminPage( 'edit-comments.php' );

			const allCount = page.locator( '.subsubsub .all-count' );
			const before = Number( await allCount.innerText() );

			const extraPage = await seedPage( requestUtils, {
				title: uniqueTitle( 'comment count control page' ),
			} );
			seededIds.push( extraPage.id );

			const extraPost = await seedPost( requestUtils, {
				title: uniqueTitle( 'comment count control post' ),
			} );
			seededIds.push( extraPost.id );

			await seedComment( requestUtils, { postId: extraPage.id, approved: true } );
			await seedComment( requestUtils, { postId: extraPost.id, approved: true } );

			await admin.visitAdminPage( 'edit-comments.php' );
			const after = Number( await allCount.innerText() );

			expect( after ).toBe( before + 1 );
		} );
	} );
} );
