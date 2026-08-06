/**
 * Role-conditional consequences of `redirect_admin_pages()` and
 * `remove_admin_bar_links()`, exercised via per-role `storageState`s.
 *
 * `redirect_admin_edit()` sends `edit.php` to `edit.php?post_type=page`
 * unconditionally, regardless of capability, so an Author (who lacks
 * `edit_pages`) lands on core's own 403 permission-error page — a real,
 * intentional side effect of the plugin's design, not a defect.
 *
 * The anonymous-role test uses `maxRedirects: 0` to inspect core's own
 * `auth_redirect()` 302, which fires before the plugin's redirect gets a
 * chance to run; the others let requests follow redirects by default.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { adminUrl, ADMIN_BAR_NEW_POST } from '../../config/admin';
import { storageStatePath } from '../../config/roles';

// Parent "+ New" dropdown node.
const ADMIN_BAR_NEW_CONTENT = '#wp-admin-bar-new-content';

// The toolbar itself, used only as the "still logged in" control.
const WP_ADMIN_BAR = '#wpadminbar';

test.describe( 'roles: author', () => {
	test.use( { storageState: storageStatePath( 'author' ) } );

	test( 'an author hitting the posts list lands on a permission error', async ( {
		request,
	} ) => {
		const response = await request.get( adminUrl( 'edit.php' ) );
		const body = await response.text();

		expect( body ).toContain( 'You need a higher level of permission.' );
		expect( body ).toContain( 'Sorry, you are not allowed to edit posts in this post type.' );

		expect( response.status() ).toBe( 403 );
	} );
} );

test.describe( 'roles: editor', () => {
	test.use( { storageState: storageStatePath( 'editor' ) } );

	test( 'an editor hitting the posts list reaches the pages list', async ( { request } ) => {
		// Control for the author test above: Editor has edit_pages.
		const response = await request.get( adminUrl( 'edit.php' ) );
		const body = await response.text();

		expect( response.url() ).toContain( 'post_type=page' );
		expect( body ).toContain( 'wp-list-table' );
		expect( body ).toContain( 'id="the-list"' );

		expect( response.status() ).toBe( 200 );
	} );
} );

test.describe( 'roles: subscriber', () => {
	test.use( { storageState: storageStatePath( 'subscriber' ) } );

	test( 'a subscriber sees no content-creation nodes in the front-end admin bar', async ( {
		page,
	} ) => {
		await page.goto( '/' );

		// A Subscriber qualifies for no post type ('post' hidden entirely;
		// 'page' needs edit_pages/publish_pages), so core's own
		// wp_admin_bar_new_content_menu() omits the "+ New" parent node
		// entirely, unlike the admin/editor case where only 'new-post' is removed.
		await expect( page.locator( ADMIN_BAR_NEW_POST ) ).toHaveCount( 0 );
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toHaveCount( 0 );

		// Confirms the toolbar itself rendered, so the counts above aren't vacuous.
		await expect( page.locator( WP_ADMIN_BAR ) ).toBeVisible();
	} );
} );

test.describe( 'roles: anonymous', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'an anonymous request to the posts list is sent to wp-login', async ( { request } ) => {
		const response = await request.get( adminUrl( 'edit.php' ), { maxRedirects: 0 } );
		const location = response.headers()[ 'location' ] ?? null;

		// Not an exact-match: auth_redirect() appends a redirect_to query arg
		// this test doesn't pin down — only that core's login gate answered.
		expect(
			location,
			`Expected a redirect to wp-login.php, got status ${ response.status() } with ` +
				`Location: ${ location ?? '(none)' }`
		).toContain( 'wp-login.php' );

		expect( response.status() ).toBe( 302 );
	} );
} );
