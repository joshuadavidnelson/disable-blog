/**
 * Two admin-redirect filters reachable only through the generic
 * filter-override mechanism (`config/filter-overrides.ts` +
 * `dwpb-test-filters.php`), since neither name is spelled out as a string
 * literal elsewhere in the plugin.
 *
 * `dwpb_redirect_admin_edit` is one of nine per-screen filters
 * `redirect_admin_pages()` builds as `'dwpb_redirect_admin_' .
 * str_replace( '-', '_', $pagename )`.
 *
 * `dwpb_redirect_admin_options_tools` is the deprecated alias
 * `redirect_admin_pages()` forwards for `tools.php` via
 * `apply_filters_deprecated()`. That function skips `_deprecated_hook()`
 * entirely when nothing is hooked to the deprecated name -- which is why it
 * stays silent in every other spec that hits tools.php -- and even once
 * hooked, the notice is gated on `WP_DEBUG`, which `@wordpress/env` forces
 * off here. `apply_filters_ref_array()` still applies the filter regardless,
 * which is what this test actually exercises.
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
		// Confirms the dynamically-built filter name resolves per-screen
		// rather than one umbrella filter firing for every admin page.
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
