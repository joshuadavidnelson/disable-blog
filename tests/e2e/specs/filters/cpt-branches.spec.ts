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

// wp-env's built-in administrator; throwaway local credentials.
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'password';

// Escape a string for safe inclusion inside XML-RPC `<string>` text content.
function escapeXml( value: string ): string {
	return value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}

// Render a single XML-RPC param, as `<string>` or `<int>` depending on type.
function xmlRpcParam( value: string | number ): string {
	if ( 'number' === typeof value ) {
		return `<param><value><int>${ value }</int></value></param>`;
	}

	return `<param><value><string>${ escapeXml( value ) }</string></value></param>`;
}

// Build a minimal XML-RPC methodCall request body.
function methodCallXml( methodName: string, params: ( string | number )[] = [] ): string {
	const paramsXml = params.map( xmlRpcParam ).join( '' );

	return (
		'<?xml version="1.0"?>' +
		`<methodCall><methodName>${ escapeXml( methodName ) }</methodName>` +
		`<params>${ paramsXml }</params></methodCall>`
	);
}

// POST an XML-RPC methodCall to /xmlrpc.php and return the raw response text.
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
		await siteConfig( requestUtils );

		await setFixtures( requestUtils, { [ FIXTURE_TOGGLES.cptEnabled ]: true } );

		// Required so /news/... and the taxonomy archive base URLs resolve.
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

		otherPost = await seedPost( requestUtils, {
			title: uniqueTitle( 'cpt other post' ),
			categories: [ categoryTerm.termId ],
			tags: [ tagTerm.termId ],
		} );
		seededIds.push( otherPost.id );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Seeded category/tag terms are left in place, wiped by global-setup's
		// reset-content at the start of the next run.
		await deletePosts( requestUtils, seededIds );

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
		await expectStatus( request, tagTerm.link, 200 );

		const response = await request.get( tagTerm.link );
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
