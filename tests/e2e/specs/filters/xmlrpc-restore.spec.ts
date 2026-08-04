/**
 * The `dwpb_disabled_xmlrpc_methods` escape hatch, via the
 * `dwpb-test-xmlrpc.php` mu-plugin fixture. Returning `false` from this
 * filter (instead of an array) short-circuits the entire XML-RPC removal
 * loop, restoring every method on the plugin's fixed list — not just the
 * taxonomy-conditional subset `filters/cpt-branches.spec.ts` exercises.
 *
 * `demo.sayHello` is used instead of `pingback.ping` to prove the restore:
 * WordPress trunk removes `pingback.ping` itself on non-production
 * environments, independent of any plugin filter.
 *
 * No browser session concept applies to XML-RPC, so this file uses the plain
 * `request` fixture throughout.
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

// wp-env's built-in administrator; throwaway local credentials.
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'password';

// Escape a string for safe inclusion inside XML-RPC `<string>` text content.
function escapeXml( value: string ): string {
	return value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}

// Render a single XML-RPC param, as `<string>` or `<int>` depending on type.
function xmlRpcParam( value: string | number ): string {
	if ( 'number' === typeof value ) {
		return `<param><value><int>${ value }</int></value></param>`;
	}

	return `<param><value><string>${ escapeXml( value ) }</string></value></param>`;
}

// Build a minimal XML-RPC methodCall request body.
function methodCallXml( methodName: string, params: ( string | number )[] = [] ): string {
	const paramsXml = params.map( xmlRpcParam ).join( '' );

	return (
		'<?xml version="1.0"?>' +
		`<methodCall><methodName>${ escapeXml( methodName ) }</methodName>` +
		`<params>${ paramsXml }</params></methodCall>`
	);
}

// POST an XML-RPC methodCall to /xmlrpc.php and return the raw response text.
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
