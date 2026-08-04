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
 * Map of hook name to override spec. Keys must start with `dwpb_` or the
 * plugin's own `dpwb_` typo-prefix (see `"dpwb_create_user_{$post_type}_column"`
 * in class-disable-blog-admin.php) — the fixture silently skips anything
 * else, so this can't become an arbitrary-hook injection surface.
 */
export type FilterOverrides = Record< string, FilterOverrideValue >;

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
