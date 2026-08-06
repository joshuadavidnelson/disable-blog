/**
 * Direct coverage for the generic filter-override *resolver* itself
 * (`dwpb_test_filters_resolve_spec()` + `dwpb_test_filters_transform()` in
 * `dwpb-test-filters.php`), not just the plugin filters it's used to
 * override elsewhere. That mechanism now backs most of `filters/*.spec.ts`,
 * so a silent decoding bug -- `append` doing nothing, `priority` ignored, an
 * unrecognized spec shape guessed at instead of skipped -- would make many
 * of those specs pass vacuously rather than fail.
 *
 * Three entry points, matching three things this file can get wrong:
 *
 * 1. The resolver's decode/transform logic, via the
 *    `dwpb-test/v1/resolve-filter-override` probe route, which skips the
 *    `dwpb_`/`dpwb_` name guard entirely.
 * 2. That name guard itself, only enforced on the real registration path.
 * 3. `dwpb_test_filter_overrides` holding malformed JSON, decoded once at
 *    mu-plugin load time -- also only observable via the real path.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { seedPage, deletePosts, uniqueTitle } from '../../config/seed';
import {
	setFilterOverrides,
	resetFilterOverrides,
	setRawFilterOverridesOption,
	resolveFilterOverride,
} from '../../config/filter-overrides';

test.describe( 'filters: generic filter-override resolver (probe route)', () => {
	test( 'a bare scalar override (bool/int/string) returns verbatim, regardless of incoming', async ( {
		requestUtils,
	} ) => {
		const bools = await resolveFilterOverride( requestUtils, true, 'incoming-value' );
		expect( bools ).toEqual( { skipped: false, output: true, priority: 10 } );

		const boolsFalse = await resolveFilterOverride( requestUtils, false, 'incoming-value' );
		expect( boolsFalse ).toEqual( { skipped: false, output: false, priority: 10 } );

		const ints = await resolveFilterOverride( requestUtils, 5, 'incoming-value' );
		expect( ints ).toEqual( { skipped: false, output: 5, priority: 10 } );

		const strings = await resolveFilterOverride( requestUtils, 'override-string', 'incoming-value' );
		expect( strings ).toEqual( { skipped: false, output: 'override-string', priority: 10 } );
	} );

	test( 'a "set" override returns verbatim, including an array, false, and null', async ( {
		requestUtils,
	} ) => {
		const arrayResult = await resolveFilterOverride(
			requestUtils,
			{ set: [ 1, 2, 3 ] },
			'ignored-incoming'
		);
		expect( arrayResult ).toEqual( { skipped: false, output: [ 1, 2, 3 ], priority: 10 } );

		const falseResult = await resolveFilterOverride( requestUtils, { set: false }, 'ignored-incoming' );
		expect( falseResult ).toEqual( { skipped: false, output: false, priority: 10 } );

		const nullResult = await resolveFilterOverride( requestUtils, { set: null }, 'ignored-incoming' );
		expect( nullResult ).toEqual( { skipped: false, output: null, priority: 10 } );
	} );

	test( 'an "append" override array_merge()s onto an incoming array', async ( { requestUtils } ) => {
		const result = await resolveFilterOverride(
			requestUtils,
			{ append: [ 'x', 'y' ] },
			[ 'a', 'b' ]
		);
		expect( result ).toEqual( { skipped: false, output: [ 'a', 'b', 'x', 'y' ], priority: 10 } );
	} );

	test( 'a "remove" override array_diff()s from an incoming array', async ( { requestUtils } ) => {
		// The removed element sits last: array_diff() preserves surviving
		// elements' original keys rather than reindexing, so removing from
		// the middle would leave a non-contiguous result that round-trips
		// through JSON as an object, not an array.
		const result = await resolveFilterOverride(
			requestUtils,
			{ remove: [ 'remove-me' ] },
			[ 'a', 'b', 'remove-me' ]
		);
		expect( result ).toEqual( { skipped: false, output: [ 'a', 'b' ], priority: 10 } );
	} );

	// Both modes cast a non-array incoming value with PHP's (array) operator
	// before combining it with the override -- (array) "x" becomes [0 => "x"],
	// (array) null becomes [] -- rather than skip the merge/diff.
	test( 'an "append" override given a non-array incoming value wraps that value at index 0', async ( {
		requestUtils,
	} ) => {
		const scalarIncoming = await resolveFilterOverride( requestUtils, { append: [ 'a', 'b' ] }, 'x' );
		expect( scalarIncoming ).toEqual( { skipped: false, output: [ 'x', 'a', 'b' ], priority: 10 } );

		// (array) null === [] -- nothing is prepended.
		const nullIncoming = await resolveFilterOverride( requestUtils, { append: [ 'a', 'b' ] }, null );
		expect( nullIncoming ).toEqual( { skipped: false, output: [ 'a', 'b' ], priority: 10 } );
	} );

	test( 'a "remove" override given a non-array incoming value diffs against the wrapped value', async ( {
		requestUtils,
	} ) => {
		const removed = await resolveFilterOverride( requestUtils, { remove: [ 'x' ] }, 'x' );
		expect( removed ).toEqual( { skipped: false, output: [], priority: 10 } );

		const kept = await resolveFilterOverride( requestUtils, { remove: [ 'a' ] }, 'x' );
		expect( kept ).toEqual( { skipped: false, output: [ 'x' ], priority: 10 } );

		// (array) null === [] -- array_diff() against an empty array is always [].
		const nullIncoming = await resolveFilterOverride( requestUtils, { remove: [ 'x' ] }, null );
		expect( nullIncoming ).toEqual( { skipped: false, output: [], priority: 10 } );
	} );

	test( 'an unrecognized object shape is skipped rather than guessed at', async ( { requestUtils } ) => {
		const result = await resolveFilterOverride( requestUtils, { nonsense: 1 }, 'incoming-value' );
		expect( result ).toEqual( { skipped: true, output: null, priority: null } );
	} );

	test( 'an explicit "priority" is honoured, and defaults to 10 otherwise', async ( { requestUtils } ) => {
		const defaulted = await resolveFilterOverride( requestUtils, { set: 'hi' }, 'incoming-value' );
		expect( defaulted.priority ).toBe( 10 );

		const explicit = await resolveFilterOverride(
			requestUtils,
			{ set: 'hi', priority: 7 },
			'incoming-value'
		);
		expect( explicit.priority ).toBe( 7 );
	} );
} );

test.describe( 'filters: generic filter-override resolver -- hook-name guard (real registration path)', () => {
	let pagePermalink: string;
	let pageId: number;

	test.beforeAll( async ( { requestUtils } ) => {
		const page = await seedPage( requestUtils, {
			title: uniqueTitle( 'filter override guard probe' ),
			content: 'Guard probe body text.',
		} );
		pageId = page.id;
		pagePermalink = page.permalink;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
		await deletePosts( requestUtils, [ pageId ] );
	} );

	test( 'an override on a hook name not starting with dwpb_/dpwb_ is never registered', async ( {
		request,
		requestUtils,
	} ) => {
		await setFilterOverrides( requestUtils, {
			// 'the_content' fires on every page render, unlike a made-up name
			// that nothing ever calls -- an override on a hook nobody fires
			// would "have no effect" whether or not the guard worked.
			the_content: { set: 'DWPB-GUARD-SHOULD-NEVER-APPEAR' },
		} );

		const response = await request.get( pagePermalink );
		const body = await response.text();

		expect( body ).toContain( 'Guard probe body text.' );
		expect( body ).not.toContain( 'DWPB-GUARD-SHOULD-NEVER-APPEAR' );
	} );
} );

test.describe( 'filters: generic filter-override resolver -- malformed JSON safety', () => {
	test.afterEach( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'malformed JSON in dwpb_test_filter_overrides does not fatal the site', async ( {
		request,
		requestUtils,
	} ) => {
		// A regression guard, not proof today's code can fatal: PHP's foreach()
		// already tolerates the non-array json_decode() result with a warning,
		// not a fatal. Locks in that resilience against a future change to
		// dwpb_test_filters_register_overrides() that assumes $overrides is
		// always an array -- every other spec in this suite shares this site.
		await setRawFilterOverridesOption( requestUtils, '{not valid json!!' );

		const home = await request.get( '/' );
		expect( home.status() ).toBe( 200 );

		// Also confirms every mu-plugin's rest_api_init handler still runs to
		// completion, including dwpb-test-api.php's own routes.
		const restIndex = await request.get( '/wp-json/' );
		expect( restIndex.status() ).toBe( 200 );
	} );
} );
