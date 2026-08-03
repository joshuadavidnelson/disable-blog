/**
 * XML-RPC endpoint behaviour, default plugin state.
 *
 * COVERAGE: `Disable_Blog_Public::xmlrpc_methods()`, hooked on the
 * `xmlrpc_methods` filter (`WP_XMLRPC_Server::__construct()` applies it to
 * `$this->methods` before any request is dispatched). It unconditionally
 * `unset()`s a fixed list of methods
 * (`Disable_Blog_Public::get_disabled_xmlrpc_methods()`) plus, conditionally,
 * the taxonomy-related methods (`wp.newCategory`, `metaWeblog.getCategories`,
 * `wp.getTags`, ...) when `dwpb_post_types_with_tax( 'category' | 'post_tag' )`
 * is falsy — true by default in a stock install, since only 'post' supports
 * either taxonomy out of the box.
 *
 * THE PLUGIN REMOVES METHODS, NOT THE ENDPOINT: `/xmlrpc.php` itself is
 * untouched — it is core's `IXR_Server`/`WP_XMLRPC_Server` machinery, always
 * present. Filtering a method out of `$this->methods` only means
 * `IXR_Server::hasMethod()` (`wp-includes/IXR/class-IXR-server.php`) returns
 * false for it, which `IXR_Server::call()` turns into a *fault response*, not
 * a connection-level failure:
 * `new IXR_Error( -32601, 'server error. requested method ' . $methodname .
 * ' does not exist.' )`. That lookup happens purely by method name, before
 * any parameter validation or authentication check runs — so every fault
 * test below can send an empty params list; the credentials or arguments a
 * real client would send for that method are never inspected.
 *
 * RAW XML-RPC, NO LIBRARY: this suite has no XML-RPC client dependency, so
 * `methodCallXml()` below hand-builds the minimal `methodCall` XML core
 * expects and `callXmlRpc()` posts it to `/xmlrpc.php` with
 * `Content-Type: text/xml`. wp-env's built-in administrator credentials are
 * `admin` / `password` (see `RequestUtils`'s defaults, also relied on by
 * `auth.setup.ts`).
 *
 * D4 FIX (`Disable_Blog_Public::get_disabled_xmlrpc_methods()`):
 * `wp.deleteCategory` IS covered below, exercising the CORRECTED behaviour —
 * that assertion is now a regression guard. The removal list's entry used to
 * be the misspelled literal `'wp.deleteeCategory'` (note the typo: three
 * e's), which didn't match the real `wp.deleteCategory` method name at all,
 * so a call to the real method succeeded instead of faulting -32601. Fixed
 * by correcting the spelling.
 *
 * N3 (not a defect — a documented constraint, see
 * `Disable_Blog_Public::get_disabled_xmlrpc_methods()`):
 * `system.listMethods`, `system.multicall`, and `system.getCapabilities` ARE
 * covered below too, but the assertion is that they remain callable, not
 * that they fault. They are present in the plugin's removal list, but
 * `IXR_Server::setCallbacks()` (wp-includes/IXR/class-IXR-server.php)
 * re-registers every `system.*` method AFTER the `xmlrpc_methods` filter
 * runs, so `unset()`ting them there has no effect — verified by direct
 * XML-RPC calls, all three remain callable no matter what the plugin does.
 * The N3 fix deletes those three dead entries from the plugin's removal list
 * and documents why, rather than pretending they can be removed — so the
 * "still callable" test below intentionally PASSES both before and after
 * the fix, unlike the regression guards above, which would have failed
 * before their fix landed. See its inline comment; do not mistake it for a
 * regression guard.
 *
 * ANONYMOUS-CAPABLE: XML-RPC has no browser session/cookie concept the way
 * the rest of this suite's public specs do, so this file uses the plain
 * `request` fixture throughout rather than opting into an empty
 * `storageState` — every call authenticates (or not) purely via the
 * XML-RPC params it sends, independent of `requestUtils`'s admin-authenticated
 * REST session.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { APIRequestContext } from '@playwright/test';

/**
 * wp-env's built-in administrator. Fixed, throwaway local credentials — not a
 * secret worth centralizing further than this file.
 */
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'password';

/**
 * Escape a string for safe inclusion inside XML-RPC `<string>` text content.
 *
 * @param value Raw string value.
 */
function escapeXml( value: string ): string {
	return value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}

/**
 * Render a single XML-RPC param, as `<string>` or `<int>` depending on type.
 *
 * Only the two scalar types this file's calls need — no array/struct
 * support, since every fault test sends an empty param list (method lookup
 * happens before params are ever read, see the file docblock) and the one
 * control test only needs strings and an int blog id.
 *
 * @param value Param value.
 */
function xmlRpcParam( value: string | number ): string {
	if ( 'number' === typeof value ) {
		return `<param><value><int>${ value }</int></value></param>`;
	}

	return `<param><value><string>${ escapeXml( value ) }</string></value></param>`;
}

/**
 * Build a minimal XML-RPC `methodCall` request body.
 *
 * @param methodName XML-RPC method name, e.g. `'wp.getPosts'`.
 * @param params     Ordered param values. Defaults to none.
 */
function methodCallXml( methodName: string, params: ( string | number )[] = [] ): string {
	const paramsXml = params.map( xmlRpcParam ).join( '' );

	return (
		'<?xml version="1.0"?>' +
		`<methodCall><methodName>${ escapeXml( methodName ) }</methodName>` +
		`<params>${ paramsXml }</params></methodCall>`
	);
}

/**
 * POST an XML-RPC `methodCall` to `/xmlrpc.php` and return the raw response text.
 *
 * @param request    Playwright API request context.
 * @param methodName XML-RPC method name.
 * @param params     Ordered param values. Defaults to none.
 */
async function callXmlRpc(
	request: APIRequestContext,
	methodName: string,
	params: ( string | number )[] = []
): Promise< string > {
	const response = await request.post( '/xmlrpc.php', {
		headers: { 'Content-Type': 'text/xml' },
		data: methodCallXml( methodName, params ),
	} );

	return response.text();
}

/**
 * Assert that an XML-RPC response body is a fault with the given fault code.
 *
 * A removed method's fault is `-32601` with a message like "requested method
 * <name> does not exist." — see the file docblock.
 *
 * @param body     Raw XML-RPC response text.
 * @param faultCode Expected `faultCode` value.
 */
function expectXmlRpcFault( body: string, faultCode: number ): void {
	expect( body ).toContain( '<name>faultCode</name>' );
	expect( body ).toContain( `<int>${ faultCode }</int>` );
}

test.describe( 'XML-RPC: default state', () => {
	test( 'the endpoint still responds to GET', async ( { request } ) => {
		// The plugin removes methods, it does not disable the endpoint —
		// core's IXR_Server::serve() itself rejects non-POST requests with
		// this exact text (wp-includes/IXR/class-IXR-server.php), regardless
		// of anything this plugin does.
		const response = await request.get( '/xmlrpc.php', { maxRedirects: 0 } );
		const body = await response.text();

		expect( body ).toContain( 'XML-RPC server accepts POST requests only.' );

		// On its own line: IXR_Server::serve() sends this as a 405 with an
		// `Allow: POST` header, which is a secondary signal compared to the
		// body text above — if this ever needs adjusting independently of
		// the body assertion, it should be easy to find.
		expect( response.status() ).toBe( 405 );
	} );

	test( 'wp.getPosts is removed', async ( { request } ) => {
		const body = await callXmlRpc( request, 'wp.getPosts' );

		expectXmlRpcFault( body, -32601 );
	} );

	test( 'pingback.ping and demo.sayHello are removed', async ( { request } ) => {
		// demo.sayHello is a good canary here: it needs no auth and no
		// meaningful params, so a fault on it can only mean the method
		// itself is gone, not a credentials/argument problem.
		//
		// system.multicall is deliberately NOT included here — it can never
		// fault -32601 regardless of what this plugin does, see the N3 test
		// below and the file docblock's "N3" note.
		const removedMethods = [ 'pingback.ping', 'demo.sayHello' ];

		for ( const methodName of removedMethods ) {
			const body = await callXmlRpc( request, methodName );

			expectXmlRpcFault( body, -32601 );
		}
	} );

	test( 'taxonomy methods are removed by default', async ( { request } ) => {
		// No custom post type in this environment declares 'category' or
		// 'post_tag' support, so dwpb_post_types_with_tax() is falsy for
		// both and get_disabled_xmlrpc_methods() includes the taxonomy
		// methods on top of its fixed list.
		const taxonomyMethods = [ 'metaWeblog.getCategories', 'wp.getTags' ];

		for ( const methodName of taxonomyMethods ) {
			const body = await callXmlRpc( request, methodName );

			expectXmlRpcFault( body, -32601 );
		}
	} );

	test( 'an unrelated method still works', async ( { request } ) => {
		// Control: without this, every fault assertion above could just as
		// easily be passing against a totally broken/misconfigured
		// endpoint. wp.getOptions is not in get_disabled_xmlrpc_methods()'s
		// list, so a valid admin call must return a real methodResponse.
		const body = await callXmlRpc( request, 'wp.getOptions', [ 0, ADMIN_USERNAME, ADMIN_PASSWORD ] );

		expect( body ).toContain( '<methodResponse>' );
		expect( body ).not.toContain( '<name>faultCode</name>' );
		expect( body ).not.toContain( '-32601' );
	} );

	test( 'regression guard: wp.deleteCategory is removed', async ( { request } ) => {
		// D4: get_disabled_xmlrpc_methods() used to list the misspelled
		// 'wp.deleteeCategory' (three e's), which never matched core's real
		// wp.deleteCategory method name, so a call to the real method
		// succeeded instead of faulting -32601. Fixed by correcting the
		// spelling.
		const body = await callXmlRpc( request, 'wp.deleteCategory' );

		expectXmlRpcFault( body, -32601 );
	} );

	test( 'N3: system.* introspection methods remain callable (documented constraint, not a defect)', async ( {
		request,
	} ) => {
		// ⚠️ NOT A DEFECT TEST — this passes both before and after the N3 fix
		// and is meant to keep passing forever, see the file docblock's "N3"
		// note. Disable_Blog_Public::get_disabled_xmlrpc_methods() no longer
		// lists these three methods at all (the N3 fix deleted them), but
		// wp-includes/IXR/class-IXR-server.php's setCallbacks() re-registers
		// every system.* method after the xmlrpc_methods filter runs
		// regardless, so no filter this plugin applies could ever remove
		// them; the fix documents that constraint rather than pretending
		// otherwise.
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
