/**
 * Guaranteed-cleanup wrapper around the seed helpers in `config/seed.ts`.
 *
 * Each method wraps its `seed.ts` counterpart and records the id, so a spec
 * seeds content without keeping an array of its own. Comments are not
 * tracked: force-deleting their post or page takes them along.
 *
 * A plain object rather than a Playwright fixture, so one instance serves
 * `beforeAll`+`afterAll` and `beforeEach`+`afterEach` alike. `cleanup()`
 * drains the ids it fires against, leaving the tracker reusable, and never
 * throws — safe to call from a hook even when seeding failed.
 *
 * @example
 * const content = createContentTracker();
 * let seededPost: SeededPost;
 *
 * test.beforeAll( async ( { requestUtils } ) => {
 *   seededPost = await content.seedPost( requestUtils, { title: uniqueTitle( 'x' ) } );
 * } );
 *
 * test.afterAll( async ( { requestUtils } ) => {
 *   await content.cleanup( requestUtils );
 * } );
 *
 * @see tests/e2e/config/seed.ts
 */

/**
 * External dependencies
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	seedPost as seedPostRaw,
	seedPage as seedPageRaw,
	seedTerm as seedTermRaw,
	seedComment as seedCommentRaw,
	deletePosts,
	deleteTerms,
} from './seed';
import type {
	SeedPostArgs,
	SeedPageArgs,
	SeedTermArgs,
	SeedCommentArgs,
	SeededPost,
} from './seed';

type SeededTerm = { termId: number; slug: string; link: string };
type SeededComment = { id: number; approved: boolean };

export interface ContentTracker {
	/** Seed a post via {@link seedPostRaw} and track its id for cleanup. */
	seedPost: ( requestUtils: RequestUtils, args: SeedPostArgs ) => Promise< SeededPost >;
	/** Seed a page via {@link seedPageRaw} and track its id for cleanup. */
	seedPage: ( requestUtils: RequestUtils, args: SeedPageArgs ) => Promise< SeededPost >;
	/** Seed a term via {@link seedTermRaw} and track its id for cleanup. */
	seedTerm: ( requestUtils: RequestUtils, args: SeedTermArgs ) => Promise< SeededTerm >;
	/**
	 * Seed a comment via {@link seedCommentRaw}. Not separately tracked --
	 * force-deleting its post/page (via {@link ContentTracker.cleanup}) takes
	 * the comment with it -- wrapped only so a spec can seed everything
	 * through one object.
	 */
	seedComment: ( requestUtils: RequestUtils, args: SeedCommentArgs ) => Promise< SeededComment >;
	/**
	 * Remove everything this tracker has seeded so far, via `deletePosts()`/
	 * `deleteTerms()`. Never throws. Drains its id lists, so the tracker is
	 * safe to reuse for further seed/cleanup rounds.
	 */
	cleanup: ( requestUtils: RequestUtils ) => Promise< void >;
}

/**
 * Create a fresh, empty {@link ContentTracker}.
 */
export function createContentTracker(): ContentTracker {
	const postIds: number[] = [];
	const termIds: number[] = [];

	return {
		async seedPost( requestUtils, args ) {
			const post = await seedPostRaw( requestUtils, args );
			postIds.push( post.id );

			return post;
		},

		async seedPage( requestUtils, args ) {
			const page = await seedPageRaw( requestUtils, args );
			postIds.push( page.id );

			return page;
		},

		async seedTerm( requestUtils, args ) {
			const term = await seedTermRaw( requestUtils, args );
			termIds.push( term.termId );

			return term;
		},

		seedComment: seedCommentRaw,

		async cleanup( requestUtils ) {
			await Promise.all( [
				deletePosts( requestUtils, postIds.splice( 0 ) ),
				deleteTerms( requestUtils, termIds.splice( 0 ) ),
			] );
		},
	};
}
