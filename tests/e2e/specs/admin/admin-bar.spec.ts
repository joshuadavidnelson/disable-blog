/**
 * The wp-admin toolbar (`#wpadminbar`), default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::remove_admin_bar_links()`, hooked on
 * `wp_before_admin_bar_render` unconditionally (both front and admin — see
 * `frontend/search-and-head.spec.ts`'s docblock for the front-end half of
 * this same method), always calls `$wp_admin_bar->remove_node( 'new-post' )`
 * and, gated on `! dwpb_post_types_with_feature( 'comments' )`, would also
 * `remove_menu( 'comments' )`. Comments remain supported by `page`/
 * `attachment` in this environment's default state, so that second removal
 * never fires — tests 3-4 below prove the comments bubble is present, not
 * absent.
 *
 * The comments bubble's actual NUMBER comes from a separate hook:
 * `Disable_Blog_Admin::filter_wp_count_comments()`, on the `wp_count_comments`
 * filter. Core's `wp_admin_bar_comments_menu()` (wp-includes/admin-bar.php)
 * reads `wp_count_comments()->moderated` — the count of comments AWAITING
 * MODERATION, not the total/approved count — so `filter_wp_count_comments()`
 * only changes what that number counts if there is at least one pending
 * comment: `get_comment_counts()` runs a SQL query scoped to
 * `post_type IN ( <post types supporting the comments feature> )`, i.e.
 * `page`/`attachment` today, which structurally excludes any `post` comment
 * (pending or not) simply by never selecting it. Tests 3-4 seed *pending*
 * (unapproved) comments for exactly this reason — an *approved* comment,
 * post or page, never touches `->moderated` at all and would move the
 * bubble by zero regardless of which post type it belongs to, proving
 * nothing about the post-type filtering this method exists for.
 *
 * CACHING NOTE: `filter_wp_count_comments()` caches its result under the
 * `comments-0` key (`wp_cache_get()`/`wp_cache_set()`, group `counts`). This
 * environment has no persistent object-cache drop-in configured (see
 * `.wp-env.json`), so WordPress falls back to its non-persistent,
 * per-request `WP_Object_Cache` — the cache is empty again at the start of
 * every new HTTP request, meaning every fresh `admin.visitAdminPage()` call
 * below recomputes the count live rather than risking a stale read from an
 * earlier test in this same file.
 *
 * DROPDOWN CHILDREN ARE HIDDEN UNTIL HOVER: `new-post`/`new-page` are
 * submenu items inside the "+ New" dropdown, which core keeps CSS-hidden
 * until the parent is hovered/focused. `toBeVisible()` on a hidden-until-hover
 * node is a false failure waiting to happen — assert DOM presence
 * (`toHaveCount()`) instead. See `frontend/search-and-head.spec.ts` for the
 * front-end version of this same admin-bar assertion, and the one prior
 * false failure this house rule already caught.
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
import { ADMIN_BAR_NEW_POST, ADMIN_BAR_NEW_PAGE } from '../../config/admin';
import { storageStatePath } from '../../config/roles';

/**
 * Parent "+ New" dropdown node (`#wp-admin-bar-new-content`).
 *
 * Not centralized in `config/admin.ts` — only needed here as a control
 * proving the dropdown itself rendered, same reasoning as
 * `frontend/search-and-head.spec.ts`'s identical local constant.
 */
const ADMIN_BAR_NEW_CONTENT = '#wp-admin-bar-new-content';

/**
 * The comments bubble's visible count label.
 *
 * Core's `wp_admin_bar_comments_menu()` builds
 * `<span class="ab-label awaiting-mod pending-count count-{n}">{n}</span>` —
 * a dynamic `count-{n}` class, never a bare `count` class. An earlier
 * `.count` selector matched zero elements regardless of the real value, which
 * would silently pass a `toHaveCount( 0 )`-style assertion for the wrong
 * reason. `config/admin.ts` now exports `ADMIN_BAR_COMMENTS_LABEL` with the
 * correct `.ab-label` selector; this local copy is kept only so the reasoning
 * above stays next to the assertions that depend on it.
 */
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

		// Control: proves remove_node( 'new-post' ) targeted that node
		// specifically, rather than the "+ New" dropdown failing to render.
		await expect( page.locator( ADMIN_BAR_NEW_PAGE ) ).toHaveCount( 1 );
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toBeVisible();
	} );
} );

test.describe( 'admin: admin bar — New Post (editor)', () => {
	test.use( { storageState: storageStatePath( 'editor' ) } );

	test( 'the New Post link is removed for editors', async ( { admin, page } ) => {
		// remove_admin_bar_links() runs unconditionally on
		// wp_before_admin_bar_render regardless of the current user's role, so
		// this repeats the administrator assertion above under the editor's
		// storage state to prove that.
		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( ADMIN_BAR_NEW_POST ) ).toHaveCount( 0 );

		// Control: editors can also publish pages, so "+ New > Page" staying
		// present proves the dropdown rendered for this role too.
		await expect( page.locator( ADMIN_BAR_NEW_PAGE ) ).toHaveCount( 1 );
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toBeVisible();
	} );
} );

test.describe( 'admin: admin bar — comments bubble (administrator)', () => {
	// Each test seeds and tears down its own content rather than sharing
	// beforeAll/afterAll state, so a before/after delta read never has to
	// worry about the other test's seeded comment still being live.

	test( 'the comments bubble excludes pending comments on posts', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( '#wp-admin-bar-comments' ) ).toBeVisible();

		const before = await readCommentsBubbleCount( page );

		const seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'admin bar comments post' ),
		} );

		try {
			// Pending (comment_approved = 0), the bucket wp_admin_bar_comments_menu()
			// actually reads via wp_count_comments()->moderated — see the file
			// docblock on why an approved comment would prove nothing here.
			await seedComment( requestUtils, { postId: seededPost.id, approved: false } );

			await admin.visitAdminPage( 'index.php' );
			const after = await readCommentsBubbleCount( page );

			// get_comment_counts()'s SQL is scoped to
			// post_type IN ( <comment-supporting types> ), which excludes 'post'
			// entirely — this pending post comment must not move the bubble.
			expect( after ).toBe( before );
		} finally {
			await deletePosts( requestUtils, [ seededPost.id ] );
		}
	} );

	test( 'page comments are counted in the bubble', async ( { admin, page, requestUtils } ) => {
		// Control for the test above: proves the bubble does count pending
		// comments on a post type that supports the comments feature, so the
		// zero-delta result there isn't just the bubble being broken/frozen.
		await admin.visitAdminPage( 'index.php' );

		const before = await readCommentsBubbleCount( page );

		const seededPage = await seedPage( requestUtils, {
			title: uniqueTitle( 'admin bar comments page' ),
		} );

		try {
			await seedComment( requestUtils, { postId: seededPage.id, approved: false } );

			await admin.visitAdminPage( 'index.php' );
			const after = await readCommentsBubbleCount( page );

			expect( after ).toBe( before + 1 );
		} finally {
			// wp_delete_post( $id, true ) (force delete, used by deletePosts())
			// also deletes comments attached to the post/page being removed, so
			// no separate comment cleanup call is needed here.
			await deletePosts( requestUtils, [ seededPage.id ] );
		}
	} );
} );
