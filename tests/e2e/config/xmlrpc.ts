/**
 * XML-RPC request/response plumbing for `/xmlrpc.php`, shared by every spec
 * that talks to the endpoint directly: `rest-xmlrpc/xmlrpc.spec.ts`,
 * `filters/cpt-branches.spec.ts` (taxonomy methods restored via
 * `dwpb_test_cpt_enabled`), and `filters/xmlrpc-restore.spec.ts` (the
 * `dwpb_disabled_xmlrpc_methods` escape hatch). Centralized here so a
 * wire-format change touches one file instead of three copies.
 */

import { expect } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';

// wp-env's built-in administrator; throwaway local credentials.
export const ADMIN_USERNAME = 'admin';
export const ADMIN_PASSWORD = 'password';

/**
 * Escape a string for safe inclusion inside XML-RPC `<string>` text content.
 *
 * @param value Raw string value.
 */
export function escapeXml( value: string ): string {
	return value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}

/**
 * An XML-RPC param value: a string, an int, or a struct of them.
 */
export type XmlRpcValue = string | number | { [ member: string ]: string | number };

/**
 * Render a single XML-RPC value, as `<string>`, `<int>` or `<struct>`
 * depending on type.
 *
 * @param value Param value.
 */
function xmlRpcValue( value: XmlRpcValue ): string {
	if ( 'number' === typeof value ) {
		return `<value><int>${ value }</int></value>`;
	}

	if ( 'string' === typeof value ) {
		return `<value><string>${ escapeXml( value ) }</string></value>`;
	}

	const members = Object.entries( value )
		.map( ( [ name, member ] ) => `<member><name>${ escapeXml( name ) }</name>${ xmlRpcValue( member ) }</member>` )
		.join( '' );

	return `<value><struct>${ members }</struct></value>`;
}

/**
 * Render a single XML-RPC param.
 *
 * @param value Param value.
 */
export function xmlRpcParam( value: XmlRpcValue ): string {
	return `<param>${ xmlRpcValue( value ) }</param>`;
}

/**
 * Build a minimal XML-RPC methodCall request body.
 *
 * @param methodName XML-RPC method name.
 * @param params     Ordered param values.
 */
export function methodCallXml( methodName: string, params: XmlRpcValue[] = [] ): string {
	const paramsXml = params.map( xmlRpcParam ).join( '' );

	return (
		'<?xml version="1.0"?>' +
		`<methodCall><methodName>${ escapeXml( methodName ) }</methodName>` +
		`<params>${ paramsXml }</params></methodCall>`
	);
}

/**
 * POST an XML-RPC methodCall to `/xmlrpc.php` and return the raw response text.
 *
 * @param request    Playwright API request context (the `request` fixture).
 * @param methodName XML-RPC method name.
 * @param params     Ordered param values, per {@link methodCallXml}.
 */
export async function callXmlRpc(
	request: APIRequestContext,
	methodName: string,
	params: XmlRpcValue[] = []
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
 * @param body      Raw XML-RPC response body, as returned by {@link callXmlRpc}.
 * @param faultCode Expected IXR fault code.
 */
export function expectXmlRpcFault( body: string, faultCode: number ): void {
	expect( body ).toContain( '<name>faultCode</name>' );
	expect( body ).toContain( `<int>${ faultCode }</int>` );
}
