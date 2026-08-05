/**
 * `Disable_Blog_Integrations`'s third-party detection, via the
 * `dwpb-test-integrations.php` mu-plugin fixture. Neither Disable Comments
 * nor WooCommerce is installed in this environment, so the fixture stubs
 * exactly the signal each detector checks: a `Disable_Comments` class for
 * `is_disable_comments_active()`'s `class_exists()` fallback, and a `WC()`
 * function plus a pre-2.6.3 `WC_VERSION` constant for
 * `is_woocommerce_active()`'s `function_exists()` fallback and
 * `woocommerce_version_check()`.
 *
 * OBSERVABILITY: `is_disable_comments_active()` reaching `true` makes
 * `plugin_integrations()` force `dwpb_post_types_supporting_comments` false,
 * observable the same way `filters/comments-unsupported.spec.ts` observes it
 * (Comments menu, edit-comments.php redirect). `is_woocommerce_active()`
 * reaching `true` only adds `filter_woocommerce_comment_count()` to
 * `wp_count_comments`, which casts the site-wide count back to an array on
 * an old WooCommerce -- a type WordPress core itself never returns and
 * nothing in this stubbed environment consumes, so `wp-count-comments-type`
 * (`config/integrations.ts`) is the only way to see it happen at all.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig } from '../../config/seed';
import { MENU_COMMENTS, MENU_PAGES, adminUrl } from '../../config/admin';
import { expectRedirect, expectStatus } from '../../config/redirects';
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';
import { wpCountCommentsType } from '../../config/integrations';

test.describe( 'filters: Disable Comments integration (is_disable_comments_active)', () => {
	let dashboardTarget: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		dashboardTarget = `${ config.homeUrl }${ adminUrl( 'index.php' ) }`;

		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.integrationsDisableComments ]: true,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.integrationsDisableComments ] );
	} );

	test( 'the Comments menu is removed', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		await expect( page.locator( MENU_COMMENTS ) ).toHaveCount( 0 );
		// Control: proves the menu removal is a targeted, comments-only branch,
		// not the whole admin menu failing to render.
		await expect( page.locator( MENU_PAGES ) ).toBeVisible();
	} );

	test( 'edit-comments.php redirects to the dashboard', async ( { request } ) => {
		await expectRedirect( request, adminUrl( 'edit-comments.php' ), dashboardTarget, 301 );
	} );

	test( 'toggling the stub off restores the screen', async ( { request, requestUtils } ) => {
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.integrationsDisableComments ] );

		// Non-vacuous control: the redirect above only happens while
		// is_disable_comments_active() reports true because the stub class
		// exists -- it stops the moment the class stops existing.
		await expectStatus( request, adminUrl( 'edit-comments.php' ), 200 );
	} );
} );

test.describe( 'filters: WooCommerce integration (filter_woocommerce_comment_count)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.integrationsWoocommerce ]: true,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.integrationsWoocommerce ] );
	} );

	test( 'wp_count_comments( 0 ) is cast back into an array', async ( { requestUtils } ) => {
		expect( await wpCountCommentsType( requestUtils ) ).toBe( 'array' );
	} );

	test( 'toggling the stub off returns the type WordPress core itself always produces', async ( {
		requestUtils,
	} ) => {
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.integrationsWoocommerce ] );

		// Non-vacuous control: proves the array above is this filter's doing,
		// not something every request returns regardless of WooCommerce.
		expect( await wpCountCommentsType( requestUtils ) ).toBe( 'object' );
	} );
} );
