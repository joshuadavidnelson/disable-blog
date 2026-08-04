/**
 * `dwpb_taxonomy_support`, via the generic filter-override mechanism --
 * overrides what `dwpb_post_types_with_tax()` reports for ANY taxonomy
 * queried, unlike the CPT fixture (`dwpb_test_cpt_enabled`, see
 * filters/cpt-branches.spec.ts) which only makes one real post type declare
 * support.
 *
 * Forced to report 'category' as supported by another post type,
 * `modify_taxonomies_arguments()` skips stripping the category taxonomy's
 * `show_in_rest`, so the `/wp/v2/categories` REST route becomes reachable
 * again -- the same downstream signal cpt-branches.spec.ts asserts for a
 * real CPT.
 *
 * Control: rest-xmlrpc/rest-api.spec.ts asserts the shipped default --
 * "the categories route is gone" (404 `rest_no_route`).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { expectStatus } from '../../config/redirects';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';

test.describe( 'filters: taxonomy support override (dwpb_taxonomy_support)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_taxonomy_support: { set: [ 'page' ] },
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'the categories REST route returns real category data', async ( { request } ) => {
		const response = await expectStatus( request, '/wp-json/wp/v2/categories', 200 );

		const body = await response.json();
		expect( Array.isArray( body ) ).toBe( true );
		expect( body[ 0 ].taxonomy ).toBe( 'category' );
	} );
} );
