/**
 * The core Query Loop block's registered default post type, default plugin
 * state.
 *
 * COVERAGE: `Disable_Blog_Admin::filter_block_type_metadata()`, hooked on
 * `block_type_metadata` unconditionally, rewrites `core/query`'s
 * `attributes.query.default.postType` from `'post'` to `'page'` at
 * block-registration time, so every consumer — including the REST
 * block-types endpoint asserted here — sees `'page'` as the default.
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
