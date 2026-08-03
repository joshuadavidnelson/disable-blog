/**
 * The core Query Loop block's registered default post type, default plugin
 * state.
 *
 * COVERAGE: `Disable_Blog_Admin::filter_block_type_metadata()`, hooked on
 * `block_type_metadata` unconditionally, rewrites `core/query`'s
 * `attributes.query.default.postType` from `'post'` to `'page'` at
 * block-registration time -- so every consumer of the block's metadata, the
 * REST block-types endpoint included, sees `'page'` as the default.
 *
 * RESPONSE SHAPE, VERIFIED NOT ASSUMED: confirmed directly against the
 * wp-env core install backing this suite (`wp eval` calling
 * `rest_do_request()` for this exact route as an administrator) that
 * `GET /wp/v2/block-types/core/query?context=edit` returns
 * `data.attributes.query.default.postType === 'page'`, matching the brief's
 * expected path exactly -- no shape mismatch to report here.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'admin: query block default post type (default state)', () => {
	test( 'the Query Loop block defaults to pages', async ( { requestUtils } ) => {
		const response = await requestUtils.rest< {
			attributes: { query: { default: { postType: string } } };
		} >( { path: '/wp/v2/block-types/core/query?context=edit' } );

		expect( response.attributes.query.default.postType ).toBe( 'page' );
	} );
} );
