/**
 * Core REST API surface, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Admin::modify_post_type_arguments()` and
 * `::modify_taxonomies_arguments()`, both hooked on `init` at priority 25.
 * `modify_post_type_arguments()` unconditionally sets `show_in_rest` (among
 * other args) to `false` on the 'post' post type on every load — no filter
 * gates it. `modify_taxonomies_arguments()` only nulls `show_in_rest` on
 * 'category'/'post_tag' when `dwpb_post_types_with_tax( $tax )` is falsy,
 * i.e. when no other registered post type declares that taxonomy; in a
 * stock install with no custom post types, that is always the case, so both
 * built-in taxonomies lose `show_in_rest` too. 'page' is untouched by either
 * method.
 *
 * Once `show_in_rest` is false, WordPress core never registers REST routes
 * for that post type/taxonomy at all
 * (`create_initial_rest_routes()` in `wp-includes/rest-api.php` checks
 * `show_in_rest` before instantiating a controller) — this is core behaviour
 * the plugin relies on, not something it re-implements. So `/wp/v2/posts`,
 * `/wp/v2/categories`, and `/wp/v2/tags` return the generic
 * `rest_no_route` 404 any unregistered route would, not a plugin-specific
 * error.
 *
 * `GET /wp/v2/types/post` is a different code path worth calling out: core's
 * `WP_REST_Post_Types_Controller::get_item()` registers its route with
 * `permission_callback => '__return_true'`, so nothing short-circuits before
 * the callback runs. Inside `get_item()`, `empty( $obj->show_in_rest )`
 * returns `WP_Error( 'rest_cannot_read_type', ..., array( 'status' =>
 * rest_authorization_required_code() ) )`, and `rest_authorization_required_code()`
 * (`wp-includes/rest-api.php`) returns 401 for a signed-out request, 403 only
 * for a signed-in one. Verified directly against WordPress core source
 * (`class-wp-rest-post-types-controller.php`, `rest-api.php`) rather than
 * assumed. Confirmed against every request in this file being anonymous:
 * expect **401**, not 403, with code `rest_cannot_read_type` — see the note
 * on test 5 below.
 *
 * ANONYMOUS CONTEXT: every request in this file uses the bare `request`
 * fixture with no admin cookie — these are public-read assertions, and the
 * plugin's REST changes apply regardless of who is asking, so there is no
 * need for an authenticated context here.
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
		// Control: 'page' is untouched by the plugin's REST changes, so it
		// must still be present — proves the assertion above is a targeted
		// absence, not an empty/broken collection.
		expect( body ).toHaveProperty( 'page' );
	} );

	test( 'the post type route is forbidden', async ( { request } ) => {
		const response = await request.get( '/wp-json/wp/v2/types/post' );

		// Deliberately 401, not 403: this request is anonymous, and
		// rest_authorization_required_code() returns 401 for a signed-out
		// caller. See the file docblock for the exact core code path this
		// was verified against.
		expect( response.status() ).toBe( 401 );

		const body = await response.json();

		expect( body.code ).toBe( 'rest_cannot_read_type' );
	} );

	test( 'untouched core routes still work', async ( { request } ) => {
		// Controls: prove the plugin's REST changes are targeted at
		// 'post'/'category'/'post_tag', not a blanket lockdown of the REST
		// API.
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
