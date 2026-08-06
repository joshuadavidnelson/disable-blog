/**
 * Core REST API surface, default plugin state. `modify_post_type_arguments()`
 * unconditionally sets `show_in_rest` false on 'post'; `modify_taxonomies_arguments()`
 * does the same for 'category'/'post_tag' since no other post type declares
 * them by default. `page` is untouched. With `show_in_rest` false, core never
 * registers routes for that type/taxonomy, so posts/categories/tags 404 with
 * the generic `rest_no_route`, not a plugin-specific error.
 *
 * All requests are anonymous.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'REST API: default state', () => {
	test( 'the posts route is gone', async ( { request } ) => {
		const response = await request.get( '/wp-json/wp/v2/posts' );

		expect( response.status() ).toBe( 404 );

		const body = await response.json();

		expect( body.code ).toBe( 'rest_no_route' );
	} );

	test( 'the categories route is gone', async ( { request } ) => {
		const response = await request.get( '/wp-json/wp/v2/categories' );

		expect( response.status() ).toBe( 404 );

		const body = await response.json();

		expect( body.code ).toBe( 'rest_no_route' );
	} );

	test( 'the tags route is gone', async ( { request } ) => {
		const response = await request.get( '/wp-json/wp/v2/tags' );

		expect( response.status() ).toBe( 404 );

		const body = await response.json();

		expect( body.code ).toBe( 'rest_no_route' );
	} );

	test( 'the types collection omits post but keeps page', async ( { request } ) => {
		const response = await request.get( '/wp-json/wp/v2/types' );

		expect( response.status() ).toBe( 200 );

		const body = await response.json();

		expect( body ).not.toHaveProperty( 'post' );
		expect( body ).toHaveProperty( 'page' );
	} );

	test( 'the post type route is forbidden', async ( { request } ) => {
		const response = await request.get( '/wp-json/wp/v2/types/post' );

		// 401, not 403: rest_authorization_required_code() returns 401 for a
		// signed-out caller.
		expect( response.status() ).toBe( 401 );

		const body = await response.json();

		expect( body.code ).toBe( 'rest_cannot_read_type' );
	} );

	test( 'untouched core routes still work', async ( { request } ) => {
		const untouchedRoutes = [ '/wp-json/wp/v2/pages', '/wp-json/wp/v2/users', '/wp-json/wp/v2/comments' ];

		for ( const route of untouchedRoutes ) {
			const response = await request.get( route );

			expect( response.status(), route ).toBe( 200 );
		}
	} );

	test( 'the REST index advertises no posts route', async ( { request } ) => {
		const response = await request.get( '/wp-json/wp/v2' );

		expect( response.status() ).toBe( 200 );

		const body = await response.json();

		expect( body.routes ).not.toHaveProperty( '/wp/v2/posts' );
	} );
} );
