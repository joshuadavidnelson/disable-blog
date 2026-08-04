/**
 * Settings screens (Reading, Writing, Permalinks) and their notices, default
 * plugin state.
 *
 * COVERAGE:
 *  - `admin_body_class()` appends ` disabled-blog` whenever `has_front_page()`
 *    is true; every CSS-driven hide rule in `disable-blog-admin.css` is
 *    scoped under that class.
 *  - That stylesheet hides rows 2-4 of options-reading.php's first
 *    `.form-table` (posts-per-page/RSS rows); row 1, the front-page chooser,
 *    is deliberately excluded.
 *  - `admin_notices()`, scoped to the `plugins`/`options-reading`/`edit`
 *    screens, shows a "no front page" notice when `has_front_page()` is
 *    false, and a "front page equals posts page" notice on
 *    options-reading.php when `page_for_posts === page_on_front`.
 *  - `disable-blog-admin.js`'s `options-writing` case hides the Default Post
 *    Format row always, and Default Post Category when
 *    `dwpb.categoriesSupported` is false; Update Services and Post-via-email
 *    are instead removed server-side (filtered to `__return_false` in
 *    `class-disable-blog.php`), so no markup renders for them at all.
 *  - `available_permalink_structure_tags()` drops the `%category%` button
 *    when `dwpb_post_types_with_tax( 'category' )` is false, and would drop
 *    `%author%` when `dwpb_disable_author_archives()` is true.
 *
 * Default-state gating: `category`/`post_tag` are used only by the disabled
 * `post` type, so `%category%` is gone (test 8); `dwpb_disable_author_archives`
 * defaults false, so `%author%` stays (test 8's control).
 *
 * Tests 3-4 mutate `page_for_posts`/`show_on_front` via `setReadingSettings()`
 * (the `dwpb-test/v1/reading-settings` route, not core's `/wp/v2/settings` —
 * see `config/seed.ts`) and restore the previous value in an in-test
 * `finally`, not a shared `afterAll`, so one failure can't strand the
 * setting for later tests.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import type { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { noticeWith } from '../../config/admin';
import { setReadingSettings, siteConfig } from '../../config/seed';
import type { ReadingSettings } from '../../config/seed';
import { pluginStrings } from '../../config/strings';

/**
 * Row-of-a-`.form-table` locator, matched by the `<label for="...">` inside
 * it rather than table position — more resilient than mirroring the CSS's
 * `tr:nth-child()` selectors verbatim.
 *
 * @param page      Page under test.
 * @param labelFor  The `for` attribute of a `<label>` inside the target row.
 */
function formTableRow( page: Page, labelFor: string ) {
	return page.locator( 'tr', { has: page.locator( `label[for="${ labelFor }"]` ) } );
}

test.describe( 'admin: settings screens (default state)', () => {
	test( 'the reading screen carries the plugin body class', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'options-reading.php' );

		await expect( page.locator( 'body' ) ).toHaveClass( /(^|\s)disabled-blog(\s|$)/ );
	} );

	test( 'the reading screen hides the blog-related rows', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'options-reading.php' );

		// CSS-driven (display: none), so toBeHidden() rather than toHaveCount( 0 ).
		await expect( formTableRow( page, 'posts_per_page' ) ).toBeHidden();
		await expect( formTableRow( page, 'posts_per_rss' ) ).toBeHidden();

		// rss_use_excerpt's row has no <label for>, it uses a <legend> instead
		// -- matched by its radio inputs instead.
		const rssUseExcerptRow = page.locator( 'tr', {
			has: page.locator( 'input[name="rss_use_excerpt"]' ),
		} );
		await expect( rssUseExcerptRow ).toBeHidden();

		// Control: the front-page chooser row is excluded from the hide rule.
		await expect( page.locator( '#front-static-pages' ) ).toBeVisible();
	} );

	test( 'the front-page-equals-posts-page notice appears', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const config = await siteConfig( requestUtils );

		let previous: ReadingSettings | undefined;

		try {
			( { previous } = await setReadingSettings( requestUtils, {
				pageForPosts: config.homeId,
			} ) );

			await admin.visitAdminPage( 'options-reading.php' );

			const strings = await pluginStrings( requestUtils );
			await expect( noticeWith( page, strings.front_equals_posts_notice ) ).toBeVisible();
		} finally {
			// Restore the exact prior value rather than re-running setupSite().
			if ( previous ) {
				await setReadingSettings( requestUtils, {
					pageForPosts: previous.pageForPosts,
				} );
			}
		}
	} );

	test( 'the no-front-page notice appears on the plugins screen', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		let previous: ReadingSettings | undefined;

		try {
			( { previous } = await setReadingSettings( requestUtils, {
				showOnFront: 'posts',
			} ) );

			await admin.visitAdminPage( 'plugins.php' );

			const strings = await pluginStrings( requestUtils );
			await expect( noticeWith( page, strings.no_front_page_notice ) ).toBeVisible();
		} finally {
			if ( previous ) {
				await setReadingSettings( requestUtils, {
					showOnFront: previous.showOnFront,
				} );
			}
		}
	} );

	// On modern WordPress, options-writing.php's Formatting and Link Manager
	// sections no longer exist, so the only surviving `label[for=...]`
	// elements are `default_category`/`default_post_format` — both hidden by
	// the plugin. The controls below assert the heading instead of a
	// neighbouring form-table row.

	test( 'the writing screen hides the post format row', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'options-writing.php' );

		await expect( formTableRow( page, 'default_post_format' ) ).toBeHidden();

		await expect( page.getByRole( 'heading', { name: 'Writing Settings' } ) ).toBeVisible();
	} );

	test( 'the writing screen hides the default category row', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'options-writing.php' );

		await expect( formTableRow( page, 'default_category' ) ).toBeHidden();

		await expect( page.getByRole( 'heading', { name: 'Writing Settings' } ) ).toBeVisible();
	} );

	test( 'the writing screen drops update services and post-via-email', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'options-writing.php' );

		// Removed server-side, not just hidden -- toHaveCount( 0 ), not toBeHidden().
		await expect( page.locator( '#ping_sites' ) ).toHaveCount( 0 );
		await expect( page.locator( '#mailserver_url' ) ).toHaveCount( 0 );

		await expect( page.getByRole( 'heading', { name: 'Writing Settings' } ) ).toBeVisible();
	} );

	test( 'the permalinks screen drops the category structure tag but keeps author', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'options-permalink.php' );

		// Scoped to .available-structure-tags -- the Help tab also mentions
		// "%category%" in prose, which an unscoped match would also hit.
		const availableTags = page.locator( '.available-structure-tags' );
		const categoryButton = availableTags.locator( 'button', { hasText: '%category%' } );
		const authorButton = availableTags.locator( 'button', { hasText: '%author%' } );

		await expect( categoryButton ).toHaveCount( 0 );

		// Control: author archives stay enabled by default.
		await expect( authorButton ).toBeVisible();
	} );
} );
