/**
 * Widget-registry introspection via `dwpb-test/v1/widgets`.
 *
 * `wp-admin/widgets.php` `wp_die()`s in this environment: the pinned theme
 * (Twenty Twenty-Two) registers no sidebars, so `current_theme_supports(
 * 'widgets' )` is false and neither the classic nor block-based widgets
 * screen ever renders. `Disable_Blog_Admin::remove_widgets()` /
 * `::filter_widget_removal()` are therefore only observable through the
 * registry they mutate, `$wp_widget_factory->widgets`, which this route
 * reports directly.
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
 * Fetch the widget classes currently registered with WordPress.
 *
 * @param requestUtils Admin request utils.
 */
export async function registeredWidgets( requestUtils: RequestUtils ): Promise< string[] > {
	const response = await requestUtils.rest< { registered: string[] } >( {
		path: `/${ TEST_API }/widgets`,
	} );

	return response.registered;
}
