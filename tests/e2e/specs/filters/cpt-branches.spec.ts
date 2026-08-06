/**
 * The "unless another post type uses it" branches of the `dwpb_*` filter API,
 * unlocked by registering a second post type ('news') that uses the built-in
 * 'category'/'post_tag' taxonomies, via the `dwpb-test-cpt.php` mu-plugin
 * fixture. With `dwpb_test_cpt_enabled` on, `dwpb_post_types_with_tax()`
 * returns `array( 'news' )` instead of `false`, which un-redirects the
 * category/tag archives, scopes their query to non-'post' types, restores
 * the category/tag REST routes, admin menu links, `edit-tags.php`, taxonomy
 * sitemaps, and the taxonomy XML-RPC methods.
 *
 * Rewrite rules must be flushed after the fixture is toggled on (so
 * `/news/...` and the taxonomy archive URLs resolve) and again after it's
 * toggled off in afterAll, so no later spec inherits stale rules.
 *
 * `otherPost` is seeded into the same category/tag as `newsItem` as a
 * negative control, proving the archive query excludes 'post' specifically.
 * `secondOtherPost` adds a second 'post' onto `categoryTerm` alone, for the
 * `filter_taxonomy_count()`/`get_term_post_count_by_type()` test, which needs
 * per-post-type counts that actually differ from each other.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, uniqueTitle, flushRewrites } from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { createContentTracker } from '../../config/content-tracker';
import { adminUrl, termRowLocator } from '../../config/admin';
import { expectStatus } from '../../config/redirects';
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';
import { ADMIN_USERNAME, ADMIN_PASSWORD, callXmlRpc } from '../../config/xmlrpc';

test.describe( 'filters: CPT branches (dwpb_test_cpt_enabled)', () => {
	let newsItem: SeededPost;
	let otherPost: SeededPost;
	let categoryTerm: { termId: number; slug: string; link: string };
	let tagTerm: { termId: number; slug: string; link: string };

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		await siteConfig( requestUtils );

		await setFixtures( requestUtils, { [ FIXTURE_TOGGLES.cptEnabled ]: true } );

		// Required so /news/... and the taxonomy archive base URLs resolve.
		await flushRewrites( requestUtils );

		newsItem = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'cpt news item' ),
			postType: 'news',
		} );

		categoryTerm = await content.seedTerm( requestUtils, {
			taxonomy: 'category',
			name: uniqueTitle( 'cpt category' ),
			assignTo: newsItem.id,
		} );

		tagTerm = await content.seedTerm( requestUtils, {
			taxonomy: 'post_tag',
			name: uniqueTitle( 'cpt tag' ),
			assignTo: newsItem.id,
		} );

		otherPost = await content.seedPost( requestUtils, {
			title: uniqueTitle( 'cpt other post' ),
			categories: [ categoryTerm.termId ],
			tags: [ tagTerm.termId ],
		} );

		// A second 'post' on categoryTerm, for the taxonomy-count test below:
		// modify_taxonomies_arguments() unconditionally strips 'post' from
		// category's object_type, so the raw cached WP_Term->count only ever
		// reflects 'news' (1) -- two 'post's on the term (2) is what makes the
		// 'post'-scoped screen's count observably different from that cached
		// value, proving get_term_post_count_by_type() actually re-queried.
		await content.seedPost( requestUtils, {
			title: uniqueTitle( 'cpt other post 2' ),
			categories: [ categoryTerm.termId ],
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await content.cleanup( requestUtils );

		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.cptEnabled ] );

		// Flush again so no later spec inherits rewrite rules built with 'news'.
		await flushRewrites( requestUtils );
	} );

	test( 'the news CPT is publicly reachable', async ( { request } ) => {
		// Proves the fixture itself works before anything else here is asserted.
		const response = await request.get( newsItem.permalink );

		expect( response.status() ).toBe( 200 );

		const body = await response.text();
		expect( body ).toContain( newsItem.title );
	} );

	test( 'category archives stop redirecting', async ( { request } ) => {
		await expectStatus( request, categoryTerm.link, 200 );
	} );

	test( 'category archives list CPT content but not posts', async ( { request } ) => {
		const response = await request.get( categoryTerm.link );
		const body = await response.text();

		expect( body ).toContain( newsItem.title );
		expect( body ).not.toContain( otherPost.title );
	} );

	test( 'tag archives stop redirecting and exclude posts', async ( { request } ) => {
		const response = await expectStatus( request, tagTerm.link, 200 );
		const body = await response.text();

		expect( body ).toContain( newsItem.title );
		expect( body ).not.toContain( otherPost.title );
	} );

	test( 'the categories REST route returns', async ( { request } ) => {
		await expectStatus( request, '/wp-json/wp/v2/categories', 200 );
	} );

	test( 'the tags REST route returns', async ( { request } ) => {
		await expectStatus( request, '/wp-json/wp/v2/tags', 200 );
	} );

	test( 'the Categories admin menu returns', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		// 'news' registers 'category' itself, so core parents the Categories
		// submenu under News (href*= matches regardless), and it's CSS-hidden
		// until News is the active/hovered menu — assert DOM presence, not visibility.
		await expect(
			page.locator( '#adminmenu a[href*="edit-tags.php?taxonomy=category"]' )
		).toHaveCount( 1 );
		await expect( page.locator( '#adminmenu' ) ).toBeVisible();
	} );

	test( 'edit-tags.php stops redirecting', async ( { request } ) => {
		await expectStatus( request, adminUrl( 'edit-tags.php?taxonomy=category' ), 200 );
	} );

	test( "edit-tags.php's Count column is scoped to the current screen's post type", async ( {
		admin,
		page,
	} ) => {
		// categoryTerm sits on 1 'news' post and 2 'post's. filter_taxonomy_count()
		// re-queries by $screen->post_type, so each screen shows only its own
		// share -- not the raw cached count (which is 1 either way; see the
		// beforeAll comment), and not the 3-post combined total.
		await admin.visitAdminPage( 'edit-tags.php', 'taxonomy=category&post_type=news' );
		await expect(
			termRowLocator( page, categoryTerm.termId ).locator( '.column-posts' )
		).toHaveText( '1' );

		await admin.visitAdminPage( 'edit-tags.php', 'taxonomy=category&post_type=post' );
		await expect(
			termRowLocator( page, categoryTerm.termId ).locator( '.column-posts' )
		).toHaveText( '2' );
	} );

	test( 'the sitemap index gains CPT and taxonomy sitemaps', async ( { request } ) => {
		const response = await request.get( '/wp-sitemap.xml' );
		const body = await response.text();

		expect( body ).toContain( 'wp-sitemap-posts-news-1.xml' );
		expect( body ).toContain( 'wp-sitemap-taxonomies-category-1.xml' );
	} );

	test( 'XML-RPC taxonomy methods are restored', async ( { request } ) => {
		const body = await callXmlRpc( request, 'metaWeblog.getCategories', [
			0,
			ADMIN_USERNAME,
			ADMIN_PASSWORD,
		] );

		expect( body ).not.toContain( '<name>faultCode</name>' );
		expect( body ).not.toContain( '-32601' );
	} );
} );
