/**
 * Site Health's REST Availability check, and Press This, default plugin
 * state.
 *
 * `site_status_tests()` runs core's own `rest_availability` test with its
 * `wp/v2/types/post` probe rewritten to `wp/v2/types/page`, since the 'post'
 * route is unreachable under this plugin (see `rest-xmlrpc/rest-api.spec.ts`)
 * and would otherwise fail this check on every install. The description
 * assertion proves core's wording is shown rather than a copy of it.
 *
 * `rest_availability` is a 'direct' Site Health test, run server-side during
 * `site-health.php`'s own PHP render (via a loopback request) with no REST
 * route of its own — asserted here on the rendered accordion instead, with a
 * generous timeout for the loopback latency.
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
import { coreVersionAtLeast } from '../../config/wp-cli';

test.describe( 'admin: site health (default state)', () => {
	test( 'the REST API check passes', async ( { admin, page } ) => {
		// The PHP-side loopback request this page performs can be slow.
		test.setTimeout( 90_000 );

		await admin.visitAdminPage( 'site-health.php' );

		const restAvailabilitySelector =
			'button[aria-controls="health-check-accordion-block-rest_availability"]';

		await expect( page.locator( restAvailabilitySelector ) ).toHaveCount( 1, {
			timeout: 45_000,
		} );

		// "Passed tests" is collapsed by default; open it before asserting.
		await page.getByRole( 'button', { name: 'Passed tests' } ).click();

		await expect(
			page.locator( '#health-check-issues-good' ).locator( restAvailabilitySelector )
		).toBeVisible();

		// Controls: the result must not have landed in the critical or
		// recommended panel instead.
		await expect(
			page.locator( '#health-check-issues-critical' ).locator( restAvailabilitySelector )
		).toHaveCount( 0 );
		await expect(
			page
				.locator( '#health-check-issues-recommended' )
				.locator( restAvailabilitySelector )
		).toHaveCount( 0 );

		// Core's description took this wording in 6.1.
		if ( await coreVersionAtLeast( '6.1' ) ) {
			await page.locator( restAvailabilitySelector ).click();

			await expect(
				page.locator( '#health-check-accordion-block-rest_availability' )
			).toContainText( 'For example, the block editor screen relies on the REST API' );
		}
	} );

	test( 'Press This is disabled', async ( { request, requestUtils } ) => {
		const strings = await pluginStrings( requestUtils );

		const response = await request.get( adminUrl( 'press-this.php' ), { maxRedirects: 0 } );

		// wp_die() with no $args defaults to a 500 (see filters/feed-message.spec.ts).
		expect( response.status() ).toBe( 500 );

		const body = await response.text();
		expect( body ).toContain( strings.press_this_disabled );
	} );
} );
