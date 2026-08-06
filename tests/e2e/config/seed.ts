/**
 * Content seeding + inspection helpers shared by every spec.
 *
 * Posts go through the `dwpb-test/v1` mu-plugin fixture, not core REST: the
 * plugin sets `show_in_rest => false` on `post`, so `POST /wp/v2/posts`
 * 404s. Pages are untouched by the plugin, so `seedPage()` uses core REST
 * (`/wp/v2/pages`) normally.
 *
 * The PHP fixture's JSON is snake_case; every helper here converts it to
 * camelCase before returning.
 *
 * @see tests/e2e/fixtures/dwpb-test-api.php
 */

/**
 * External dependencies
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { TEST_API } from './roles';

/**
 * Reading-settings state established by `dwpb-test/v1/setup`.
 */
export interface SiteConfig {
	homeId: number;
	blogId: number;
	frontPageUrl: string;
	homeUrl: string;
	permalinkStructure: string;
}

/**
 * A post or page seeded by {@link seedPost} / {@link seedPage}.
 */
export interface SeededPost {
	id: number;
	permalink: string;
	title: string;
	postType: string;
	postStatus: string;
}

/**
 * Status-relevant state of a post/page, as reported by `/post-state/<id>`.
 */
export interface PostState {
	id: number;
	postStatus: string;
	postType: string;
	commentsOpen: boolean;
	pingsOpen: boolean;
	permalink: string;
	commentCount: number;
}

let titleCounter = 0;

/**
 * Build a title unique across specs, runs, workers and shards, to avoid slug
 * collisions and cross-spec locator matches.
 *
 * The counter only separates calls within one process, so the pid is mixed in
 * too — two shards can otherwise start inside the same millisecond and
 * produce identical titles.
 *
 * @param prefix Human-readable prefix, usually the spec name.
 */
export function uniqueTitle( prefix: string ): string {
	titleCounter += 1;

	return `${ prefix } ${ Date.now().toString( 36 ) }-${ process.pid.toString( 36 ) }-${ titleCounter }`;
}

let cachedSiteConfig: SiteConfig | null = null;

/**
 * Idempotently seed the Home/Blog pages and reading settings, via
 * `dwpb-test/v1/setup`. Always re-fetches — use {@link siteConfig} for a
 * memoized read.
 *
 * @param requestUtils Admin request utils.
 */
export async function setupSite( requestUtils: RequestUtils ): Promise< SiteConfig > {
	const response = await requestUtils.rest< {
		home_id: number;
		blog_id: number;
		front_page_url: string;
		home_url: string;
		permalink_structure: string;
	} >( {
		method: 'POST',
		path: `/${ TEST_API }/setup`,
	} );

	cachedSiteConfig = {
		homeId: response.home_id,
		blogId: response.blog_id,
		frontPageUrl: response.front_page_url,
		homeUrl: response.home_url,
		permalinkStructure: response.permalink_structure,
	};

	return cachedSiteConfig;
}

/**
 * Memoized wrapper around {@link setupSite}. Values are static for a run
 * unless a spec changes them directly, in which case it must restore them
 * via {@link setupSite}.
 *
 * @param requestUtils Admin request utils.
 */
export async function siteConfig( requestUtils: RequestUtils ): Promise< SiteConfig > {
	if ( ! cachedSiteConfig ) {
		return setupSite( requestUtils );
	}

	return cachedSiteConfig;
}

/**
 * Reading-settings values the `dwpb-test/v1/reading-settings` route reads
 * and writes.
 */
export interface ReadingSettings {
	showOnFront: string;
	pageOnFront: number;
	pageForPosts: number;
}

/**
 * The subset of {@link ReadingSettings} a spec wants to change. Omitted keys
 * are left untouched by the route.
 */
export interface SetReadingSettingsArgs {
	showOnFront?: string;
	pageOnFront?: number;
	pageForPosts?: number;
}

/**
 * Set `show_on_front` / `page_on_front` / `page_for_posts` directly via
 * `dwpb-test/v1/reading-settings`, bypassing core REST.
 *
 * WordPress 5.9's `/wp/v2/settings` doesn't expose these keys at all, so a
 * `PUT` against them silently no-ops there instead of erroring. Returns the
 * values it overwrote so a caller can restore exactly what it changed.
 *
 * @param requestUtils Admin request utils.
 * @param args         Settings to change. Omitted keys are left untouched.
 */
export async function setReadingSettings(
	requestUtils: RequestUtils,
	args: SetReadingSettingsArgs
): Promise< { previous: ReadingSettings; current: ReadingSettings } > {
	const response = await requestUtils.rest< {
		previous: { show_on_front: string; page_on_front: number; page_for_posts: number };
		current: { show_on_front: string; page_on_front: number; page_for_posts: number };
	} >( {
		method: 'POST',
		path: `/${ TEST_API }/reading-settings`,
		data: {
			...( undefined !== args.showOnFront && { show_on_front: args.showOnFront } ),
			...( undefined !== args.pageOnFront && { page_on_front: args.pageOnFront } ),
			...( undefined !== args.pageForPosts && { page_for_posts: args.pageForPosts } ),
		},
	} );

	return {
		previous: {
			showOnFront: response.previous.show_on_front,
			pageOnFront: response.previous.page_on_front,
			pageForPosts: response.previous.page_for_posts,
		},
		current: {
			showOnFront: response.current.show_on_front,
			pageOnFront: response.current.page_on_front,
			pageForPosts: response.current.page_for_posts,
		},
	};
}

/**
 * Wipe every post, non-Home/Blog page, comment, and non-default
 * category/post_tag term, via `dwpb-test/v1/reset-content`.
 *
 * @param requestUtils Admin request utils.
 */
export async function resetContent( requestUtils: RequestUtils ): Promise< void > {
	await requestUtils.rest( {
		method: 'POST',
		path: `/${ TEST_API }/reset-content`,
	} );
}

/**
 * Delete every `post` in any status, leaving pages/comments/terms alone, via
 * `dwpb-test/v1/delete-all-posts`.
 *
 * A narrower, cheaper sibling of {@link resetContent} for specs that only
 * seeded posts.
 *
 * @param requestUtils Admin request utils.
 * @return Number of posts deleted.
 */
export async function deleteAllPosts( requestUtils: RequestUtils ): Promise< number > {
	const response = await requestUtils.rest< { deleted: number } >( {
		method: 'POST',
		path: `/${ TEST_API }/delete-all-posts`,
	} );

	return response.deleted;
}

export interface SeedPostArgs {
	title: string;
	status?: string;
	content?: string;
	author?: number;
	commentStatus?: 'open' | 'closed';
	pingStatus?: 'open' | 'closed';
	/** Post type slug. Defaults (server-side) to `post`. */
	postType?: string;
	postDate?: string;
	categories?: number[];
	tags?: number[];
}

/**
 * Create a post via `dwpb-test/v1/create-post` (`wp_insert_post()`) — core
 * REST won't allow this post type under this plugin.
 *
 * The response carries no title, so the {@link SeededPost} title is the
 * value passed in, not read back from the server.
 *
 * @param requestUtils Admin request utils.
 * @param args         Post attributes.
 */
export async function seedPost(
	requestUtils: RequestUtils,
	args: SeedPostArgs
): Promise< SeededPost > {
	const response = await requestUtils.rest< {
		id: number;
		permalink: string;
		post_type: string;
		post_status: string;
	} >( {
		method: 'POST',
		path: `/${ TEST_API }/create-post`,
		data: {
			title: args.title,
			...( undefined !== args.status && { status: args.status } ),
			...( undefined !== args.content && { content: args.content } ),
			...( undefined !== args.author && { author: args.author } ),
			...( undefined !== args.commentStatus && {
				comment_status: args.commentStatus,
			} ),
			...( undefined !== args.pingStatus && {
				ping_status: args.pingStatus,
			} ),
			...( undefined !== args.postType && { post_type: args.postType } ),
			...( undefined !== args.postDate && { post_date: args.postDate } ),
			...( undefined !== args.categories && {
				categories: args.categories,
			} ),
			...( undefined !== args.tags && { tags: args.tags } ),
		},
	} );

	return {
		id: response.id,
		permalink: response.permalink,
		title: args.title,
		postType: response.post_type,
		postStatus: response.post_status,
	};
}

export interface SeedPageArgs {
	title: string;
	status?: string;
	content?: string;
	author?: number;
	commentStatus?: 'open' | 'closed';
	pingStatus?: 'open' | 'closed';
	parent?: number;
	date?: string;
}

/**
 * Create a page via core REST (`POST /wp/v2/pages`) — pages are untouched by
 * the plugin, so unlike {@link seedPost} this does not go through the test
 * API fixture.
 *
 * @param requestUtils Admin request utils.
 * @param args         Page attributes.
 */
export async function seedPage(
	requestUtils: RequestUtils,
	args: SeedPageArgs
): Promise< SeededPost > {
	const response = await requestUtils.rest< {
		id: number;
		link: string;
		type: string;
		status: string;
	} >( {
		method: 'POST',
		path: '/wp/v2/pages',
		data: {
			title: args.title,
			status: args.status ?? 'publish',
			...( undefined !== args.content && { content: args.content } ),
			...( undefined !== args.author && { author: args.author } ),
			...( undefined !== args.commentStatus && {
				comment_status: args.commentStatus,
			} ),
			...( undefined !== args.pingStatus && {
				ping_status: args.pingStatus,
			} ),
			...( undefined !== args.parent && { parent: args.parent } ),
			...( undefined !== args.date && { date: args.date } ),
		},
	} );

	return {
		id: response.id,
		permalink: response.link,
		title: args.title,
		postType: response.type,
		postStatus: response.status,
	};
}

export interface SeedCommentArgs {
	postId: number;
	content?: string;
	approved?: boolean;
	authorName?: string;
	authorEmail?: string;
}

/**
 * Create a comment via `dwpb-test/v1/create-comment` (`wp_insert_comment()`).
 *
 * @param requestUtils Admin request utils.
 * @param args         Comment attributes.
 */
export async function seedComment(
	requestUtils: RequestUtils,
	args: SeedCommentArgs
): Promise< { id: number; approved: boolean } > {
	return requestUtils.rest< { id: number; approved: boolean } >( {
		method: 'POST',
		path: `/${ TEST_API }/create-comment`,
		data: {
			post_id: args.postId,
			...( undefined !== args.content && { content: args.content } ),
			...( undefined !== args.approved && { approved: args.approved } ),
			...( undefined !== args.authorName && {
				author_name: args.authorName,
			} ),
			...( undefined !== args.authorEmail && {
				author_email: args.authorEmail,
			} ),
		},
	} );
}

export interface SeedTermArgs {
	taxonomy: string;
	name: string;
	/** Post id to assign the newly created term to. */
	assignTo?: number;
}

/**
 * Create a category/post_tag term via `dwpb-test/v1/create-term`
 * (`wp_insert_term()`), optionally assigning it straight onto a post.
 *
 * @param requestUtils Admin request utils.
 * @param args         Term attributes.
 */
export async function seedTerm(
	requestUtils: RequestUtils,
	args: SeedTermArgs
): Promise< { termId: number; slug: string; link: string } > {
	const response = await requestUtils.rest< {
		term_id: number;
		slug: string;
		link: string;
	} >( {
		method: 'POST',
		path: `/${ TEST_API }/create-term`,
		data: {
			taxonomy: args.taxonomy,
			name: args.name,
			...( undefined !== args.assignTo && { assign_to: args.assignTo } ),
		},
	} );

	return {
		termId: response.term_id,
		slug: response.slug,
		link: response.link,
	};
}

/**
 * Read a post's/page's status-relevant state via `dwpb-test/v1/post-state/<id>`.
 *
 * Reports `comments_open` / `pings_open` as WordPress evaluates them, not
 * just the raw `comment_status` column — the only option at all for `post`,
 * which has no core REST route under this plugin.
 *
 * @param requestUtils Admin request utils.
 * @param id           Post id.
 */
export async function postState(
	requestUtils: RequestUtils,
	id: number
): Promise< PostState > {
	const response = await requestUtils.rest< {
		id: number;
		post_status: string;
		post_type: string;
		comments_open: boolean;
		pings_open: boolean;
		permalink: string;
		comment_count: number;
	} >( {
		path: `/${ TEST_API }/post-state/${ id }`,
	} );

	return {
		id: response.id,
		postStatus: response.post_status,
		postType: response.post_type,
		commentsOpen: response.comments_open,
		pingsOpen: response.pings_open,
		permalink: response.permalink,
		commentCount: response.comment_count,
	};
}

/**
 * Force-delete posts/pages by id, via `dwpb-test/v1/post/<id>`.
 *
 * Handles any post type, and never throws — already-gone ids are tolerated
 * by the route, and this typically runs from `afterEach`, where a failure
 * would fail an otherwise-passing test.
 *
 * @param requestUtils Admin request utils.
 * @param ids          Post/page ids to remove.
 */
export async function deletePosts(
	requestUtils: RequestUtils,
	ids: number[]
): Promise< void > {
	await Promise.all(
		ids.map( async ( id ) => {
			try {
				await requestUtils.rest( {
					method: 'DELETE',
					path: `/${ TEST_API }/post/${ id }`,
				} );
			} catch {
				// Best-effort — see the docblock above.
			}
		} )
	);
}

/**
 * Delete terms by id, in whichever taxonomy owns each one, via
 * `dwpb-test/v1/term/<id>`.
 *
 * Never throws, for the same reason as {@link deletePosts}.
 *
 * @param requestUtils Admin request utils.
 * @param ids          Term ids to remove.
 */
export async function deleteTerms(
	requestUtils: RequestUtils,
	ids: number[]
): Promise< void > {
	await Promise.all(
		ids.map( async ( id ) => {
			try {
				await requestUtils.rest( {
					method: 'DELETE',
					path: `/${ TEST_API }/term/${ id }`,
				} );
			} catch {
				// Best-effort — see the docblock above.
			}
		} )
	);
}

/**
 * Flush rewrite rules via `dwpb-test/v1/flush-rewrites`.
 *
 * Needed after a spec changes the permalink structure or a redirect-related
 * option directly (rather than through `setupSite()`) and expects the new
 * rules to take effect within the same test.
 *
 * @param requestUtils Admin request utils.
 */
export async function flushRewrites( requestUtils: RequestUtils ): Promise< void > {
	await requestUtils.rest( {
		method: 'POST',
		path: `/${ TEST_API }/flush-rewrites`,
	} );
}
