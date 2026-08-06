/**
 * Settings screens (Reading, Writing, Permalinks) and their notices, default
 * plugin state.
 *
 * The permalinks screen has two independent mechanisms: JS hides the
 * category_base/tag_base rows, while available_permalink_structure_tags()
 * drops the %category% button server-side.
 *
 * Default state leaves `%author%` in place (dwpb_disable_author_archives
 * defaults false) while `%category%` goes, which is what makes the author
 * assertions usable as controls.
 *
 * The reading-settings describe restores in afterEach, not the test body: a
 * body is abandoned mid-unwind on timeout, stranding the setting for every
 * later spec.
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

		await expect( formTableRow( page, 'posts_per_page' ) ).toBeHidden();
		await expect( formTableRow( page, 'posts_per_rss' ) ).toBeHidden();

		// rss_use_excerpt's row has no <label for>, only a <legend>, so match
		// on its radio inputs instead.
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

			// On options-reading.php the notice tells the user to pick a page
			// right here rather than linking to Reading Settings.
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

			// post_type=post/no post_type redirects elsewhere via
			// redirect_admin_edit(), so post_type=page is required to reach
			// the 'edit' screen base at all.
			await admin.visitAdminPage( 'edit.php', 'post_type=page' );

			const strings = await pluginStrings( requestUtils );
			await expect( noticeWith( page, strings.no_front_page_notice ) ).toBeVisible();
			await expect( page.getByRole( 'link', { name: 'Reading Settings' } ) ).toBeVisible();
		} );
	} );

	// options-writing.php's Formatting and Link Manager sections no longer
	// exist on modern WordPress, so there's no neighbouring row left to assert
	// against; the tests below check the heading instead.

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

		// Removed server-side, not just hidden, hence toHaveCount( 0 ).
		await expect( page.locator( '#ping_sites' ) ).toHaveCount( 0 );
		await expect( page.locator( '#mailserver_url' ) ).toHaveCount( 0 );

		await expect( page.getByRole( 'heading', { name: 'Writing Settings' } ) ).toBeVisible();
	} );

	test( 'the permalinks screen drops the category structure tag but keeps author', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'options-permalink.php' );

		// Scoped to .available-structure-tags: the Help tab also mentions
		// "%category%" in prose, which an unscoped match would hit too.
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

		// Without this, a wrong page/redirect would make formTableRow() match
		// nothing, and toBeHidden() below would pass vacuously.
		await expect( page.locator( '#category_base' ) ).toHaveCount( 1 );
		await expect( page.locator( '#tag_base' ) ).toHaveCount( 1 );

		// toHaveClass() on the row itself, not just toBeHidden() -- the
		// "Optional" table's own "hidden" class (asserted below) would satisfy
		// toBeHidden() on every row inside it regardless of whether hideRow()
		// ran on this row specifically.
		await expect( formTableRow( page, 'category_base' ) ).toHaveClass(
			/(^|\s)hidden(\s|$)/
		);
		await expect( formTableRow( page, 'tag_base' ) ).toHaveClass( /(^|\s)hidden(\s|$)/ );
		await expect( formTableRow( page, 'category_base' ) ).toBeHidden();
		await expect( formTableRow( page, 'tag_base' ) ).toBeHidden();

		// CSS locators, not getByRole()/getByText(): a `display: none` element
		// drops out of the accessibility tree, so a role/text query would find
		// nothing once the "hidden" class lands, rather than finding it and
		// reporting it hidden.
		const optionalHeading = page.locator( 'h2.title', { hasText: 'Optional' } );
		const optionalDescription = page.locator( 'p.permalink-structure-optional-description' );

		await expect( optionalHeading ).toHaveCount( 1 );
		await expect( optionalDescription ).toHaveCount( 1 );
		await expect( optionalHeading ).toBeHidden();
		await expect( optionalDescription ).toBeHidden();

		// Control: confirms the page loaded normally rather than some other/blank screen.
		await expect( page.getByRole( 'heading', { name: 'Permalink Settings' } ) ).toBeVisible();
	} );
} );
