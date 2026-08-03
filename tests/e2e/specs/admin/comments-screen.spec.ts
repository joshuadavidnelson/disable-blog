/**
 * The admin comments list table (`edit-comments.php`), default plugin state.
 *
 * COVERAGE:
 *  - `Disable_Blog_Admin::comment_filter()`, hooked on `pre_get_comments` and
 *    scoped to the `edit-comments` screen, sets the query's `post_type` to
 *    `dwpb_post_types_with_feature( 'comments' )` -- 'page'/'attachment' in
 *    this environment's default state, structurally excluding every comment
 *    on a 'post'.
 *  - `Disable_Blog_Admin::filter_admin_table_comment_count()`, hooked on
 *    `views_edit-comments`, rewrites each view's count (`All`, `Pending`,
 *    ...) to `Disable_Blog_Admin::get_comment_counts()`'s own count, a direct
 *    SQL query scoped the same way -- `post_type IN (<comment-supporting
 *    types>)` AND `post_status = 'publish'` -- rather than trusting
 *    `wp_count_comments()`'s cache.
 *
 * GATING CONDITION: comments remain supported by `page`/`attachment` in this
 * environment's default state (see the file-level brief), so this whole
 * screen and its filtering are reachable at all -- none of this fires the
 * `redirect_admin_edit_comments()` early-exit that would apply if no post
 * type supported comments (see `admin-redirects.spec.ts`, test 6, for that
 * screen's own default-state coverage).
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override -- the project default
 * (administrator) has `moderate_comments`, required for this screen.
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
	let seededPage: SeededPost;
	let seededPost: SeededPost;
	let pageCommentId: number;
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

		const pageComment = await seedComment( requestUtils, {
			postId: seededPage.id,
			content: pageCommentContent,
			approved: true,
		} );
		pageCommentId = pageComment.id;

		await seedComment( requestUtils, {
			postId: seededPost.id,
			content: postCommentContent,
			approved: true,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// wp_delete_post( $id, true ) (force delete, used by deletePosts())
		// also deletes comments attached to the post/page being removed, so no
		// separate comment cleanup call is needed here -- see admin-bar.spec.ts
		// for the same pattern.
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'post comments are filtered out of the comments list', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit-comments.php' );

		// Scoped to real rows in #the-comment-list rather than page-wide text
		// matches: core emits a hidden <textarea class="comment"> per row
		// (inline-edit's data carrier) that duplicates the comment body, so an
		// unscoped page.getByText() resolves to 2 elements and fails Playwright's
		// strict mode. Don't revert to page-scoped getByText() here.

		// Control: the page's comment is a supported post type, so it must
		// still be listed.
		await expect(
			page.locator( '#the-comment-list tr', { hasText: pageCommentContent } )
		).toHaveCount( 1 );

		// The post's comment is structurally excluded by comment_filter()'s
		// post_type query-var rewrite -- not merely hidden.
		await expect(
			page.locator( '#the-comment-list tr', { hasText: postCommentContent } )
		).toHaveCount( 0 );
	} );

	test( 'the list reports no comments when only post comments exist', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		// Force-delete (not trash) the page's comment via core REST -- pages are
		// untouched by the plugin's show_in_rest stripping, so /wp/v2/comments
		// works for it the same as any stock WordPress install (see seed.ts's
		// docblock on why 'post' comments can't go through this same route).
		// What remains after this is exactly one comment, on a 'post', which
		// comment_filter() excludes from every view's query entirely.
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/comments/${ pageCommentId }?force=true`,
		} );

		await admin.visitAdminPage( 'edit-comments.php' );

		// Exact core copy (WP_Comments_List_Table::no_items()), not plugin
		// copy -- the plugin doesn't own this string, core's empty-state
		// fallback does. Scoped strictly to #the-comment-list -- core also
		// renders a hidden #the-extra-comment-list clone table (another
		// inline-edit artifact) that repeats this exact empty-state text, so an
		// unscoped page-root match resolves to 2 elements under Playwright's
		// strict mode. Don't revert to an unscoped page.getByText() here.
		await expect(
			page.locator( '#the-comment-list' ).getByText( 'No comments found.' )
		).toBeVisible();
	} );

	test( 'the view counts exclude post comments', async ( { admin, page, requestUtils } ) => {
		// A before/after delta rather than asserting an absolute count: the
		// "All" count reflects every approved 'page'/'attachment' comment
		// site-wide (get_comment_counts()'s SQL has no per-spec scope),
		// so a fresh pair of seeded comments -- one on a supported type, one on
		// a 'post' -- proves the post comment contributes zero to the delta
		// without needing to know the absolute baseline.
		//
		// CACHE NOTE: get_comment_counts() itself runs a fresh, uncached SQL
		// query every call. The one cache in play is core's own
		// wp_count_comments( 0 ) cache (key 'comments-0', group 'counts'),
		// which Disable_Blog_Admin::filter_wp_count_comments() intercepts and
		// re-populates from get_comment_counts() -- but that filter backs the
		// admin-bar bubble, not this screen's .subsubsub view links (those come
		// from filter_admin_table_comment_count(), a separate hook that never
		// reads that cache). Comment insertion also invalidates it via
		// wp_update_comment_count_now() regardless, so this test should not be
		// cache-sensitive in practice -- flagged for the coordinator to watch
		// in case a shared/persistent object cache in CI behaves differently
		// than this environment's per-request fallback cache.
		await admin.visitAdminPage( 'edit-comments.php' );

		const allCount = page.locator( '.subsubsub .all-count' );
		const before = Number( await allCount.innerText() );

		const extraPage = await seedPage( requestUtils, {
			title: uniqueTitle( 'comment count control page' ),
		} );
		const extraPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'comment count control post' ),
		} );
		const extraIds = [ extraPage.id, extraPost.id ];

		try {
			await seedComment( requestUtils, { postId: extraPage.id, approved: true } );
			await seedComment( requestUtils, { postId: extraPost.id, approved: true } );

			await admin.visitAdminPage( 'edit-comments.php' );
			const after = Number( await allCount.innerText() );

			expect( after ).toBe( before + 1 );
		} finally {
			await deletePosts( requestUtils, extraIds );
		}
	} );
} );
