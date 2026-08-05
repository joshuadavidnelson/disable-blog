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
 *    screens (all three covered below), shows a "no front page" notice when
 *    `has_front_page()` is false, and a "front page equals posts page"
 *    notice on options-reading.php when `page_for_posts === page_on_front`.
 *    The "no front page" notice's link wording differs on options-reading.php
 *    (tells the user to pick a page right there) versus elsewhere (links to
 *    Reading Settings).
 *  - `disable-blog-admin.js`'s `options-writing` case hides the Default Post
 *    Format row always, and Default Post Category when
 *    `dwpb.categoriesSupported` is false; Update Services and Post-via-email
 *    are instead removed server-side (filtered to `__return_false` in
 *    `class-disable-blog.php`), so no markup renders for them at all.
 *  - That same script's `options-permalink` case hides the category_base/
 *    tag_base rows when unsupported, and collapses the "Optional" heading/
 *    description/table when both are -- a different, JS-side mechanism from
 *    `available_permalink_structure_tags()` below, which is PHP-side and
 *    covers the Custom Structure tag buttons instead.
 *  - `available_permalink_structure_tags()` drops the `%category%` button
 *    when `dwpb_post_types_with_tax( 'category' )` is false, and would drop
 *    `%author%` when `dwpb_disable_author_archives()` is true.
 *
 * Default-state gating: `category`/`post_tag` are used only by the disabled
 * `post` type, so `%category%` is gone (and the permalinks-JS rows are
 * hidden); `dwpb_disable_author_archives` defaults false, so `%author%`
 * stays as a control throughout.
 *
 * Every test in the nested "reading settings notices" describe below mutates
 * `page_for_posts`/`show_on_front` via `setReadingSettings()` (the
 * `dwpb-test/v1/reading-settings` route, not core's `/wp/v2/settings` — see
 * `config/seed.ts`). Restore lives in that describe's `afterEach`, not the
 * test body: a body is abandoned mid-unwind on timeout, stranding the
 * setting for every later spec.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { formTableRow, noticeWith } from '../../config/admin';
import { setReadingSettings, siteConfig } from '../../config/seed';
import type { ReadingSettings } from '../../config/seed';
import { pluginStrings } from '../../config/strings';

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

	test.describe( 'reading settings notices (mutates site-wide reading settings)', () => {
		let previous: ReadingSettings | undefined;

		test.afterEach( async ( { requestUtils } ) => {
			// Restore the exact prior value rather than re-running setupSite().
			if ( previous ) {
				await setReadingSettings( requestUtils, previous );
				previous = undefined;
			}
		} );

		test( 'the front-page-equals-posts-page notice appears', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			const config = await siteConfig( requestUtils );

			( { previous } = await setReadingSettings( requestUtils, {
				pageForPosts: config.homeId,
			} ) );

			await admin.visitAdminPage( 'options-reading.php' );

			const strings = await pluginStrings( requestUtils );
			await expect( noticeWith( page, strings.front_equals_posts_notice ) ).toBeVisible();
		} );

		test( 'the no-front-page notice appears on the plugins screen', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			( { previous } = await setReadingSettings( requestUtils, {
				showOnFront: 'posts',
			} ) );

			await admin.visitAdminPage( 'plugins.php' );

			const strings = await pluginStrings( requestUtils );
			await expect( noticeWith( page, strings.no_front_page_notice ) ).toBeVisible();
		} );

		test( 'the no-front-page notice on options-reading.php points at the form below it, not itself', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			( { previous } = await setReadingSettings( requestUtils, {
				showOnFront: 'posts',
			} ) );

			await admin.visitAdminPage( 'options-reading.php' );

			const strings = await pluginStrings( requestUtils );
			await expect( noticeWith( page, strings.no_front_page_notice ) ).toBeVisible();

			// admin_notices()'s 'options-reading' branch: tells the user to pick
			// a page right here instead of linking them to Reading Settings,
			// since they're already on it -- unlike the plugins-screen case above.
			await expect(
				noticeWith( page, 'Select a page for your homepage below.' )
			).toBeVisible();
			await expect( page.getByRole( 'link', { name: 'Reading Settings' } ) ).toHaveCount( 0 );
		} );

		test( 'the no-front-page notice appears on a post type list screen too', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			( { previous } = await setReadingSettings( requestUtils, {
				showOnFront: 'posts',
			} ) );

			// post_type=page: edit.php with no post_type (or post_type=post)
			// redirects elsewhere (redirect_admin_edit()); the 'edit' screen
			// base is the same for any post type, and that's what
			// admin_notices() actually checks.
			await admin.visitAdminPage( 'edit.php', 'post_type=page' );

			const strings = await pluginStrings( requestUtils );
			await expect( noticeWith( page, strings.no_front_page_notice ) ).toBeVisible();

			// Proves admin_notices()'s screen scope includes 'edit', not just
			// 'plugins' (above) and 'options-reading' -- this is the generic
			// link variant, same as the plugins-screen case.
			await expect( page.getByRole( 'link', { name: 'Reading Settings' } ) ).toBeVisible();
		} );
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

	test( 'the permalinks screen JS hides the category/tag base rows and collapses "Optional"', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'options-permalink.php' );

		// Control: the inputs genuinely render on this screen -- without this,
		// a wrong page/redirect would make formTableRow() match nothing at
		// all, and toBeHidden() below would pass vacuously on a locator with
		// zero matches instead of on a real "hidden" class.
		await expect( page.locator( '#category_base' ) ).toHaveCount( 1 );
		await expect( page.locator( '#tag_base' ) ).toHaveCount( 1 );

		// disable-blog-admin.js's options-permalink case hides both rows,
		// since category/post_tag are unsupported by default. Checked via
		// toHaveClass() on the <tr> hideRow() targets directly -- not just
		// toBeHidden(), which the "Optional" table's own "hidden" class below
		// would also satisfy by cascading through every row inside it,
		// independent of whether hideRow() ran on this one specifically.
		await expect( formTableRow( page, 'category_base' ) ).toHaveClass(
			/(^|\s)hidden(\s|$)/
		);
		await expect( formTableRow( page, 'tag_base' ) ).toHaveClass( /(^|\s)hidden(\s|$)/ );
		await expect( formTableRow( page, 'category_base' ) ).toBeHidden();
		await expect( formTableRow( page, 'tag_base' ) ).toBeHidden();

		// category_base/tag_base are the only two rows in the "Optional"
		// table, and both are unsupported, so the script also collapses the
		// section's heading and description paragraph.
		//
		// CSS locators, not getByRole()/getByText() -- a `display: none`
		// element drops out of the accessibility tree, so a role/text query
		// would find nothing at all once the "hidden" class lands, rather
		// than finding it and reporting it hidden.
		const optionalHeading = page.locator( 'h2.title', { hasText: 'Optional' } );
		const optionalDescription = page.locator( 'p.permalink-structure-optional-description' );

		await expect( optionalHeading ).toHaveCount( 1 );
		await expect( optionalDescription ).toHaveCount( 1 );
		await expect( optionalHeading ).toBeHidden();
		await expect( optionalDescription ).toBeHidden();

		// Control: the page itself loaded normally, not some other/blank
		// screen -- the "Permalink Settings" page heading is untouched by
		// this code path.
		await expect( page.getByRole( 'heading', { name: 'Permalink Settings' } ) ).toBeVisible();
	} );
} );
