/**
 * The admin comments list table (`edit-comments.php`), default plugin state.
 *
 * `comment_filter()` (on `pre_get_comments`) scopes the query's post_type to
 * `dwpb_post_types_with_feature( 'comments' )` -- 'page'/'attachment' by
 * default -- excluding every comment on a 'post'.
 * `filter_admin_table_comment_count()` rewrites each view's count with the
 * same post-type-scoped SQL rather than trusting `wp_count_comments()`'s
 * cache.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { seedComment, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { createContentTracker } from '../../config/content-tracker';

test.describe( 'admin: comments list table (default state)', () => {
	test.describe( 'filtering by post type', () => {
		let seededPage: SeededPost;
		let seededPost: SeededPost;
		let pageCommentContent: string;
		let postCommentContent: string;

		const content = createContentTracker();

		test.beforeAll( async ( { requestUtils } ) => {
			seededPage = await content.seedPage( requestUtils, {
				title: uniqueTitle( 'comments screen page' ),
			} );

			seededPost = await content.seedPost( requestUtils, {
				title: uniqueTitle( 'comments screen post' ),
			} );

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
			await content.cleanup( requestUtils );
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

		const content = createContentTracker();

		test.beforeEach( async ( { requestUtils } ) => {
			seededPage = await content.seedPage( requestUtils, {
				title: uniqueTitle( 'comments screen empty-state page' ),
			} );
			seededPost = await content.seedPost( requestUtils, {
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
			await content.cleanup( requestUtils );
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
		const content = createContentTracker();

		test.afterEach( async ( { requestUtils } ) => {
			await content.cleanup( requestUtils );
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

			const extraPage = await content.seedPage( requestUtils, {
				title: uniqueTitle( 'comment count control page' ),
			} );

			const extraPost = await content.seedPost( requestUtils, {
				title: uniqueTitle( 'comment count control post' ),
			} );

			await seedComment( requestUtils, { postId: extraPage.id, approved: true } );
			await seedComment( requestUtils, { postId: extraPost.id, approved: true } );

			await admin.visitAdminPage( 'edit-comments.php' );
			const after = Number( await allCount.innerText() );

			expect( after ).toBe( before + 1 );
		} );
	} );
} );
