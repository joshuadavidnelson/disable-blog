/**
 * Front-end comment status on posts vs. pages, default plugin state.
 * `filter_comment_status()` forces `comments_open`/`pings_open` to `false`
 * for 'post' only, and is registered whenever any non-post public type (page,
 * attachment by default) declares comments support — true out of the box.
 *
 * Only the comment-status data layer (comments_open()/pings_open() via
 * postState()) is asserted, not DOM rendering: the active theme (a block
 * theme) queries comments via WP_Comment_Query directly and never applies
 * the comments_array filter, and its page template renders no #commentform,
 * so rendering assertions would be theme artifacts, not plugin behaviour.
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
	test.use( { storageState: { cookies: [], origins: [] } } );

	let seededPost: SeededPost;
	let seededPage: SeededPost;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		// Explicitly seeded 'open' so an observed 'closed' proves the plugin
		// forced it, not a coincidence of the default already being closed.
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
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'comments and pings are closed on posts', async ( { requestUtils } ) => {
		const postCommentState = await postState( requestUtils, seededPost.id );

		expect( postCommentState.commentsOpen ).toBe( false );
		expect( postCommentState.pingsOpen ).toBe( false );
	} );

	test( 'comments and pings stay open on pages', async ( { requestUtils } ) => {
		const pageCommentState = await postState( requestUtils, seededPage.id );

		expect( pageCommentState.commentsOpen ).toBe( true );
		expect( pageCommentState.pingsOpen ).toBe( true );
	} );
} );
