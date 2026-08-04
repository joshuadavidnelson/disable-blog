/**
 * Redirect filters (`dwpb_redirect_front_end`, `dwpb_redirect_status_code`,
 * `dwpb_pass_query_string_on_redirect` + `dwpb_allowed_query_vars`,
 * `dwpb_front_end_redirect_url`), toggled via the `dwpb-test-redirects.php`
 * mu-plugin fixture rather than plugin defaults — see that file's docblock
 * for the option -> filter map. Assertions run request-layer, same reasoning
 * as `redirects.spec.ts`; the describe block is anonymous, requestUtils stays
 * admin-authenticated for seeding/fixture toggling.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, seedPost, deletePosts, uniqueTitle } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { expectRedirect, expectStatus } from '../../config/redirects';
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

// Must match DWPB_TEST_REDIRECT_LANDING_PATH in dwpb-test-redirects.php.
const LANDING_PATH = '/dwpb-test-landing/';

// Reset together in afterEach so a failing assertion can't leak a fixture.
const TOGGLES_USED = [
	FIXTURE_TOGGLES.frontEndRedirectsOff,
	FIXTURE_TOGGLES.redirectStatusCode,
	FIXTURE_TOGGLES.queryStringPassthrough,
	FIXTURE_TOGGLES.customRedirectUrl,
];

test.describe( 'frontend: redirect mechanics (fixture-driven)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let frontPageUrl: string;
	let homeUrl: string;
	let seededPost: SeededPost;

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		frontPageUrl = config.frontPageUrl;
		homeUrl = config.homeUrl;

		seededPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'redirect mechanics post' ),
		} );
		seededIds.push( seededPost.id );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetFixtures( requestUtils, TOGGLES_USED );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await deletePosts( requestUtils, seededIds );
	} );

	test( 'the front-end kill switch disables redirects', async ( {
		request,
		requestUtils,
	} ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.frontEndRedirectsOff ]: true,
		} );

		// Confirms the page actually rendered, not merely that the redirect vanished.
		await expectStatus( request, seededPost.permalink, 200 );

		const response = await request.get( seededPost.permalink );
		const body = await response.text();

		expect( body ).toContain( seededPost.title );
	} );

	test( 'the status code filter is honored', async ( { request, requestUtils } ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.redirectStatusCode ]: 302,
		} );

		await expectRedirect( request, seededPost.permalink, frontPageUrl, 302 );
	} );

	test( 'an out-of-range status code clamps back to 301', async ( {
		request,
		requestUtils,
	} ) => {
		// 200 is outside the 300-399 range get_redirect_status_code() allows,
		// so it clamps back to 301 rather than passing 200 to wp_safe_redirect().
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.redirectStatusCode ]: 200,
		} );

		await expectRedirect( request, seededPost.permalink, frontPageUrl, 301 );
	} );

	test( 'query-string passthrough honors the allowlist', async ( {
		request,
		requestUtils,
	} ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.queryStringPassthrough ]: true,
		} );

		// Fixture allow-lists only 'utm_source'; 'evil' must be dropped.
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
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.customRedirectUrl ]: true,
		} );

		// homeUrl carries no trailing slash, so the target is a plain concatenation.
		await expectRedirect( request, seededPost.permalink, `${ homeUrl }${ LANDING_PATH }` );
	} );
} );
