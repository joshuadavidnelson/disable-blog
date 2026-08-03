/**
 * Settings screens (Reading, Writing, Permalinks) and their notices, default
 * plugin state.
 *
 * COVERAGE:
 *  - `Disable_Blog_Admin::admin_body_class()`, hooked on `admin_body_class`,
 *    appends ` disabled-blog` whenever `has_front_page()` is true. That class
 *    is what every CSS-driven hide rule in `disable-blog-admin.css` is scoped
 *    under.
 *  - `disable-blog-admin.css` hides rows 2-4 of options-reading.php's first
 *    `.form-table` (`Blog pages show at most`, `Syndication feeds show the
 *    most recent`, `For each post in a feed, include`) unconditionally
 *    whenever `.disabled-blog` is present — row 1 (the front-page chooser,
 *    `#front-static-pages`) is deliberately left out of that rule.
 *  - `Disable_Blog_Admin::admin_notices()`, hooked on `admin_notices` and
 *    scoped to the `plugins`, `options-reading`, and `edit` screens only,
 *    throws the "no front page" notice whenever `has_front_page()` is false,
 *    and the "front page equals posts page" notice on options-reading.php
 *    specifically whenever `page_for_posts === page_on_front`.
 *  - `disable-blog-admin.js` (`options-writing` case), run unconditionally on
 *    `DOMContentLoaded`, hides the Default Post Format row always, and the
 *    Default Post Category row whenever `dwpb.categoriesSupported` is false.
 *  - `add_filter( 'enable_update_services_configuration', '__return_false' )`
 *    and `add_filter( 'enable_post_by_email_configuration', '__return_false' )`
 *    (registered in `class-disable-blog.php`, not `class-disable-blog-admin.php`)
 *    remove the Update Services and Post via e-mail sections of
 *    options-writing.php server-side — no markup is rendered for either at
 *    all, unlike the JS-hidden rows above.
 *  - `Disable_Blog_Admin::available_permalink_structure_tags()`, hooked on
 *    `available_permalink_structure_tags`, drops the `%category%` structure
 *    tag button from options-permalink.php whenever
 *    `dwpb_post_types_with_tax( 'category' )` is false, and would drop
 *    `%author%` whenever `dwpb_disable_author_archives()` is true.
 *
 * GATING CONDITIONS THAT SHAPE THIS SPEC's DEFAULT STATE:
 *  - `category`/`post_tag` are, by default, used only by the disabled `post`
 *    post type, so `dwpb_post_types_with_tax( 'category' )` is false and the
 *    `%category%` button is gone (test 8).
 *  - `dwpb_disable_author_archives` defaults to `false`, so the `%author%`
 *    button stays (test 8, the control).
 *
 * MUTATING TESTS: tests 3 and 4 change `page_for_posts` / `show_on_front` via
 * core REST to provoke the two admin_notices() branches. Each reads the prior
 * value first and restores it in a `finally` INSIDE the test — not a shared
 * `afterAll` — so one test's failure can never strand the reading settings
 * for every test that runs after it in this file (or any later spec file,
 * since the whole suite shares one wp-env site and `workers: 1`).
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override — every screen here
 * requires `manage_options`, which only the project default (administrator)
 * has.
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
import { pluginStrings } from '../../config/strings';

/**
 * Row-of-a-`.form-table` locator, matched by the `<label for="...">` inside
 * it rather than table position.
 *
 * More resilient than mirroring the CSS's `tr:nth-child()` selectors
 * verbatim (see the file docblock: those target rows 2-4 of the FIRST
 * `.form-table` on options-reading.php) while still proving exactly the same
 * behaviour — this environment always renders that table with the front-page
 * chooser as row 1, since `siteConfig()`/`setupSite()` guarantee at least one
 * page exists (the `! get_pages()` branch that would change the table
 * structure entirely never applies here).
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

		// has_front_page() is true for the whole suite (see setupSite()), so
		// the class is unconditionally present here.
		await expect( page.locator( 'body' ) ).toHaveClass( /(^|\s)disabled-blog(\s|$)/ );
	} );

	test( 'the reading screen hides the blog-related rows', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'options-reading.php' );

		// CSS-driven (disable-blog-admin.css), not JS — the rows are rendered,
		// just display: none, so toBeHidden() is correct here, not toHaveCount( 0 ).
		await expect( formTableRow( page, 'posts_per_page' ) ).toBeHidden();
		await expect( formTableRow( page, 'posts_per_rss' ) ).toBeHidden();

		// rss_use_excerpt's row has no <label for>, it uses a <legend> instead
		// (see wp-admin/options-reading.php) -- match it by its distinctive
		// radio inputs instead.
		const rssUseExcerptRow = page.locator( 'tr', {
			has: page.locator( 'input[name="rss_use_excerpt"]' ),
		} );
		await expect( rssUseExcerptRow ).toBeHidden();

		// Control: row 1, the front-page chooser, is deliberately excluded from
		// the CSS rule (see disable-blog-admin.css) and must stay visible.
		await expect( page.locator( '#front-static-pages' ) ).toBeVisible();
	} );

	test( 'the front-page-equals-posts-page notice appears', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const settings = await requestUtils.rest< {
			page_on_front: number;
			page_for_posts: number;
		} >( { path: '/wp/v2/settings' } );

		const originalPageForPosts = settings.page_for_posts;

		try {
			await requestUtils.rest( {
				method: 'PUT',
				path: '/wp/v2/settings',
				data: { page_for_posts: settings.page_on_front },
			} );

			await admin.visitAdminPage( 'options-reading.php' );

			const strings = await pluginStrings( requestUtils );
			await expect( noticeWith( page, strings.front_equals_posts_notice ) ).toBeVisible();
		} finally {
			// Restore the exact prior value rather than re-running setupSite():
			// a surgical restore can't accidentally mask a real regression in
			// setupSite() itself with a different one of its own side effects.
			await requestUtils.rest( {
				method: 'PUT',
				path: '/wp/v2/settings',
				data: { page_for_posts: originalPageForPosts },
			} );
		}
	} );

	test( 'the no-front-page notice appears on the plugins screen', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const settings = await requestUtils.rest< { show_on_front: string } >( {
			path: '/wp/v2/settings',
		} );

		const originalShowOnFront = settings.show_on_front;

		try {
			await requestUtils.rest( {
				method: 'PUT',
				path: '/wp/v2/settings',
				data: { show_on_front: 'posts' },
			} );

			await admin.visitAdminPage( 'plugins.php' );

			const strings = await pluginStrings( requestUtils );
			await expect( noticeWith( page, strings.no_front_page_notice ) ).toBeVisible();
		} finally {
			await requestUtils.rest( {
				method: 'PUT',
				path: '/wp/v2/settings',
				data: { show_on_front: originalShowOnFront },
			} );
		}
	} );

	// WRITING SCREEN CONTROLS, READ BEFORE TOUCHING: `label[for="use_balanceTags"]`
	// (Formatting options) and `label[for="default_link_category"]` (Link
	// Manager) do NOT exist on modern WordPress -- both sections were removed
	// from wp-admin/options-writing.php years ago. Verified directly against
	// /wp-admin/options-writing.php on this suite's core install: with the
	// plugin active, the only `label[for=...]` elements left on the whole
	// screen are `default_category` and `default_post_format` -- and the
	// plugin hides both of those via JS. So once the plugin is active this
	// screen is left with essentially nothing but the `Writing Settings`
	// heading, the two hidden rows, and the Save button -- there is no
	// "richer" neighbouring control to restore here. The tests below prove
	// the screen rendered via the heading instead.

	test( 'the writing screen hides the post format row', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'options-writing.php' );

		await expect( formTableRow( page, 'default_post_format' ) ).toBeHidden();

		// Control: proves the screen as a whole still rendered rather than
		// everything on it being hidden (see the block comment above for why
		// this isn't a neighbouring form-table row).
		await expect( page.getByRole( 'heading', { name: 'Writing Settings' } ) ).toBeVisible();
	} );

	test( 'the writing screen hides the default category row', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'options-writing.php' );

		// Gated on dwpb.categoriesSupported: category is, by default, used only
		// by the disabled 'post' post type, so this is false and the row hides.
		await expect( formTableRow( page, 'default_category' ) ).toBeHidden();

		// Control: proves the screen as a whole still rendered (see the block
		// comment above -- the Link Manager's Default Link Category field this
		// used to check against no longer exists on modern WordPress).
		await expect( page.getByRole( 'heading', { name: 'Writing Settings' } ) ).toBeVisible();
	} );

	test( 'the writing screen drops update services and post-via-email', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'options-writing.php' );

		// Server-side removal (enable_update_services_configuration /
		// enable_post_by_email_configuration both filtered to __return_false in
		// class-disable-blog.php), so these fields are entirely absent from the
		// markup -- toHaveCount( 0 ), not toBeHidden().
		await expect( page.locator( '#ping_sites' ) ).toHaveCount( 0 );
		await expect( page.locator( '#mailserver_url' ) ).toHaveCount( 0 );

		// Control: the Writing Settings form itself still rendered (see the
		// block comment above for why this isn't a neighbouring form-table row).
		await expect( page.getByRole( 'heading', { name: 'Writing Settings' } ) ).toBeVisible();
	} );

	test( 'the permalinks screen drops the category structure tag but keeps author', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'options-permalink.php' );

		// Scoped to the "Available tags" buttons specifically
		// (.available-structure-tags) -- the Permalinks screen's own contextual
		// Help tab also mentions "%category%" in prose (see
		// wp-admin/options-permalink.php's help-tab content), so an unscoped
		// text/name match would find that stray DOM node and pass for the wrong
		// reason even with the button correctly removed.
		const availableTags = page.locator( '.available-structure-tags' );
		const categoryButton = availableTags.locator( 'button', { hasText: '%category%' } );
		const authorButton = availableTags.locator( 'button', { hasText: '%author%' } );

		await expect( categoryButton ).toHaveCount( 0 );

		// Control: dwpb_disable_author_archives defaults to false, so
		// available_permalink_structure_tags() never touches the author tag.
		await expect( authorButton ).toBeVisible();
	} );
} );
