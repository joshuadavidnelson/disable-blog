/**
 * Site Health's REST Availability check, and Press This, default plugin
 * state.
 *
 * COVERAGE:
 *  - `Disable_Blog_Admin::site_status_tests()`, hooked on `site_status_tests`,
 *    swaps core's `rest_availability` Site Health test for
 *    `get_test_rest_availability()`, a copy of core's own test with the
 *    probed route changed from `wp/v2/types/post` to `wp/v2/types/page` --
 *    the 'post' route being unreachable under this plugin (see
 *    `rest-xmlrpc/rest-api.spec.ts`) would otherwise make this check fail on
 *    every install of this plugin.
 *  - `Disable_Blog_Admin::disable_press_this()`, hooked on
 *    `load-press-this.php`, unconditionally `wp_die()`s.
 *
 * REST AVAILABILITY IS A 'DIRECT' TEST, NOT 'ASYNC' -- VERIFIED, NOT ASSUMED:
 * `rest_availability` lives in `WP_Site_Health::get_tests()`'s `$tests['direct']`
 * bucket (wp-admin/includes/class-wp-site-health.php), which
 * `wp-admin/site-health.php` runs server-side during THIS PAGE'S OWN PHP
 * render (via a loopback `wp_remote_get()` to the probed REST route) and
 * hands to the page's JS as an already-resolved result. There is no
 * `/wp-json/wp-site-health/v1/tests/rest-availability` REST route for it --
 * `WP_REST_Site_Health_Controller::register_routes()` only registers routes
 * for the true async tests (background-updates, loopback-requests,
 * https-status, dotorg-communication, authorization-header, page-cache).
 * So the brief's suggested fallback (asserting via that REST endpoint) does
 * not apply to this specific test; asserting on the rendered accordion
 * instead, with a generous timeout to absorb the loopback request's latency.
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override -- both screens
 * require capabilities (`view_site_health_checks`, `edit_posts`) only the
 * project default (administrator) is guaranteed to have.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { adminUrl } from '../../config/admin';
import { pluginStrings } from '../../config/strings';

test.describe( 'admin: site health (default state)', () => {
	test( 'the REST API check passes', async ( { admin, page } ) => {
		// The PHP-side loopback request this page performs before it ever
		// responds can be slow, so this test's navigation and assertions get a
		// generous allowance rather than the suite default.
		test.setTimeout( 90_000 );

		await admin.visitAdminPage( 'site-health.php' );

		// A selector string, not a pre-built Locator -- reused below scoped to
		// three different ancestor panels, and Locator.locator() takes a
		// selector, not another Locator.
		const restAvailabilitySelector =
			'button[aria-controls="health-check-accordion-block-rest_availability"]';

		// Rendered by site-health.js as soon as health_check_site_status.direct
		// is available -- generous timeout to absorb the PHP-side loopback
		// latency that has to finish before that variable exists at all.
		await expect( page.locator( restAvailabilitySelector ) ).toHaveCount( 1, {
			timeout: 45_000,
		} );

		// "Good" (passed) tests are collapsed behind a "Passed tests"
		// disclosure and, unlike the critical/recommended panels, are never
		// auto-unhidden by site-health.js -- open it before asserting where the
		// result landed.
		await page.getByRole( 'button', { name: 'Passed tests' } ).click();

		await expect(
			page.locator( '#health-check-issues-good' ).locator( restAvailabilitySelector )
		).toBeVisible();

		// Controls: the result must not have landed in the critical or
		// recommended panel instead -- i.e. this plugin's swapped-in
		// wp/v2/types/page probe is genuinely passing, not merely present
		// somewhere on the page.
		await expect(
			page.locator( '#health-check-issues-critical' ).locator( restAvailabilitySelector )
		).toHaveCount( 0 );
		await expect(
			page
				.locator( '#health-check-issues-recommended' )
				.locator( restAvailabilitySelector )
		).toHaveCount( 0 );
	} );

	test( 'Press This is disabled', async ( { request, requestUtils } ) => {
		const strings = await pluginStrings( requestUtils );

		const response = await request.get( adminUrl( 'press-this.php' ), { maxRedirects: 0 } );

		// disable_press_this() also calls wp_die() with no $args, so this is a
		// 500 for the same reason documented in plugins-screen.spec.ts's
		// capability-gate test -- verified directly against
		// wp-includes/functions.php, not assumed.
		expect( response.status() ).toBe( 500 );

		const body = await response.text();
		expect( body ).toContain( strings.press_this_disabled );
	} );
} );
