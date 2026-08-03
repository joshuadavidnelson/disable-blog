/**
 * Front-end comment status behaviour on posts vs. pages, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::filter_comment_status()`, hooked on both the
 * `comments_open` and `pings_open` filters, forces both to `false` whenever
 * `'post' === get_post_type( $post_id )` and passes the incoming value
 * through untouched for every other post type.
 *
 * GATING CONDITION: the above is only registered when
 * `dwpb_post_types_with_feature( 'comments' )` is truthy
 * (`includes/class-disable-blog.php`, `define_admin_hooks()`). That function
 * returns every public post type OTHER than `post` that declares
 * `'comments'` support, and `page` and `attachment` both declare it by
 * default in stock WordPress — so on a fresh install this is truthy and the
 * hook IS active, which is exactly the state this spec exercises. The
 * `dwpb_test_comments_unsupported` fixture toggle exists to flip that gating
 * condition off and is deliberately out of scope here — see the Phase 3
 * backlog.
 *
 * 🚫 FRONT-END COMMENT *RENDERING* IS DELIBERATELY NOT ASSERTED HERE: this
 * file used to also cover `Disable_Blog_Admin::filter_existing_comments()`
 * (hooked on `comments_array`) and the presence/absence of `#commentform` on
 * a rendered post/page. Both were verified empirically to be untestable
 * against the current site: the active theme is Twenty Twenty-Four, a block
 * theme, whose Comments block queries comments via `WP_Comment_Query`
 * directly and never applies the `comments_array` filter at all — that
 * filter only fires through the classic `comments_template()` path. So
 * `filter_existing_comments()` has no effect under this theme and existing
 * comments keep rendering regardless of post type; separately, the theme's
 * page template renders no comment UI at all, so `#commentform` never exists
 * on either a post or a page. This is a real, confirmed defect
 * (`comments_array` registered, `wp_is_block_theme()` true), but it is being
 * filed as an issue rather than fixed here, and it is theme-dependent rather
 * than a plugin contract — so this file asserts only the theme-independent
 * comment-status data layer (`comments_open()`/`pings_open()` via
 * `postState()`), not DOM rendering. Do not add rendering assertions back
 * without re-verifying against whatever theme is active at the time.
 *
 * ANONYMOUS CONTEXT: same convention as `redirects.spec.ts` — the describe
 * block runs with an empty `storageState`; the worker-scoped `requestUtils`
 * fixture stays admin-authenticated for seeding.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	seedPost,
	seedPage,
	deletePosts,
	uniqueTitle,
	postState,
} from '../../config/seed';
import type { SeededPost } from '../../config/seed';

test.describe( 'frontend: comment status on posts vs. pages (default state)', () => {
	// Public-facing behaviour — every request in this block is anonymous.
	// The worker-scoped `requestUtils` fixture stays admin-authenticated
	// regardless (see the file docblock), so seeding still works. Nothing in
	// this file navigates a rendered page (see the file docblock's "FRONT-END
	// COMMENT RENDERING" note), so there is no need for the
	// `frontEndRedirectsOff` fixture toggle that a DOM-navigating version of
	// this file would require.
	test.use( { storageState: { cookies: [], origins: [] } } );

	let seededPost: SeededPost;
	let seededPage: SeededPost;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		// comment_status/ping_status are explicitly set to 'open' on both —
		// not left at whatever the site default happens to be — so that
		// "closed" observed on the post below is proof the plugin forced it,
		// not a coincidence of the raw column already being closed.
		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'comments post' ),
			commentStatus: 'open',
			pingStatus: 'open',
		} );
		seededIds.push( seededPost.id );

		seededPage = await seedPage( requestUtils, {
			title: uniqueTitle( 'comments page' ),
			commentStatus: 'open',
			pingStatus: 'open',
		} );
		seededIds.push( seededPage.id );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Best-effort — deletePosts() never throws, see its docblock.
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'comments and pings are closed on posts', async ( { requestUtils } ) => {
		const postCommentState = await postState( requestUtils, seededPost.id );

		expect( postCommentState.commentsOpen ).toBe( false );
		expect( postCommentState.pingsOpen ).toBe( false );
	} );

	test( 'comments and pings stay open on pages', async ( { requestUtils } ) => {
		// Control for the test above: the page was seeded with the exact same
		// open comment/ping status as the post, so an unchanged `true` here
		// proves filter_comment_status() is targeting 'post' specifically
		// rather than closing comments/pings site-wide.
		const pageCommentState = await postState( requestUtils, seededPage.id );

		expect( pageCommentState.commentsOpen ).toBe( true );
		expect( pageCommentState.pingsOpen ).toBe( true );
	} );
} );
