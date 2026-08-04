/**
 * XML-RPC endpoint behaviour (`Disable_Blog_Public::xmlrpc_methods()` on the
 * `xmlrpc_methods` filter), default plugin state. It unsets a fixed method
 * list plus, conditionally, taxonomy methods (removed by default, since only
 * 'post' supports category/post_tag out of the box). The endpoint itself
 * stays up; a removed method faults with IXR fault code -32601 rather than
 * failing the connection, and that lookup happens before params are read, so
 * fault tests can send empty param lists.
 *
 * Regression guard: the removal list's `wp.deleteCategory` entry must stay
 * correctly spelled -- a misspelling there silently leaves the real method
 * reachable.
 *
 * `system.*` methods (listMethods, multicall, getCapabilities) can never be
 * removed via this filter — IXR_Server::setCallbacks() re-registers them
 * afterwards — so the test below asserts they stay callable; this is a
 * documented constraint, not a regression guard.
 *
 * No browser session concept applies to XML-RPC, so this file uses the plain
 * `request` fixture throughout rather than an empty storageState.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	ADMIN_USERNAME,
	ADMIN_PASSWORD,
	callXmlRpc,
	expectXmlRpcFault,
} from '../../config/xmlrpc';

test.describe( 'XML-RPC: default state', () => {
	test( 'the endpoint still responds to GET', async ( { request } ) => {
		// Core's IXR_Server::serve() rejects non-POST requests regardless of
		// this plugin; the endpoint itself is never disabled.
		const response = await request.get( '/xmlrpc.php', { maxRedirects: 0 } );
		const body = await response.text();

		expect( body ).toContain( 'XML-RPC server accepts POST requests only.' );
		expect( response.status() ).toBe( 405 );
	} );

	test( 'wp.getPosts is removed', async ( { request } ) => {
		const body = await callXmlRpc( request, 'wp.getPosts' );

		expectXmlRpcFault( body, -32601 );
	} );

	test( 'pingback.ping and demo.sayHello are removed', async ( { request } ) => {
		// demo.sayHello needs no auth/params, so a fault here can only mean
		// the method itself is gone. It also carries the plugin-specific
		// signal: WordPress 7.1 removes pingback.ping itself on non-production
		// environments, so that one faults there whatever the plugin does.
		const removedMethods = [ 'pingback.ping', 'demo.sayHello' ];

		for ( const methodName of removedMethods ) {
			const body = await callXmlRpc( request, methodName );

			expectXmlRpcFault( body, -32601 );
		}
	} );

	test( 'taxonomy methods are removed by default', async ( { request } ) => {
		const taxonomyMethods = [ 'metaWeblog.getCategories', 'wp.getTags' ];

		for ( const methodName of taxonomyMethods ) {
			const body = await callXmlRpc( request, methodName );

			expectXmlRpcFault( body, -32601 );
		}
	} );

	test( 'an unrelated method still works', async ( { request } ) => {
		// Control: proves the fault assertions above aren't passing against a
		// broken endpoint.
		const body = await callXmlRpc( request, 'wp.getOptions', [ 0, ADMIN_USERNAME, ADMIN_PASSWORD ] );

		expect( body ).toContain( '<methodResponse>' );
		expect( body ).not.toContain( '<name>faultCode</name>' );
		expect( body ).not.toContain( '-32601' );
	} );

	test( 'regression guard: wp.deleteCategory is removed', async ( { request } ) => {
		const body = await callXmlRpc( request, 'wp.deleteCategory' );

		expectXmlRpcFault( body, -32601 );
	} );

	test( 'N3: system.* introspection methods remain callable (documented constraint, not a defect)', async ( {
		request,
	} ) => {
		const systemMethods = [
			'system.listMethods',
			'system.multicall',
			'system.getCapabilities',
		];

		for ( const methodName of systemMethods ) {
			const body = await callXmlRpc( request, methodName );

			expect( body ).not.toContain( '<name>faultCode</name>' );
			expect( body ).not.toContain( '-32601' );
		}
	} );
} );
