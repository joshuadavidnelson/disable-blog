/**
 * Proof spec for the generic filter-override mechanism
 * (`config/filter-overrides.ts` + `dwpb-test-filters.php`), covering three
 * filters built by string concatenation inside
 * `Disable_Blog_Public::redirect_public_pages()`'s `foreach` loop
 * (`'dwpb_redirect_' . $filtername`): `dwpb_redirect_date_archive`,
 * `dwpb_redirect_category_archive`, and `dwpb_redirect_post_tag_archive`.
 *
 * Each block also asserts that the blog page (a different branch of the same
 * loop) still redirects to the default front page, proving the per-branch
 * filter name resolves independently rather than one umbrella filter firing
 * for every page.
 *
 * The category/tag blocks double as regression cover for `is_category()`/
 * `is_tag()` detection: with no other post type using the taxonomy,
 * `modify_taxonomies_arguments()` strips its `query_var`, so core never
 * populates `WP_Query::$tax_query` and `is_category()` stays false.
 * `redirect_public_pages()` reads the raw request query var directly
 * instead, which the rewrite rule populates regardless.
 */

/**
 * WordPress dependencies
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, uniqueTitle } from '../../config/seed';
import { createContentTracker } from '../../config/content-tracker';
import { expectRedirect } from '../../config/redirects';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

// Same host as home_url(): wp_safe_redirect() rejects an off-site Location,
// which would make the override indistinguishable from a broken filter.
const OVERRIDE_PATH = '/dwpb-test-filter-override/';

// Fixed, known post date so the year-archive URL below is deterministic
// instead of depending on "today" (mirrors frontend/redirects.spec.ts).
const POST_YEAR = '2022';
const POST_DATE = `${ POST_YEAR }-03-14 09:30:00`;

test.describe( 'filters: generic filter-override mechanism (dwpb_redirect_date_archive)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let frontPageUrl: string;
	let overrideUrl: string;

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		frontPageUrl = config.frontPageUrl;
		overrideUrl = `${ config.homeUrl }${ OVERRIDE_PATH }`;

		await content.seedPost( requestUtils, {
			title: uniqueTitle( 'filter override date archive post' ),
			postDate: POST_DATE,
		} );

		await setFilterOverrides( requestUtils, {
			dwpb_redirect_date_archive: overrideUrl,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
		await content.cleanup( requestUtils );
	} );

	test( 'a date archive redirects to the overridden URL', async ( { request } ) => {
		await expectRedirect( request, `/${ POST_YEAR }/`, overrideUrl );
	} );

	test( 'the blog page still redirects to the default front page', async ( { request } ) => {
		await expectRedirect( request, '/blog/', frontPageUrl );
	} );
} );

test.describe( 'filters: generic filter-override mechanism (dwpb_redirect_category_archive)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let frontPageUrl: string;
	let overrideUrl: string;
	let categorySlug: string;
	let categoryTermId: number;

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		frontPageUrl = config.frontPageUrl;
		overrideUrl = `${ config.homeUrl }${ OVERRIDE_PATH }category/`;

		const term = await content.seedTerm( requestUtils, {
			taxonomy: 'category',
			name: uniqueTitle( 'filter override category archive term' ),
		} );
		categorySlug = term.slug;
		categoryTermId = term.termId;

		await setFilterOverrides( requestUtils, {
			dwpb_redirect_category_archive: overrideUrl,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
		await content.cleanup( requestUtils );
	} );

	test( 'a category archive redirects to the overridden URL', async ( { request } ) => {
		await expectRedirect( request, `/category/${ categorySlug }/`, overrideUrl );
	} );

	test( 'the blog page still redirects to the default front page', async ( { request } ) => {
		await expectRedirect( request, '/blog/', frontPageUrl );
	} );

	test( 'the blog page still redirects to the default front page when the request carries an incidental cat query var', async ( {
		request,
	} ) => {
		// WordPress copies the raw 'cat' query var onto queried_object
		// regardless of what's actually being served, so
		// is_category_archive_request()'s fallback must also require an
		// empty get_queried_object() before trusting it -- otherwise this
		// blog-page request would misroute onto the category override.
		await expectRedirect( request, `/blog/?cat=${ categoryTermId }`, frontPageUrl );
	} );
} );

test.describe( 'filters: generic filter-override mechanism (dwpb_redirect_post_tag_archive)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	let frontPageUrl: string;
	let overrideUrl: string;
	let tagSlug: string;

	const content = createContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		frontPageUrl = config.frontPageUrl;
		overrideUrl = `${ config.homeUrl }${ OVERRIDE_PATH }tag/`;

		const term = await content.seedTerm( requestUtils, {
			taxonomy: 'post_tag',
			name: uniqueTitle( 'filter override tag archive term' ),
		} );
		tagSlug = term.slug;

		await setFilterOverrides( requestUtils, {
			dwpb_redirect_post_tag_archive: overrideUrl,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
		await content.cleanup( requestUtils );
	} );

	test( 'a tag archive redirects to the overridden URL', async ( { request } ) => {
		await expectRedirect( request, `/tag/${ tagSlug }/`, overrideUrl );
	} );

	test( 'the blog page still redirects to the default front page', async ( { request } ) => {
		await expectRedirect( request, '/blog/', frontPageUrl );
	} );

	test( 'the blog page still redirects to the default front page when the request carries an incidental tag query var', async ( {
		request,
	} ) => {
		// Same guard as the category block's equivalent test, for the 'tag'
		// query var and is_tag_archive_request()'s fallback.
		await expectRedirect( request, `/blog/?tag=${ tagSlug }`, frontPageUrl );
	} );
} );
