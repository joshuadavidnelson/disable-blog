/**
 * The author-archive filter pair (`dwpb_disable_author_archives`,
 * `dwpb_author_archive_post_types`), toggled via `setFilterOverrides()`.
 * `dwpb_disable_author_archives` flips the author-archive redirect, the
 * users-screen "view" row action, and the `%author%` permalink tag.
 * `dwpb_author_archive_post_types` (paired with `cptEnabled`) scopes the
 * archive query to the 'news' CPT and re-enables the users sitemap.
 *
 * `wp_author_sitemaps()` disables the users sitemap when EITHER condition is
 * true; the CPT-backed describe block deliberately leaves
 * `dwpb_disable_author_archives` unset so its sitemap test isolates the
 * post-types-empty condition specifically.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { userRowLocator } from '../../config/admin';
import { siteConfig, uniqueTitle } from '../../config/seed';
import { createContentTracker } from '../../config/content-tracker';
import { expectRedirect } from '../../config/redirects';
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

test.describe( 'author archives: disabled (dwpb_disable_author_archives)', () => {
	test.describe( 'front end', () => {
		test.use( { storageState: { cookies: [], origins: [] } } );

		let frontPageUrl: string;

		test.beforeAll( async ( { requestUtils } ) => {
			const config = await siteConfig( requestUtils );
			frontPageUrl = config.frontPageUrl;

			await setFilterOverrides( requestUtils, {
				dwpb_disable_author_archives: true,
			} );
		} );

		test.afterAll( async ( { requestUtils } ) => {
			await resetFilterOverrides( requestUtils );
		} );

		test( 'author archives redirect when disabled', async ( { request } ) => {
			await expectRedirect( request, '/author/admin/', frontPageUrl );
		} );
	} );

	test.describe( 'admin screens', () => {
		test.beforeAll( async ( { requestUtils } ) => {
			await setFilterOverrides( requestUtils, {
				dwpb_disable_author_archives: true,
			} );
		} );

		test.afterAll( async ( { requestUtils } ) => {
			await resetFilterOverrides( requestUtils );
		} );

		test( 'the users screen drops the View row action', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			const me = await requestUtils.rest< { id: number } >( { path: '/wp/v2/users/me' } );

			await admin.visitAdminPage( 'users.php' );

			const row = userRowLocator( page, me.id );

			// Row actions are hidden until hover, so assert DOM presence, not visibility.
			await expect( row.locator( '.row-actions .view a' ) ).toHaveCount( 0 );
			await expect( row.locator( '.row-actions .edit a' ) ).toHaveCount( 1 );
		} );

		test( 'the permalinks screen drops the %author% tag', async ( { admin, page } ) => {
			await admin.visitAdminPage( 'options-permalink.php' );

			// Scoped to the "Available tags" buttons: the Help tab also
			// mentions "%author%" in prose.
			const availableTags = page.locator( '.available-structure-tags' );
			const authorButton = availableTags.locator( 'button', { hasText: '%author%' } );

			await expect( authorButton ).toHaveCount( 0 );
			await expect(
				page.getByRole( 'heading', { name: 'Permalink Settings' } )
			).toBeVisible();
		} );
	} );
} );

test.describe( 'author archives: CPT-backed (dwpb_author_archive_post_types)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.cptEnabled ]: true,
		} );
		await setFilterOverrides( requestUtils, {
			dwpb_author_archive_post_types: { set: [ 'news' ] },
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await content.cleanup( requestUtils );

		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.cptEnabled ] );
		await resetFilterOverrides( requestUtils );
	} );

	test( 'author archives serve CPT content when a post type opts in', async ( {
		request,
		requestUtils,
	} ) => {
		// Both authored by the default API user ('admin'), matching /author/admin/ below.
		const post = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'author archive post' ),
		} );

		const newsItem = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'author archive news' ),
			postType: 'news',
		} );

		const response = await request.get( '/author/admin/' );

		expect( response.status() ).toBe( 200 );

		const body = await response.text();
		expect( body ).toContain( newsItem.title );
		expect( body ).not.toContain( post.title );
	} );

	test( 'the users sitemap returns when author archives are backed by a CPT', async ( {
		request,
	} ) => {
		const response = await request.get( '/wp-sitemap.xml' );
		const body = await response.text();

		expect( body ).toContain( 'wp-sitemap-users-1.xml' );
	} );
} );
