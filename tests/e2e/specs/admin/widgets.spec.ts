/**
 * The widget registry (`$wp_widget_factory->widgets`), default plugin state,
 * and the `dwpb_unregister_widgets` filter it runs through.
 *
 * `wp-admin/widgets.php` `wp_die()`s in this environment — the pinned theme
 * registers no sidebars, so the widgets screen never renders. Assertions
 * below read the registry through `dwpb-test/v1/widgets` instead.
 *
 * WP_Widget_Links is never asserted here: core only registers it when the
 * (deprecated) Link Manager is on, which is off in this environment
 * regardless of the plugin.
 *
 * `cptEnabled` (filters/cpt-branches.spec.ts) can't exercise
 * `filter_widget_removal()`'s taxonomy branches: `wp_widgets_init()` runs on
 * `init` priority 1, before the fixture's `register_post_type()` call at
 * priority 10, so the taxonomy doesn't exist yet when `remove_widgets()`
 * checks it. The `dwpb_taxonomy_support` and
 * `dwpb_post_types_supporting_comments` overrides reach the same branches
 * without that ordering problem, so they stand in below.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { registeredWidgets } from '../../config/widgets';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

/**
 * `WP_Widget_Categories` and `WP_Widget_Tag_Cloud` do have a veto branch in
 * `filter_widget_removal()` (see "conditional widgets restored" below); it
 * just isn't reached by default, since no other post type supports the
 * category/post_tag taxonomies out of the box.
 */
const REMOVED_BY_DEFAULT = [
	'WP_Widget_Tag_Cloud',
	'WP_Widget_Categories',
	'WP_Widget_Archives',
	'WP_Widget_Calendar',
	'WP_Widget_Recent_Posts',
	'WP_Widget_RSS',
] as const;

test.describe( 'admin: widgets registry (default state)', () => {
	test( 'the widgets removed by default are unregistered', async ( { requestUtils } ) => {
		const registered = await registeredWidgets( requestUtils );

		for ( const widget of REMOVED_BY_DEFAULT ) {
			expect( registered ).not.toContain( widget );
		}

		// Control: core widgets outside the plugin's removal list are still there.
		expect( registered ).toContain( 'WP_Widget_Search' );
		expect( registered ).toContain( 'WP_Widget_Pages' );
	} );

	test( 'Recent Comments stays registered because page/attachment already support comments', async ( {
		requestUtils,
	} ) => {
		const registered = await registeredWidgets( requestUtils );

		// 'page' and 'attachment' both support 'comments' out of the box, so
		// filter_widget_removal() vetoes the removal without any fixture at all.
		expect( registered ).toContain( 'WP_Widget_Recent_Comments' );
	} );
} );

test.describe( 'admin: widgets registry — conditional widgets restored', () => {
	test.describe( 'a taxonomy shared with another post type (dwpb_taxonomy_support override)', () => {
		test.beforeAll( async ( { requestUtils } ) => {
			await setFilterOverrides( requestUtils, {
				dwpb_taxonomy_support: { set: [ 'page' ] },
			} );
		} );

		test.afterAll( async ( { requestUtils } ) => {
			await resetFilterOverrides( requestUtils );
		} );

		test( 'the Categories and Tag Cloud widgets come back', async ( { requestUtils } ) => {
			const registered = await registeredWidgets( requestUtils );

			expect( registered ).toContain( 'WP_Widget_Categories' );
			expect( registered ).toContain( 'WP_Widget_Tag_Cloud' );

			// Control: a widget with no taxonomy branch is unaffected by this override.
			expect( registered ).not.toContain( 'WP_Widget_Archives' );
		} );
	} );

	test.describe( 'comments unsupported by any other post type (dwpb_post_types_supporting_comments override)', () => {
		test.beforeAll( async ( { requestUtils } ) => {
			await setFilterOverrides( requestUtils, {
				dwpb_post_types_supporting_comments: false,
			} );
		} );

		test.afterAll( async ( { requestUtils } ) => {
			await resetFilterOverrides( requestUtils );
		} );

		test( 'Recent Comments is unregistered once forced false', async ( { requestUtils } ) => {
			const registered = await registeredWidgets( requestUtils );

			expect( registered ).not.toContain( 'WP_Widget_Recent_Comments' );

			// Control: a widget with no comments branch is unaffected by this override.
			expect( registered ).not.toContain( 'WP_Widget_Archives' );
		} );
	} );
} );

test.describe( 'admin: widgets registry — dwpb_unregister_widgets override', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_unregister_widgets: false,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'forcing the filter false keeps every targeted widget registered', async ( {
		requestUtils,
	} ) => {
		const registered = await registeredWidgets( requestUtils );

		for ( const widget of REMOVED_BY_DEFAULT ) {
			expect( registered ).toContain( widget );
		}
	} );
} );
