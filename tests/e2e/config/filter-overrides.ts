/**
 * Generic filter-override mechanism for the e2e suite, backed by the
 * `dwpb_test_filter_overrides` mu-plugin fixture.
 *
 * Unlike `fixtures.ts`'s one-option-per-hook toggles, this reaches every
 * `dwpb_`/`dpwb_` filter the plugin fires — including the hooks built by
 * string concatenation inside foreach loops (e.g. `'dwpb_redirect_' .
 * $filtername`, `"dwpb_disable_{$metabox_id}"`) that never appear as string
 * literals and so can never get a bespoke toggle. A spec supplies a map of
 * hook name to override spec, JSON-encoded into a single `string`-typed
 * option rather than a nested object/array setting: WordPress 5.9 (in the CI
 * matrix) doesn't reliably validate/save nested-object REST settings
 * schemas, where a JSON string round-trips on every supported version.
 *
 * Kept separate from `fixtures.ts` because the two don't share a shape:
 * `FIXTURE_TOGGLES` is a fixed set of boolean/integer options with a known
 * "off" value each, while this is one option holding an open-ended map with
 * its own reset semantics (clear the whole map, not toggle it off).
 *
 * @see tests/e2e/fixtures/dwpb-test-filters.php
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Option name of the JSON-encoded override map.
 */
export const FILTER_OVERRIDES_OPTION = 'dwpb_test_filter_overrides';

/**
 * A JSON-serializable value: anything `JSON.stringify()` round-trips.
 */
export type JsonValue = null | boolean | number | string | JsonValue[] | { [ key: string ]: JsonValue };

/**
 * Priority passed to the fixture's `add_filter()` call. Defaults to 10 (the
 * mu-plugin's default) when omitted.
 */
interface FilterOverridePriority {
	priority?: number;
}

/** Return `set` verbatim, in place of the filter's incoming (first) argument. */
export interface SetFilterOverride extends FilterOverridePriority {
	set: JsonValue;
}

/** `array_merge()` `append` onto the incoming array value. */
export interface AppendFilterOverride extends FilterOverridePriority {
	append: JsonValue[];
}

/** `array_diff()` `remove` from the incoming array value. */
export interface RemoveFilterOverride extends FilterOverridePriority {
	remove: JsonValue[];
}

/**
 * One entry of the override map: a bare scalar the filter returns verbatim,
 * or one of the three object forms the mu-plugin fixture understands.
 *
 * @see tests/e2e/fixtures/dwpb-test-filters.php
 */
export type FilterOverrideValue =
	| boolean
	| number
	| string
	| SetFilterOverride
	| AppendFilterOverride
	| RemoveFilterOverride;

/**
 * Every `dwpb_`/`dpwb_` filter name this suite has a reason to name, grouped
 * by the `includes/` file that fires it. Literal names are copied straight
 * from that file's `apply_filters()`/`apply_filters_deprecated()` call; the
 * ones built by string concatenation (`'dwpb_redirect_' . $filtername`,
 * `'dwpb_' . $function`, `"dwpb_disable_{$metabox_id}"`,
 * `"d[wp]pb_create_user_{$post_type}_column"`) are spelled out here as the
 * concrete instances that loop produces, not the template.
 *
 * Not exhaustive — `dwpb_create_user_{$post_type}_column` fires once per
 * post type `user_column_post_types()` reports, and only the `page` instance
 * this suite exercises is listed. Add an entry here when a spec needs a hook
 * that isn't yet one.
 */
export const KNOWN_HOOKS = [
	// includes/class-disable-blog-admin.php
	'dwpb_disable_user_post_column',
	'dpwb_disable_user_post_column', // deprecated alias of the above
	'dwpb_create_user_page_column', // "dwpb_create_user_{$post_type}_column", post_type = 'page'
	'dpwb_create_user_page_column', // deprecated alias of the above
	'dwpb_admin_user_post_types',
	'dwpb_admin_redirect_url',
	'dwpb_redirect_admin',
	'dwpb_redirect_admin_post', // 'dwpb_' . 'redirect_admin_' . 'post'
	'dwpb_redirect_admin_edit',
	'dwpb_redirect_admin_post_new',
	'dwpb_redirect_admin_edit_tags',
	'dwpb_redirect_admin_term',
	'dwpb_redirect_admin_edit_comments',
	'dwpb_redirect_admin_options_discussion',
	'dwpb_redirect_admin_options_writing',
	'dwpb_redirect_admin_tools',
	'dwpb_redirect_admin_options_tools', // deprecated alias of dwpb_redirect_admin_tools
	'dwpb_menu_pages_to_remove',
	'dwpb_menu_subpages_to_remove',
	'dwpb_remove_options_writing',
	'dwpb_disable_dashboard_quick_press', // "dwpb_disable_{$metabox_id}", metabox_id = 'dashboard_quick_press'
	'dwpb_disable_dashboard_activity', // same loop, metabox_id = 'dashboard_activity'
	'dwpb_unregister_widgets',

	// includes/class-disable-blog-functions.php
	'dwpb_pass_query_string_on_redirect',
	'dwpb_allowed_query_vars',
	'dwpb_redirect_status_code',
	'dwpb_author_archive_post_types',
	'dwpb_disable_author_archives',
	'dwpb_disable_feed',

	// includes/class-disable-blog-public.php
	'dwpb_redirect_post', // 'dwpb_redirect_' . 'post'
	'dwpb_redirect_post_tag_archive',
	'dwpb_redirect_category_archive',
	'dwpb_redirect_blog_page',
	'dwpb_redirect_date_archive',
	'dwpb_redirect_author_archive',
	'dwpb_redirect_front_end',
	'dwpb_front_end_redirect_url',
	'dwpb_redirect_feeds',
	'dwpb_feed_message',
	'dwpb_feed_die_message',
	'dwpb_disabled_xmlrpc_methods',
	'dwpb_remove_pingback_header',
	'dwpb_disable_user_sitemap',
	'dwpb_disable_removed_sitemaps',

	// includes/functions.php
	'dwpb_taxonomy_support',
	'dwpb_post_types_supporting_comments', // "dwpb_post_types_supporting_{$feature}", feature = 'comments'
] as const;

/**
 * A filter name from {@link KNOWN_HOOKS}, for autocomplete — see
 * {@link FilterOverrides} for why an arbitrary string is still accepted
 * alongside it.
 */
export type KnownHook = ( typeof KNOWN_HOOKS )[ number ];

/**
 * Map of hook name to override spec. Keys must start with `dwpb_` or the
 * plugin's own `dpwb_` typo-prefix (see `"dpwb_create_user_{$post_type}_column"`
 * in class-disable-blog-admin.php) — the fixture silently skips anything
 * else, so this can't become an arbitrary-hook injection surface.
 *
 * The key type autocompletes every {@link KnownHook}, while `string & {}`
 * keeps a plain string assignable too — the fixture reaches hooks (like the
 * per-post-type ones above) this file doesn't enumerate.
 */
export type FilterOverrides = Partial<
	Record< KnownHook | ( string & {} ), FilterOverrideValue >
>;

/**
 * Set one or more filter overrides, via a single `PUT /wp/v2/settings`.
 *
 * Replaces the whole map rather than merging onto whatever's already
 * stored — the fixture always re-decodes the option from scratch, so pass
 * every entry a test needs in one call.
 *
 * @param requestUtils Admin request utils.
 * @param overrides    Map of hook name to override spec.
 */
export async function setFilterOverrides(
	requestUtils: RequestUtils,
	overrides: FilterOverrides
): Promise< void > {
	await requestUtils.rest( {
		method: 'PUT',
		path: '/wp/v2/settings',
		data: { [ FILTER_OVERRIDES_OPTION ]: JSON.stringify( overrides ) },
	} );
}

/**
 * Clear every filter override.
 *
 * @param requestUtils Admin request utils.
 */
export async function resetFilterOverrides( requestUtils: RequestUtils ): Promise< void > {
	await requestUtils.rest( {
		method: 'PUT',
		path: '/wp/v2/settings',
		data: { [ FILTER_OVERRIDES_OPTION ]: '' },
	} );
}
