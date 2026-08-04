/**
 * Proof spec for the generic filter-override mechanism
 * (`config/filter-overrides.ts` + `dwpb-test-filters.php`): covers
 * `dwpb_redirect_date_archive`, one of the per-branch filters built by string
 * concatenation inside `Disable_Blog_Public::redirect_public_pages()`'s
 * `foreach` loop (`'dwpb_redirect_' . $filtername`) that never appears as a
 * string literal anywhere in the plugin and so has no dedicated fixture
 * toggle.
 *
 * Asserts BOTH that the overridden branch (date archive) redirects to the
 * override target AND that a different branch of the same loop (the blog
 * page) still redirects to the plugin's default front page — proving the
 * dynamically-built filter name resolves per-branch, rather than some
 * umbrella filter being what actually fired.
 *
 * Deliberately not `dwpb_redirect_category_archive`/`..._post_tag_archive`:
 * both are unreachable. Their branch needs `is_category()`/`is_tag()` true
 * AND `! dwpb_post_types_with_tax(...)`, but
 * `Disable_Blog_Admin::modify_taxonomies_arguments()` only restores the
 * taxonomy's `publicly_queryable`/`query_var` when another post type uses
 * it — the same condition that makes the branch's own guard false. With no
 * other post type the taxonomy stays stripped and `is_category()` never
 * becomes true, so the request falls through to `dwpb_redirect_blog_page`
 * instead. `is_date()` has no such dependency.
 */

/**
 * WordPress dependencies
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig, seedPost, deletePosts, uniqueTitle } from '../../config/seed';
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

	const seededPostIds: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		frontPageUrl = config.frontPageUrl;
		overrideUrl = `${ config.homeUrl }${ OVERRIDE_PATH }`;

		const post = await seedPost( requestUtils, {
			title: uniqueTitle( 'filter override date archive post' ),
			postDate: POST_DATE,
		} );
		seededPostIds.push( post.id );

		await setFilterOverrides( requestUtils, {
			dwpb_redirect_date_archive: overrideUrl,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
		await deletePosts( requestUtils, seededPostIds );
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
