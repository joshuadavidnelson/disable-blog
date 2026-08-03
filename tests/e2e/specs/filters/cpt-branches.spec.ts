/**
 * The "unless another post type uses it" branches of the `dwpb_*` filter API,
 * unlocked by registering a second, real post type ('news') that uses the
 * built-in 'category' and 'post_tag' taxonomies -- exercised via the
 * `dwpb-test-cpt.php` mu-plugin fixture rather than the plugin's shipped
 * defaults.
 *
 * COVERAGE: with `dwpb_test_cpt_enabled` on, `dwpb_post_types_with_tax( 'category' | 'post_tag' )`
 * (includes/functions.php) stops returning `false` -- it returns `array( 'news' )`
 * -- which flips every branch in the plugin gated on that function from its
 * default-state "only 'post' uses this taxonomy" behaviour to its
 * "something else uses it too" behaviour:
 *  - `Disable_Blog_Public::redirect_public_pages()`'s `post_tag_archive` /
 *    `category_archive` entries in `$public_redirects` stop matching, so
 *    those archives stop redirecting (tests 2, 4).
 *  - `Disable_Blog_Public::modify_query()` restricts the tag/category archive
 *    query to exactly the post types `dwpb_post_types_with_tax()` returns,
 *    excluding 'post' entirely (tests 3, 4).
 *  - `Disable_Blog_Admin::modify_taxonomies_arguments()` stops stripping
 *    `show_in_rest`/`show_ui`/... off the 'category'/'post_tag' taxonomies,
 *    so their REST routes and admin menu links come back (tests 5, 6, 7).
 *  - `Disable_Blog_Admin::redirect_admin_pages()`'s `edit-tags.php` branch
 *    (`redirect_admin_edit_tags()`) stops matching (test 8).
 *  - `Disable_Blog_Public::wp_sitemaps_taxonomies()` stops `unset()`-ing the
 *    'category'/'post_tag' sitemap entries (test 9).
 *  - `Disable_Blog_Public::get_disabled_xmlrpc_methods()` stops adding the
 *    taxonomy XML-RPC methods (`metaWeblog.getCategories`, ...) to its
 *    removal list (test 10).
 * `Disable_Blog_Admin::modify_post_type_arguments()` and
 * `::modify_taxonomies_arguments()` are both hooked on `init` at priority 25
 * (see `Disable_Blog::define_admin_hooks()`); the fixture registers 'news' on
 * `init` at priority 10, strictly before either runs -- see
 * `dwpb-test-cpt.php`'s docblock for why that ordering matters.
 *
 * REWRITE RULES: `flushRewrites()` runs once right after the fixture is
 * switched on (so `/news/...` permalinks and the taxonomy archive base URLs
 * resolve against fresh rules) and again after it is switched back off in
 * `afterAll` (so no later spec inherits rules built with 'news' in them). See
 * `dwpb-test-cpt.php`'s docblock for why a flush is required at all.
 *
 * CONTENT SHAPE: one 'news' item (`newsItem`) carries both a category and a
 * tag term, and one ordinary 'post' (`otherPost`) is seeded into the SAME
 * category and tag. `otherPost` is the negative control for tests 3 and 4 --
 * proving the archive query is restricted to 'news', not merely that no
 * 'post' content happens to exist.
 *
 * REQUEST LAYER FOR REDIRECTS/STATUS: `expectRedirect()` / `expectStatus()`
 * from `config/redirects.ts`, same reasoning as every other spec in this
 * suite -- see that file's docblock. No `storageState` override: every check
 * here is either public content unaffected by login state, or an admin
 * screen that needs the project's default administrator session (see
 * `admin-menu.spec.ts` / `admin-redirects.spec.ts` for the same choice made
 * for the same reason).
 *
 * RAW XML-RPC: test 10 hand-builds its XML-RPC request the same way
 * `rest-xmlrpc/xmlrpc.spec.ts` does (no XML-RPC client dependency in this
 * suite) -- see that file's docblock for the full explanation of why method
 * lookup happens before params are validated.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { APIRequestContext } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	siteConfig,
	seedPost,
	seedTerm,
	deletePosts,
	uniqueTitle,
	flushRewrites,
} from '../../config/seed';
import type { SeededPost } from '../../config/seed';
import { adminUrl } from '../../config/admin';
import { expectStatus } from '../../config/redirects';
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

/**
 * wp-env's built-in administrator. Fixed, throwaway local credentials -- not
 * a secret worth centralizing further than this file. Mirrors
 * `rest-xmlrpc/xmlrpc.spec.ts`.
 */
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'password';

/**
 * Escape a string for safe inclusion inside XML-RPC `<string>` text content.
 *
 * @param value Raw string value.
 */
function escapeXml( value: string ): string {
	return value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}

/**
 * Render a single XML-RPC param, as `<string>` or `<int>` depending on type.
 *
 * @param value Param value.
 */
function xmlRpcParam( value: string | number ): string {
	if ( 'number' === typeof value ) {
		return `<param><value><int>${ value }</int></value></param>`;
	}

	return `<param><value><string>${ escapeXml( value ) }</string></value></param>`;
}

/**
 * Build a minimal XML-RPC `methodCall` request body.
 *
 * @param methodName XML-RPC method name, e.g. `'metaWeblog.getCategories'`.
 * @param params     Ordered param values. Defaults to none.
 */
function methodCallXml( methodName: string, params: ( string | number )[] = [] ): string {
	const paramsXml = params.map( xmlRpcParam ).join( '' );

	return (
		'<?xml version="1.0"?>' +
		`<methodCall><methodName>${ escapeXml( methodName ) }</methodName>` +
		`<params>${ paramsXml }</params></methodCall>`
	);
}

/**
 * POST an XML-RPC `methodCall` to `/xmlrpc.php` and return the raw response text.
 *
 * @param request    Playwright API request context.
 * @param methodName XML-RPC method name.
 * @param params     Ordered param values. Defaults to none.
 */
async function callXmlRpc(
	request: APIRequestContext,
	methodName: string,
	params: ( string | number )[] = []
): Promise< string > {
	const response = await request.post( '/xmlrpc.php', {
		headers: { 'Content-Type': 'text/xml' },
		data: methodCallXml( methodName, params ),
	} );

	return response.text();
}

test.describe( 'filters: CPT branches (dwpb_test_cpt_enabled)', () => {
	let newsItem: SeededPost;
	let otherPost: SeededPost;
	let categoryTerm: { termId: number; slug: string; link: string };
	let tagTerm: { termId: number; slug: string; link: string };

	const seededIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		// Ensure the site config (home/front-page urls) is warm before this
		// spec starts flipping rewrite-affecting fixtures.
		await siteConfig( requestUtils );

		await setFixtures( requestUtils, { [ FIXTURE_TOGGLES.cptEnabled ]: true } );

		// Required so /news/... and the taxonomy archive base URLs resolve --
		// see dwpb-test-cpt.php's docblock on why a flush is not optional here.
		await flushRewrites( requestUtils );

		newsItem = await seedPost( requestUtils, {
			title: uniqueTitle( 'cpt news item' ),
			postType: 'news',
		} );
		seededIds.push( newsItem.id );

		categoryTerm = await seedTerm( requestUtils, {
			taxonomy: 'category',
			name: uniqueTitle( 'cpt category' ),
			assignTo: newsItem.id,
		} );

		tagTerm = await seedTerm( requestUtils, {
			taxonomy: 'post_tag',
			name: uniqueTitle( 'cpt tag' ),
			assignTo: newsItem.id,
		} );

		// A 'post' in the SAME category and tag -- the negative control for
		// tests 3 and 4: the archive query must exclude it because it is a
		// 'post', not merely because nothing else happens to be in those terms.
		otherPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'cpt other post' ),
			categories: [ categoryTerm.termId ],
			tags: [ tagTerm.termId ],
		} );
		seededIds.push( otherPost.id );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Best-effort -- deletePosts() never throws, see its docblock. The
		// seeded category/tag terms are deliberately left in place, matching
		// this suite's existing convention (see admin-redirects.spec.ts's
		// categoryTerm) -- they are only wiped by global-setup's
		// reset-content at the start of the next run.
		await deletePosts( requestUtils, seededIds );

		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.cptEnabled ] );

		// Flush again so no later spec inherits rewrite rules built with
		// 'news' still registered -- see dwpb-test-cpt.php's docblock.
		await flushRewrites( requestUtils );
	} );

	test( 'the news CPT is publicly reachable', async ( { request } ) => {
		// Proves the fixture itself works -- registered, flushed, and
		// resolvable -- before anything else in this file is asserted.
		const response = await request.get( newsItem.permalink );

		expect( response.status() ).toBe( 200 );

		const body = await response.text();
		expect( body ).toContain( newsItem.title );
	} );

	test( 'category archives stop redirecting', async ( { request } ) => {
		// is_category() && ! dwpb_post_types_with_tax( 'category' ) no longer
		// matches: dwpb_post_types_with_tax( 'category' ) now returns
		// array( 'news' ), so the category_archive entry in $public_redirects
		// evaluates false.
		await expectStatus( request, categoryTerm.link, 200 );
	} );

	test( 'category archives list CPT content but not posts', async ( { request } ) => {
		// modify_query() restricts the category archive's post_type to
		// dwpb_post_types_with_tax( 'category' )'s result -- array( 'news' ) --
		// excluding 'post' entirely, regardless of otherPost's own category.
		const response = await request.get( categoryTerm.link );
		const body = await response.text();

		expect( body ).toContain( newsItem.title );
		expect( body ).not.toContain( otherPost.title );
	} );

	test( 'tag archives stop redirecting and exclude posts', async ( { request } ) => {
		// Same shape as the category pair above, for post_tag.
		await expectStatus( request, tagTerm.link, 200 );

		const response = await request.get( tagTerm.link );
		const body = await response.text();

		expect( body ).toContain( newsItem.title );
		expect( body ).not.toContain( otherPost.title );
	} );

	test( 'the categories REST route returns', async ( { request } ) => {
		// 404 by default (modify_taxonomies_arguments() strips show_in_rest
		// off 'category' when only 'post' uses it) -- now 200 since 'news'
		// uses it too.
		await expectStatus( request, '/wp-json/wp/v2/categories', 200 );
	} );

	test( 'the tags REST route returns', async ( { request } ) => {
		await expectStatus( request, '/wp-json/wp/v2/tags', 200 );
	} );

	test( 'the Categories admin menu returns', async ( { admin, page } ) => {
		// Same navigation target as admin-menu.spec.ts: edit.php?post_type=page
		// is provably not redirected by this plugin, so the menu it renders is
		// never mid-redirect.
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		// With 'news' registering 'category' via its own `taxonomies` arg
		// (dwpb-test-cpt.php's register_post_type() call), core adds the
		// Categories submenu under the 'news' top-level menu, so its href is
		// `edit-tags.php?taxonomy=category&post_type=news` -- not the bare
		// `edit-tags.php?taxonomy=category` a 'post'-only site would render.
		// `href*=` matches the taxonomy query arg regardless of which post
		// type's menu it ends up parented under.
		//
		// ADMIN SUBMENU ITEMS ARE HIDDEN UNTIL THEIR PARENT IS ACTIVE: core
		// only shows a top-level menu's submenu `<ul>` when that top-level
		// menu is the current screen (open) or hovered/focused -- same CSS
		// pattern already documented in admin-bar.spec.ts for the "+ New"
		// dropdown. The current screen here is Pages, not News, so the
		// Categories link under the News menu is CSS-hidden by design;
		// `toBeVisible()` on it is a false failure. Assert DOM presence
		// instead.
		await expect(
			page.locator( '#adminmenu a[href*="edit-tags.php?taxonomy=category"]' )
		).toHaveCount( 1 );

		// Control: proves the menu itself rendered, so the assertion above
		// isn't vacuously true against a menu that never loaded.
		await expect( page.locator( '#adminmenu' ) ).toBeVisible();
	} );

	test( 'edit-tags.php stops redirecting', async ( { request } ) => {
		// redirect_admin_edit_tags() returns
		// isset( $_GET['taxonomy'] ) && ! dwpb_post_types_with_tax( $_GET['taxonomy'] ),
		// which is now false.
		await expectStatus( request, adminUrl( 'edit-tags.php?taxonomy=category' ), 200 );
	} );

	test( 'the sitemap index gains CPT and taxonomy sitemaps', async ( { request } ) => {
		const response = await request.get( '/wp-sitemap.xml' );
		const body = await response.text();

		expect( body ).toContain( 'wp-sitemap-posts-news-1.xml' );
		expect( body ).toContain( 'wp-sitemap-taxonomies-category-1.xml' );
	} );

	test( 'XML-RPC taxonomy methods are restored', async ( { request } ) => {
		// get_disabled_xmlrpc_methods() only adds the taxonomy methods
		// (including metaWeblog.getCategories) when
		// ! dwpb_post_types_with_tax( 'category' ) -- now false, so the method
		// stays registered and a valid authenticated call succeeds.
		const body = await callXmlRpc( request, 'metaWeblog.getCategories', [
			0,
			ADMIN_USERNAME,
			ADMIN_PASSWORD,
		] );

		expect( body ).not.toContain( '<name>faultCode</name>' );
		expect( body ).not.toContain( '-32601' );
	} );
} );
