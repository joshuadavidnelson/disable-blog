/**
 * Role-conditional consequences of `Disable_Blog_Admin::redirect_admin_pages()`
 * and `Disable_Blog_Admin::remove_admin_bar_links()` -- no fixture toggles in
 * this file, only different per-role `storageState`s (see `config/roles.ts`).
 *
 * WHY THIS MATTERS: `redirect_admin_edit()` (includes/class-disable-blog-admin.php:330)
 * redirects `edit.php` (no/`post` post_type) to `edit.php?post_type=page`
 * unconditionally, for every role -- it never checks the current user's
 * capabilities. That is fine for an Editor or Administrator, who can both
 * manage pages, but an Author cannot (`edit_pages` is not in the Author
 * role's capability list), so the plugin's own redirect is what puts an
 * Author in front of a page they are not allowed to see. Tests 1-2 pin that
 * real, user-facing consequence down: not a defect, but a side effect of the
 * plugin's design worth regression-testing on its own.
 *
 * CORE BEHAVIOUR, VERIFIED EMPIRICALLY (NOT ASSUMED): the exact status codes
 * asserted below come from reading WordPress core itself, not from guessing:
 *  - wp-admin/edit.php:44-49 -- `wp_die( ..., 403 )` when the current user
 *    lacks `$post_type_object->cap->edit_posts` for the requested post type,
 *    with the literal body text "You need a higher level of permission." /
 *    "Sorry, you are not allowed to edit posts in this post type." (test 1).
 *  - wp-includes/pluggable.php's `auth_redirect()` -- `wp_redirect( $login_url ); exit;`
 *    with no explicit status, so core's default of `302` applies (test 4).
 *
 * ORDERING: core's `auth_redirect()` (called near the top of
 * wp-admin/admin.php, well before `set_current_screen()` fires the
 * `current_screen` hook `redirect_admin_pages()` is hooked on) always wins
 * over the plugin's own redirect for a signed-out visitor -- test 4 pins
 * that ordering down, matching `admin/admin-redirects.spec.ts`'s docblock
 * note that every admin screen 302s to `wp-login.php` before the plugin
 * gets a chance to run.
 *
 * REQUEST LAYER FOR TESTS 1, 2, 4: `request.get()` follows redirects by
 * default (no `maxRedirects: 0`), unlike every other redirect assertion in
 * this suite -- here that is deliberate, not an oversight: tests 1-2 want
 * the page AT THE END of the redirect chain, not the redirect response
 * itself, and test 4 inspects the single `Location` header from the first
 * (only) hop with `maxRedirects: 0`. The `request` fixture is scoped to
 * whatever `storageState` its `test.use()` block set, exactly like
 * `admin/admin-redirects.spec.ts`'s default-admin `request` fixture and
 * `frontend/redirect-mechanics.spec.ts`'s anonymous one.
 *
 * TEST 3 USES `page`, NOT `request`: the admin bar's DOM structure -- and
 * specifically which nodes core chooses not to render at all for a
 * capability-less role -- can only be observed in a rendered page, not a raw
 * response body.
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

/**
 * Parent "+ New" dropdown node (`#wp-admin-bar-new-content`).
 *
 * Not centralized in `config/admin.ts` -- same local-constant convention as
 * `admin/admin-bar.spec.ts` and `frontend/search-and-head.spec.ts`.
 */
const ADMIN_BAR_NEW_CONTENT = '#wp-admin-bar-new-content';

/**
 * The toolbar itself, used here only as the "still logged in" control.
 */
const WP_ADMIN_BAR = '#wpadminbar';

test.describe( 'roles: author', () => {
	test.use( { storageState: storageStatePath( 'author' ) } );

	test( 'an author hitting the posts list lands on a permission error', async ( {
		request,
	} ) => {
		// edit.php redirects (301) to edit.php?post_type=page regardless of
		// role (redirect_admin_edit() never checks capabilities); this
		// request follows that redirect (default behaviour -- maxRedirects
		// is not set to 0 here) and inspects what an Author actually lands
		// on: core's own capability check inside edit.php, not anything the
		// plugin controls directly.
		const response = await request.get( adminUrl( 'edit.php' ) );
		const body = await response.text();

		// Primary signal: the exact copy wp-admin/edit.php's wp_die() call
		// emits when the current user lacks cap->edit_posts for 'page'.
		expect( body ).toContain( 'You need a higher level of permission.' );
		expect( body ).toContain( 'Sorry, you are not allowed to edit posts in this post type.' );

		expect( response.status() ).toBe( 403 );
	} );
} );

test.describe( 'roles: editor', () => {
	test.use( { storageState: storageStatePath( 'editor' ) } );

	test( 'an editor hitting the posts list reaches the pages list', async ( { request } ) => {
		// Control for the author test above: an Editor has edit_pages, so
		// the same redirect_admin_edit() 301 lands them on a normally
		// rendered Pages list table instead of core's permission-error page.
		const response = await request.get( adminUrl( 'edit.php' ) );
		const body = await response.text();

		expect( response.url() ).toContain( 'post_type=page' );
		// Core list-table markup, stable regardless of WP version/theme
		// (wp-admin is never theme-controlled): the table's own class and
		// its (possibly empty) body container.
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

		// A Subscriber has no create_posts capability for any remaining
		// public post type ('post' is fully hidden from the admin bar by
		// modify_post_type_arguments(); 'page' still shows there, but
		// requires edit_pages/publish_pages, which Subscriber lacks). Core's
		// wp_admin_bar_new_content_menu() never adds the "+ New" parent node
		// at all when no post type qualifies -- unlike the administrator/
		// editor cases in admin/admin-bar.spec.ts, where "+ New" itself
		// stays present with only its 'new-post' child removed by the
		// plugin.
		await expect( page.locator( ADMIN_BAR_NEW_POST ) ).toHaveCount( 0 );
		await expect( page.locator( ADMIN_BAR_NEW_CONTENT ) ).toHaveCount( 0 );

		// Control: proves the toolbar itself rendered (the Subscriber is
		// logged in), so the two zero counts above aren't vacuously true
		// against a toolbar that never loaded.
		await expect( page.locator( WP_ADMIN_BAR ) ).toBeVisible();
	} );
} );

test.describe( 'roles: anonymous', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'an anonymous request to the posts list is sent to wp-login', async ( { request } ) => {
		// maxRedirects: 0 -- inspect the single redirect WordPress core
		// itself produces (auth_redirect(), fired before the plugin's own
		// current_screen-hooked redirect ever gets a chance to run), not
		// wherever a followed chain would eventually land.
		const response = await request.get( adminUrl( 'edit.php' ), { maxRedirects: 0 } );
		const location = response.headers()[ 'location' ] ?? null;

		// Not an exact-match Location assertion (unlike expectRedirect()
		// elsewhere in this suite): auth_redirect() appends a
		// `redirect_to` query arg carrying the originally-requested URL,
		// which this test deliberately does not pin down -- only that core's
		// own login gate, not the plugin, is what answered.
		expect(
			location,
			`Expected a redirect to wp-login.php, got status ${ response.status() } with ` +
				`Location: ${ location ?? '(none)' }`
		).toContain( 'wp-login.php' );

		expect( response.status() ).toBe( 302 );
	} );
} );
