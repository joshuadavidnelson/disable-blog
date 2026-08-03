/**
 * The `dwpb_disabled_xmlrpc_methods` escape hatch, exercised via the
 * `dwpb-test-xmlrpc.php` mu-plugin fixture rather than the plugin's shipped
 * default (an array of method names to remove).
 *
 * COVERAGE: `Disable_Blog_Public::get_disabled_xmlrpc_methods()`
 * (includes/class-disable-blog-public.php) documents this filter as
 * "Return false to disable this functionality entirely and keep all methods
 * in place." -- and its implementation backs that literally:
 * `is_array( $methods_to_remove ) ? array_filter( ... ) : false`, feeding
 * `Disable_Blog_Public::xmlrpc_methods()`'s
 * `! empty( $methods_to_remove ) && is_array( $methods_to_remove )` guard.
 * A non-array return short-circuits the ENTIRE removal loop -- every method
 * on the plugin's fixed list (`wp.getPosts`, `pingback.ping`, ...), not just
 * the taxonomy-conditional subset `filters/cpt-branches.spec.ts` exercises.
 * The fixture forces exactly that: `dwpb_disabled_xmlrpc_methods` returns
 * `false` when switched on.
 *
 * RAW XML-RPC, NO LIBRARY: same approach as `rest-xmlrpc/xmlrpc.spec.ts` and
 * `filters/cpt-branches.spec.ts` -- `methodCallXml()`/`callXmlRpc()` below
 * hand-build the minimal `methodCall` XML core expects. See that file's
 * docblock for why every fault-code assertion can safely ignore whatever
 * params a real client would normally send: method lookup
 * (`IXR_Server::hasMethod()`) happens purely by name, before any parameter
 * validation or authentication check runs.
 *
 * WHY ONLY `-32601` IS ASSERTED FOR `pingback.ping`: `wp.getPosts` is called
 * with real admin credentials and asserted to return a genuine success
 * response (no fault at all), since valid auth plus valid (empty/default)
 * params is enough for that method to fully succeed. `pingback.ping` takes
 * two required URL params this file does not attempt to construct a
 * realistic pair for, so calling it with an empty param list can legitimately
 * fault for an unrelated reason (a pingback-specific error code, not
 * `-32601`). The assertion below therefore only proves the ONE thing this
 * fixture is responsible for -- the method is no longer removed -- not that
 * an intentionally-incomplete call fully succeeds.
 *
 * ANONYMOUS-CAPABLE: XML-RPC has no browser session/cookie concept, so this
 * file uses the plain `request` fixture throughout rather than opting into an
 * empty `storageState` -- matching `rest-xmlrpc/xmlrpc.spec.ts`'s same
 * choice, see that file's docblock.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { APIRequestContext } from '@playwright/test';

/**
 * Internal dependencies
 */
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

/**
 * wp-env's built-in administrator. Fixed, throwaway local credentials -- not
 * a secret worth centralizing further than this file. Mirrors
 * `rest-xmlrpc/xmlrpc.spec.ts`.
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

test.describe( 'filters: XML-RPC restore (dwpb_disabled_xmlrpc_methods)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setFixtures( requestUtils, { [ FIXTURE_TOGGLES.xmlrpcRestore ]: true } );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.xmlrpcRestore ] );
	} );

	test( 'wp.getPosts is restored', async ( { request } ) => {
		// On the plugin's fixed removal list unconditionally -- the strongest
		// possible proof the escape hatch really does bypass the ENTIRE
		// removal loop, not just the taxonomy-conditional subset.
		const body = await callXmlRpc( request, 'wp.getPosts', [
			0,
			ADMIN_USERNAME,
			ADMIN_PASSWORD,
		] );

		expect( body ).not.toContain( '<name>faultCode</name>' );
		expect( body ).not.toContain( '-32601' );
	} );

	test( 'pingback.ping is restored', async ( { request } ) => {
		// See the file docblock's "WHY ONLY -32601 IS ASSERTED" note: no
		// realistic params are sent, so only the removed-method fault code is
		// checked, not full success.
		const body = await callXmlRpc( request, 'pingback.ping' );

		expect( body ).not.toContain( '-32601' );
	} );
} );
