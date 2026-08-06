/**
 * Guaranteed-cleanup wrapper around the seed helpers in `config/seed.ts`:
 * each method records the id it creates, so a spec can seed content without
 * keeping its own id array and remove it all via `cleanup()` in one call.
 *
 * A plain object rather than a Playwright fixture, so one instance can span
 * both `beforeAll`+`afterAll` and `beforeEach`+`afterEach`.
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
	 * Seed a comment via {@link seedCommentRaw}. Not tracked separately --
	 * deleting its post/page via {@link ContentTracker.cleanup} takes it too.
	 */
	seedComment: ( requestUtils: RequestUtils, args: SeedCommentArgs ) => Promise< SeededComment >;
	/**
	 * Remove everything seeded so far. Never throws -- safe to call
	 * unconditionally from an afterEach/afterAll even if the matching seed
	 * step failed partway through -- and drains its id lists so the tracker
	 * stays reusable.
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
