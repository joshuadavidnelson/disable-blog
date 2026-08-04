/**
 * Two admin-redirect filters covered only through the generic filter-override
 * mechanism (`config/filter-overrides.ts` + `dwpb-test-filters.php`):
 *
 * `dwpb_redirect_admin_edit` is one of the NINE per-screen filters
 * `redirect_admin_pages()` builds as `'dwpb_' . 'redirect_admin_' .
 * str_replace( '-', '_', $pagename )`. Only the `tools` entry's filter name
 * (below) is ever spelled out as a string literal anywhere in the plugin, so
 * this is the only way to reach the other eight.
 *
 * `dwpb_redirect_admin_options_tools` is the deprecated alias
 * `redirect_admin_pages()` forwards through `apply_filters_deprecated()` for
 * `tools.php` only. `apply_filters_deprecated()` bails out before calling
 * `_deprecated_hook()` at all when nothing is hooked to the deprecated name
 * (`if ( ! has_filter( $hook_name ) ) { return $args[0]; }`), which is why
 * this alias has stayed silent in every other spec that hits tools.php.
 * Registering the override here makes `has_filter()` true, so
 * `_deprecated_hook()` does run — but its own notice is gated on `WP_DEBUG`,
 * which `@wordpress/env` forces off for the `tests` environment by default
 * (`env.tests.config` in its own defaults, unrelated to and not overridden by
 * this project's `.wp-env.json`), so no notice reaches the response body or
 * debug.log to mask a broken redirect. `apply_filters_ref_array()` still
 * applies the filter regardless of that notice, which is the part this test
 * actually exercises.
 */

/**
 * WordPress dependencies
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig } from '../../config/seed';
import { adminUrl } from '../../config/admin';
import { expectRedirect } from '../../config/redirects';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

// Same host as home_url(): wp_safe_redirect() rejects an off-site Location.
const OVERRIDE_PATH_EDIT = '/dwpb-test-filter-override-admin-edit/';
const OVERRIDE_PATH_TOOLS_DEPRECATED = '/dwpb-test-filter-override-tools-deprecated/';

test.describe( 'filters: dwpb_redirect_admin_edit (one of the nine dynamic admin redirect filters)', () => {
	let overrideUrl: string;
	let dashboardTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		overrideUrl = `${ config.homeUrl }${ OVERRIDE_PATH_EDIT }`;
		dashboardTarget = `${ config.homeUrl }${ adminUrl( 'index.php' ) }`;

		await setFilterOverrides( requestUtils, {
			dwpb_redirect_admin_edit: overrideUrl,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'edit.php redirects to the overridden URL', async ( { request } ) => {
		await expectRedirect( request, adminUrl( 'edit.php' ), overrideUrl );
	} );

	test( 'tools.php still redirects to its own default target', async ( { request } ) => {
		// Different filter name built by the same foreach loop, left
		// untouched by the override above -- confirms the dynamically-built
		// name resolves per-screen rather than one umbrella filter firing
		// for every admin page.
		await expectRedirect( request, adminUrl( 'tools.php' ), dashboardTarget );
	} );
} );

test.describe( 'filters: dwpb_redirect_admin_options_tools (deprecated alias)', () => {
	let overrideUrl: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		overrideUrl = `${ config.homeUrl }${ OVERRIDE_PATH_TOOLS_DEPRECATED }`;

		await setFilterOverrides( requestUtils, {
			dwpb_redirect_admin_options_tools: overrideUrl,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'tools.php honours the deprecated filter name', async ( { request } ) => {
		// Only the deprecated alias is overridden here -- the modern
		// dwpb_redirect_admin_tools filter is untouched, so this target can
		// only come from the apply_filters_deprecated() forwarding.
		await expectRedirect( request, adminUrl( 'tools.php' ), overrideUrl );
	} );
} );
