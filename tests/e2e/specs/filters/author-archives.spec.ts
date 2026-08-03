/**
 * The author-archive filter pair (`dwpb_disable_author_archives`,
 * `dwpb_author_archive_post_types`), exercised via the
 * `dwpb-test-author-archives.php` mu-plugin fixture rather than the plugin's
 * shipped defaults.
 *
 * COVERAGE:
 *  - `dwpb_test_author_archives_disabled` forces `dwpb_disable_author_archives`
 *    to `true`, which flips three branches that are unreachable at the
 *    plugin's default (`false`):
 *    `Disable_Blog_Public::redirect_public_pages()`'s `author_archive` entry
 *    in `$public_redirects` (test 1), `Disable_Blog_Admin::user_row_actions()`'s
 *    removal of the users-screen "view" row action (test 2), and
 *    `::available_permalink_structure_tags()`'s removal of the `%author%`
 *    structure tag (test 3).
 *  - `dwpb_test_author_archive_cpt` forces `dwpb_author_archive_post_types`
 *    to `array( 'news' )` -- paired with `dwpb-test-cpt.php`'s
 *    `dwpb_test_cpt_enabled` toggle, since 'news' is the post type that
 *    filter points at. This flips `Disable_Blog_Public::modify_query()`'s
 *    author-archive branch (which only restricts the query when
 *    `author_archive_post_types()` is non-empty, test 4) and
 *    `::wp_author_sitemaps()`'s users-sitemap gate (test 5).
 *
 * TEST 5's TWO GATING CONDITIONS: `wp_author_sitemaps()` disables the users
 * sitemap when EITHER `disable_author_archives()` is true OR
 * `author_archive_post_types()` is empty -- at the plugin's default state
 * both conditions independently disable it (see `sitemap.spec.ts`'s DEFECT
 * D5 coverage). Test 5 below only flips the second condition
 * (`author_archive_post_types()` becomes non-empty via `authorArchiveCpt`) --
 * `authorArchivesDisabled` is deliberately left off for that test, so the
 * users sitemap reappearing there proves BOTH conditions were checked, not
 * just one.
 *
 * REQUEST LAYER FOR REDIRECTS/STATUS: `expectRedirect()` from
 * `config/redirects.ts`, same reasoning as every other spec in this suite --
 * see that file's docblock.
 *
 * MIXED CONTEXT: tests 1, 4, and 5 are public-facing (a signed-out visitor's
 * author-archive/sitemap request) and run under an empty `storageState`,
 * nested under their own `describe` blocks; tests 2 and 3 are admin screens
 * (`users.php`, `options-permalink.php`) and run under the project's default
 * administrator session. The worker-scoped `requestUtils` fixture stays
 * admin-authenticated regardless of a test's `storageState` (see
 * `frontend/redirects.spec.ts`'s docblock), so fixture toggling and content
 * seeding work the same way in every block.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import type { Locator, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { siteConfig, seedPost, deletePosts, uniqueTitle } from '../../config/seed';
import { expectRedirect } from '../../config/redirects';
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

/**
 * A `users.php` list-table row, by user id.
 *
 * Not `rowLocator()` from `config/admin.ts` -- that helper is `#post-<id>`,
 * which every post-type list table renders regardless of post type, but
 * `WP_Users_List_Table::single_row()` renders `<tr id='user-<id>'>` instead.
 * Mirrors `admin/users-screen.spec.ts`'s local helper of the same shape.
 *
 * @param page   Page under test.
 * @param userId User id.
 */
function userRowLocator( page: Page, userId: number ): Locator {
	return page.locator( `#user-${ userId }` );
}

test.describe( 'author archives: disabled (dwpb_disable_author_archives)', () => {
	test.describe( 'front end', () => {
		// Public-facing behaviour -- anonymous. The worker-scoped requestUtils
		// fixture stays admin-authenticated regardless (see the file docblock).
		test.use( { storageState: { cookies: [], origins: [] } } );

		let frontPageUrl: string;

		test.beforeAll( async ( { requestUtils } ) => {
			const config = await siteConfig( requestUtils );
			frontPageUrl = config.frontPageUrl;

			await setFixtures( requestUtils, {
				[ FIXTURE_TOGGLES.authorArchivesDisabled ]: true,
			} );
		} );

		test.afterAll( async ( { requestUtils } ) => {
			await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.authorArchivesDisabled ] );
		} );

		test( 'author archives redirect when disabled', async ( { request } ) => {
			// is_author() && true === disable_author_archives() now matches,
			// so the author_archive entry in $public_redirects fires. wp-env's
			// built-in administrator has the username 'admin'.
			await expectRedirect( request, '/author/admin/', frontPageUrl );
		} );
	} );

	test.describe( 'admin screens', () => {
		// AUTHENTICATED BY DEFAULT: no storageState override -- both screens
		// below require an authenticated session.
		test.beforeAll( async ( { requestUtils } ) => {
			await setFixtures( requestUtils, {
				[ FIXTURE_TOGGLES.authorArchivesDisabled ]: true,
			} );
		} );

		test.afterAll( async ( { requestUtils } ) => {
			await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.authorArchivesDisabled ] );
		} );

		test( 'the users screen drops the View row action', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			const me = await requestUtils.rest< { id: number } >( { path: '/wp/v2/users/me' } );

			await admin.visitAdminPage( 'users.php' );

			const row = userRowLocator( page, me.id );

			// Row actions are visually hidden until hover (same house rule as
			// admin-bar.spec.ts's dropdown children) -- assert DOM presence via
			// toHaveCount(), not toBeVisible().
			await expect( row.locator( '.row-actions .view a' ) ).toHaveCount( 0 );

			// Control: the row itself still renders its other row actions --
			// user_row_actions() only ever unsets 'view', never the whole
			// $actions array.
			await expect( row.locator( '.row-actions .edit a' ) ).toHaveCount( 1 );
		} );

		test( 'the permalinks screen drops the %author% tag', async ( { admin, page } ) => {
			await admin.visitAdminPage( 'options-permalink.php' );

			// Scoped to the "Available tags" buttons specifically -- see
			// admin/settings-screens.spec.ts's identical scoping and the
			// reasoning in its comment (the Permalinks screen's own contextual
			// Help tab also mentions "%author%" in prose).
			const availableTags = page.locator( '.available-structure-tags' );
			const authorButton = availableTags.locator( 'button', { hasText: '%author%' } );

			await expect( authorButton ).toHaveCount( 0 );

			// Control: proves the Permalinks screen itself rendered, so the
			// absence above isn't vacuously true against a screen that never
			// loaded.
			await expect(
				page.getByRole( 'heading', { name: 'Permalink Settings' } )
			).toBeVisible();
		} );
	} );
} );

test.describe( 'author archives: CPT-backed (dwpb_author_archive_post_types)', () => {
	// Public-facing behaviour -- anonymous. The worker-scoped requestUtils
	// fixture stays admin-authenticated regardless (see the file docblock).
	test.use( { storageState: { cookies: [], origins: [] } } );

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.cptEnabled ]: true,
			[ FIXTURE_TOGGLES.authorArchiveCpt ]: true,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await deletePosts( requestUtils, seededIds );

		await resetFixtures( requestUtils, [
			FIXTURE_TOGGLES.cptEnabled,
			FIXTURE_TOGGLES.authorArchiveCpt,
		] );
	} );

	test( 'author archives serve CPT content when a post type opts in', async ( {
		request,
		requestUtils,
	} ) => {
		// Both authored by the API's default (the current, admin, user) --
		// wp-env's built-in administrator has the username 'admin', matching
		// the /author/admin/ URL below.
		const post = await seedPost( requestUtils, {
			title: uniqueTitle( 'author archive post' ),
		} );
		seededIds.push( post.id );

		const newsItem = await seedPost( requestUtils, {
			title: uniqueTitle( 'author archive news' ),
			postType: 'news',
		} );
		seededIds.push( newsItem.id );

		// disable_author_archives() is still false here (authorArchivesDisabled
		// is not set in this describe block), so the archive renders instead of
		// redirecting -- modify_query() then restricts it to author_archive_post_types()'s
		// result, array( 'news' ), excluding 'post' entirely.
		const response = await request.get( '/author/admin/' );

		expect( response.status() ).toBe( 200 );

		const body = await response.text();
		expect( body ).toContain( newsItem.title );
		expect( body ).not.toContain( post.title );
	} );

	test( 'the users sitemap returns when author archives are backed by a CPT', async ( {
		request,
	} ) => {
		// See the file docblock's "TEST 5's TWO GATING CONDITIONS" note:
		// authorArchivesDisabled is NOT set here, so this specifically proves
		// the author_archive_post_types()-empty condition, not the
		// disable_author_archives() one.
		const response = await request.get( '/wp-sitemap.xml' );
		const body = await response.text();

		expect( body ).toContain( 'wp-sitemap-users-1.xml' );
	} );
} );
