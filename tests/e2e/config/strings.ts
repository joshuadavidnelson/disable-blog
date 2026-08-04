/**
 * User-facing strings a spec asserts against, fetched from the plugin's own
 * source via `dwpb-test/v1/strings` instead of duplicated as literals, so a
 * wording change can't silently desync the suite. Memoized per run.
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
 * Shape of the `dwpb-test/v1/strings` response.
 */
export interface PluginStrings {
	/** `Disable_Blog_Admin::admin_notices()`, the "no static front page" branch. */
	no_front_page_notice: string;
	/** `Disable_Blog_Admin::admin_notices()`, the "front page === posts page" branch. */
	front_equals_posts_notice: string;
	/** `Disable_Blog_Admin::posts_page_notice()`. */
	posts_page_edit_notice: string;
	/**
	 * `Disable_Blog_Admin::disable_press_this()`. Not passed through `__()` in
	 * the plugin itself, so the PHP fixture reproduces it verbatim rather than
	 * translating it.
	 */
	press_this_disabled: string;
	/** `Disable_Blog_Admin::page_post_states()`. */
	page_post_state: string;
	/**
	 * `Disable_Blog_Admin::manage_users_columns()`. Not a plugin-owned string —
	 * it's the `page` post type's own label object, mirrored here so a core
	 * label change is caught the same way a plugin copy change would be.
	 */
	users_pages_column_label: string;
}

let cached: PluginStrings | null = null;

/**
 * Fetch the plugin's user-facing strings, memoized across the suite.
 *
 * @param requestUtils Admin request utils.
 */
export async function pluginStrings(
	requestUtils: RequestUtils
): Promise< PluginStrings > {
	if ( ! cached ) {
		const fetched = await requestUtils.rest< PluginStrings >( {
			path: `/${ TEST_API }/strings`,
		} );

		// Throw on empty values so a toContainText()-style assertion can't
		// pass vacuously.
		for ( const [ key, value ] of Object.entries( fetched ) ) {
			if ( ! value ) {
				throw new Error(
					`${ TEST_API }/strings returned an empty value for "${ key }".`
				);
			}
		}

		cached = fetched;
	}

	return cached;
}
