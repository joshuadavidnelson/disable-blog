/**
 * The `dwpb_disabled_xmlrpc_methods` escape hatch, via `setFilterOverrides()`.
 * Returning `false` from this filter (instead of an array) short-circuits
 * the entire XML-RPC removal loop, restoring every method on the plugin's
 * fixed list — not just the taxonomy-conditional subset
 * `filters/cpt-branches.spec.ts` exercises.
 *
 * `demo.sayHello` is used instead of `pingback.ping` to prove the restore:
 * WordPress 7.1 removes `pingback.ping` itself on any non-production
 * environment, so no plugin filter can restore it there.
 *
 * No browser session concept applies to XML-RPC, so this file uses the plain
 * `request` fixture throughout.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';
import { ADMIN_USERNAME, ADMIN_PASSWORD, callXmlRpc } from '../../config/xmlrpc';

test.describe( 'filters: XML-RPC restore (dwpb_disabled_xmlrpc_methods)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, { dwpb_disabled_xmlrpc_methods: false } );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'wp.getPosts is restored', async ( { request } ) => {
		const body = await callXmlRpc( request, 'wp.getPosts', [
			0,
			ADMIN_USERNAME,
			ADMIN_PASSWORD,
		] );

		expect( body ).not.toContain( '<name>faultCode</name>' );
		expect( body ).not.toContain( '-32601' );
	} );

	test( 'demo.sayHello is restored', async ( { request } ) => {
		const body = await callXmlRpc( request, 'demo.sayHello' );

		expect( body ).not.toContain( '-32601' );
	} );
} );
