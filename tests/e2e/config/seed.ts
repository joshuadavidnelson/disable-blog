/**
 * Content seeding + inspection helpers shared by every spec.
 *
 * WHY THIS FILE EXISTS: `Disable_Blog_Admin::modify_post_type_arguments()`,
 * hooked on `init` at priority 25, sets `show_in_rest` (and `public`,
 * `show_ui`, ...) to `false` on the `post` post type for the entire life of
 * the test environment. That means core REST has no route left for `post` at
 * all — `POST /wp/v2/posts` returns `rest_no_route`, and the standard
 * `RequestUtils.createPost()` helper simply cannot be used. Posts, comments,
 * and category/tag terms therefore go through the `dwpb-test/v1` mu-plugin
 * fixture, an authenticated back door that calls `wp_insert_post()` /
 * `wp_insert_comment()` / `wp_insert_term()` directly, the same way WP-CLI or
 * a direct database actor would.
 *
 * Pages are untouched by the plugin — `seedPage()` goes through core REST
 * (`/wp/v2/pages`) like any stock WordPress install, and MUST keep doing so;
 * routing pages through the test API would exercise a code path real authors
 * never use.
 *
 * The PHP fixture's JSON is snake_case (`post_status`, `comment_status`, ...).
 * Every helper below converts it to camelCase before returning, so no spec
 * ever has to read a snake_case key off a seed result.
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
 * Build a title that is unique across specs, runs and workers.
 *
 * Specs must be independently re-runnable; a unique title keeps slug
 * collisions (`my-post-2`) and cross-spec locator matches (two specs both
 * asserting on a link with the text "Test Post") from ever happening.
 *
 * @param prefix Human-readable prefix, usually the spec name.
 */
export function uniqueTitle( prefix: string ): string {
	titleCounter += 1;

	return `${ prefix } ${ Date.now().toString( 36 ) }-${ titleCounter }`;
}

let cachedSiteConfig: SiteConfig | null = null;

/**
 * Idempotently seed the Home/Blog pages and the reading settings that point
 * at them, via `dwpb-test/v1/setup`.
 *
 * Safe to call more than once: the route only creates what is missing and
 * corrects what has drifted (e.g. a spec that changed `show_on_front` and
 * did not restore it). Always re-fetches — use {@link siteConfig} instead
 * when a memoized read is good enough.
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
 * Memoized wrapper around {@link setupSite}.
 *
 * The Home/Blog ids and permalink structure are static for the duration of a
 * run unless a spec deliberately changes them (and such a spec is
 * responsible for restoring them, typically by calling {@link setupSite}
 * again). Everything else can read the cached value instead of paying a
 * request per call.
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
 * WordPress 5.9's core `/wp/v2/settings` endpoint does not expose these three
 * keys at all -- they are simply absent from both the schema and the
 * response, so a `PUT` against them silently no-ops instead of erroring. A
 * spec relying on core REST here would never actually establish its
 * precondition on that version, even though the notice it's testing for is a
 * real, reachable branch of `admin_notices()`. This route calls
 * `update_option()` directly instead, the same way WP-CLI or a direct
 * database actor would, and hands back the values it overwrote so a caller
 * can restore exactly what it changed rather than assuming defaults.
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
 * Create a post via `dwpb-test/v1/create-post` (`wp_insert_post()` under the
 * hood) — the route core REST won't allow for the `post` post type under
 * this plugin.
 *
 * The response carries no title (`dwpb_test_api_create_post()` doesn't
 * return one), so the title on the returned {@link SeededPost} is the value
 * passed in, not a value read back from the server.
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
 * Create a page via core REST (`POST /wp/v2/pages`).
 *
 * Pages are untouched by Disable Blog's `show_in_rest` stripping, so — unlike
 * {@link seedPost} — this deliberately does NOT go through the
 * `dwpb-test/v1` fixture. Routing pages through the test API would exercise
 * a back door real authors never use for the one content type the plugin
 * leaves alone.
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
 * Preferred over `/wp/v2/pages/<id>` (and the only option at all for
 * `post`, which has no core REST route under this plugin) because it also
 * reports `comments_open` / `pings_open` as WordPress itself would evaluate
 * them (`comments_open()` / `pings_open()`), not just the raw
 * `comment_status` column.
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
 * Deletes any post type — `wp_delete_post()` under the hood handles posts
 * and pages alike, so callers don't need to track which type each id was;
 * pass a mixed list straight from whatever a spec seeded. Ids that are
 * already gone are tolerated (the route itself is idempotent, returning
 * `deleted: false` with HTTP 200 rather than a 404), and this helper never
 * throws: this runs from `afterEach`, and a spec that already deleted
 * something as part of its assertions must still be able to run its own
 * teardown without failing an otherwise-passing test.
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
