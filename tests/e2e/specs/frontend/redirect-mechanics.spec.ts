/**
 * Redirect filters (`dwpb_redirect_front_end`, `dwpb_redirect_status_code`,
 * `dwpb_pass_query_string_on_redirect` + `dwpb_allowed_query_vars`,
 * `dwpb_front_end_redirect_url`), toggled via `setFilterOverrides()` rather
 * than plugin defaults. Assertions run request-layer, same reasoning as
 * `redirects.spec.ts`; the describe block is anonymous, requestUtils stays
 * admin-authenticated for seeding/override setting.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { createContentTracker } from '../../config/content-tracker';
import { expectRedirect, expectStatus } from '../../config/redirects';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

const LANDING_PATH = '/dwpb-test-landing/';

test.describe( 'frontend: redirect mechanics (override-driven)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let frontPageUrl: string;
	let homeUrl: string;
	let seededPost: SeededPost;

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		frontPageUrl = config.frontPageUrl;
		homeUrl = config.homeUrl;

		seededPost = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'redirect mechanics post' ),
		} );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await content.cleanup( requestUtils );
	} );

	test( 'the front-end kill switch disables redirects', async ( {
		request,
		requestUtils,
	} ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_redirect_front_end: false,
		} );

		// Confirms the page actually rendered, not merely that the redirect vanished.
		const response = await expectStatus( request, seededPost.permalink, 200 );
		const body = await response.text();

		expect( body ).toContain( seededPost.title );
	} );

	test( 'the status code filter is honored', async ( { request, requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_redirect_status_code: 302,
		} );

		await expectRedirect( request, seededPost.permalink, frontPageUrl, 302 );
	} );

	test( 'an out-of-range status code clamps back to 301', async ( {
		request,
		requestUtils,
	} ) => {
		// 200 is outside the 300-399 range get_redirect_status_code() allows,
		// so it clamps back to 301 rather than passing 200 to wp_safe_redirect().
		await setFilterOverrides( requestUtils, {
			dwpb_redirect_status_code: 200,
		} );

		await expectRedirect( request, seededPost.permalink, frontPageUrl, 301 );
	} );

	test( 'query-string passthrough honors the allowlist', async ( {
		request,
		requestUtils,
	} ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_pass_query_string_on_redirect: true,
			dwpb_allowed_query_vars: { set: [ 'utm_source' ] },
		} );

		// Allow-lists only 'utm_source'; 'evil' must be dropped.
		await expectRedirect(
			request,
			`${ seededPost.permalink }?utm_source=x&evil=1`,
			`${ frontPageUrl }?utm_source=x`
		);
	} );

	test( 'the custom front-end redirect URL filter is honored', async ( {
		request,
		requestUtils,
	} ) => {
		// homeUrl carries no trailing slash, so the target is a plain
		// concatenation -- matches home_url( $path ) server-side.
		const landingUrl = `${ homeUrl }${ LANDING_PATH }`;

		await setFilterOverrides( requestUtils, {
			dwpb_front_end_redirect_url: landingUrl,
		} );

		await expectRedirect( request, seededPost.permalink, landingUrl );
	} );
} );
