/**
 * Proof spec for the generic filter-override mechanism
 * (`config/filter-overrides.ts` + `dwpb-test-filters.php`): covers three of
 * the per-branch filters built by string concatenation inside
 * `Disable_Blog_Public::redirect_public_pages()`'s `foreach` loop
 * (`'dwpb_redirect_' . $filtername`) that never appear as a string literal
 * anywhere in the plugin and so have no dedicated fixture toggle:
 * `dwpb_redirect_date_archive`, `dwpb_redirect_category_archive`, and
 * `dwpb_redirect_post_tag_archive`.
 *
 * Each block asserts BOTH that its overridden branch redirects to the
 * override target AND that a different branch of the same loop (the blog
 * page) still redirects to the plugin's default front page — proving the
 * dynamically-built filter name resolves per-branch, rather than some
 * umbrella filter being what actually fired.
 *
 * The category/tag blocks are also the regression cover for the
 * `is_category()`/`is_tag()` detection in `redirect_public_pages()`: with no
 * other post type using the taxonomy, `Disable_Blog_Admin::modify_taxonomies_arguments()`
 * strips the taxonomy's `query_var` (among other public-facing args), which
 * stops core from ever populating `WP_Query::$tax_query` for `category_name`
 * requests, so `is_category()` stays false. `redirect_public_pages()` reads
 * the raw request query var directly instead, which the rewrite rule
 * populates regardless of the taxonomy's public state.
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
		// Different branch of the same foreach loop, left untouched by the
		// override above -- confirms 'dwpb_redirect_' . $filtername resolves
		// per-branch rather than one umbrella filter firing for every page.
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
		// Different branch of the same foreach loop, left untouched by the
		// override above -- confirms 'dwpb_redirect_' . $filtername resolves
		// per-branch rather than every branch collapsing onto is_home().
		await expectRedirect( request, '/blog/', frontPageUrl );
	} );

	test( 'the blog page still redirects to the default front page when the request carries an incidental cat query var', async ( {
		request,
	} ) => {
		// is_category_archive_request()'s fallback reads the raw 'cat' query
		// var directly (see its docblock), because is_category() is
		// unreliable here. That raw var is present on this request too --
		// WordPress copies it onto the queried_object regardless of what's
		// actually being served -- so the fallback must also require an
		// empty get_queried_object() before trusting it, or a blog-page
		// request with an incidental '?cat=' would be misrouted onto this
		// override's target instead of its own.
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
		// Different branch of the same foreach loop, left untouched by the
		// override above -- confirms 'dwpb_redirect_' . $filtername resolves
		// per-branch rather than one umbrella filter firing for every page.
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
