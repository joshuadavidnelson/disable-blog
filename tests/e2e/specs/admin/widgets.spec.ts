/**
 * The widget registry (`$wp_widget_factory->widgets`), default plugin state,
 * plus the `dwpb_unregister_widgets` filter it runs through.
 *
 * COVERAGE: `Disable_Blog_Admin::remove_widgets()`, hooked on `widgets_init`,
 * unregisters eight legacy blog widgets, each gated by `dwpb_unregister_widgets`
 * (fired once per widget, `true` by default). `filter_widget_removal()`,
 * hooked onto that same filter, vetoes the removal of WP_Widget_Categories /
 * WP_Widget_Tag_Cloud / WP_Widget_Recent_Comments specifically when another
 * post type still uses their taxonomy/feature.
 *
 * OBSERVABILITY: `wp-admin/widgets.php` `wp_die()`s outright in this
 * environment (verified directly) — the pinned theme registers no sidebars,
 * so `current_theme_supports( 'widgets' )` is false and neither the classic
 * nor block-based widgets screen ever renders. Every assertion below reads
 * `$wp_widget_factory->widgets` through `dwpb-test/v1/widgets`
 * (`config/widgets.ts`) instead.
 *
 * WP_Widget_Links is excluded from every list here on purpose: WordPress
 * core only registers it when the (long-deprecated) Link Manager is on, and
 * `link_manager_enabled` is off in this environment regardless of the
 * plugin — so it's absent in every state below, and asserting that would
 * prove nothing.
 *
 * `cptEnabled` (see filters/cpt-branches.spec.ts) cannot exercise
 * `filter_widget_removal()`'s taxonomy branches: `wp_widgets_init()` runs on
 * `init` at priority 1, while the fixture's `register_post_type( 'news',
 * ... )` call runs on `init` at priority 10 — 'news' does not exist yet when
 * `remove_widgets()` reads `dwpb_post_types_with_tax()` (verified directly:
 * toggling the fixture on leaves the registry unchanged). The generic
 * `dwpb_taxonomy_support` override (already used in
 * filters/taxonomy-support-override.spec.ts) and the
 * `dwpb_post_types_supporting_comments` override
 * (filters/comments-unsupported.spec.ts) reach the exact same downstream
 * branches without that ordering problem, so they stand in below.
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
 * Widgets `remove_widgets()` unregisters in the default environment.
 * `WP_Widget_Categories` and `WP_Widget_Tag_Cloud` do have a veto branch in
 * `filter_widget_removal()` (see the "conditional widgets restored" describe
 * block below, which triggers it) — it just isn't reached by default, since
 * no other post type supports the category/post_tag taxonomies out of the
 * box. The other four widgets have no veto branch at all.
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

		// Control: proves the route reports the real registry rather than
		// something that reads empty regardless — core widgets outside the
		// plugin's removal list are still there.
		expect( registered ).toContain( 'WP_Widget_Search' );
		expect( registered ).toContain( 'WP_Widget_Pages' );
	} );

	test( 'Recent Comments stays registered because page/attachment already support comments', async ( {
		requestUtils,
	} ) => {
		const registered = await registeredWidgets( requestUtils );

		// 'page' and 'attachment' both support 'comments' out of the box, so
		// dwpb_post_types_with_feature( 'comments' ) is truthy here without any
		// fixture at all — filter_widget_removal() vetoes the removal. Proven
		// non-vacuous below: forcing that support false removes it.
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

		// Every widget the default-state test above proved gone is back,
		// including the ones filter_widget_removal() never touches — the raw
		// filter return value alone controls unregister_widget() here.
		for ( const widget of REMOVED_BY_DEFAULT ) {
			expect( registered ).toContain( widget );
		}
	} );
} );
