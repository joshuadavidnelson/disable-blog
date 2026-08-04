/**
 * Redirect assertions against the request layer, never against browser
 * navigation — Chromium caches 301s aggressively and can keep serving a
 * stale redirect to `page.goto()` after Reading settings change mid-suite,
 * where a plain `request.get()` with redirect-following disabled always
 * reads what WordPress actually sent back.
 *
 * The `Location` header is compared exactly, with no trailing-slash
 * normalization: page redirects target the site root *with* a slash
 * (`get_permalink( page_on_front )`), feed redirects target it *without*
 * one (`home_url()`) — both correct, so don't trim or append a slash
 * anywhere in this file.
 */

import { expect } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';

/**
 * Assert that requesting `from` returns exactly `status`, with a `Location`
 * header exactly equal to `to`. Issued with redirect-following disabled, to
 * inspect the redirect response itself rather than wherever it leads.
 *
 * @param request Playwright API request context (the `request` fixture, or `context.request`).
 * @param from    URL (relative or absolute) to request.
 * @param to      Exact expected `Location` header value. Compared verbatim — see the file docblock on trailing slashes.
 * @param status  Expected HTTP status. Defaults to `301`.
 */
export async function expectRedirect(
	request: APIRequestContext,
	from: string,
	to: string,
	status = 301
): Promise< void > {
	const response = await request.get( from, { maxRedirects: 0 } );
	const actualStatus = response.status();
	const actualLocation = response.headers()[ 'location' ] ?? null;

	const message =
		`Redirect assertion failed for ${ from }\n` +
		`  expected status:   ${ status }\n` +
		`  actual status:     ${ actualStatus }\n` +
		`  expected Location: ${ to }\n` +
		`  actual Location:   ${ actualLocation ?? '(no Location header)' }`;

	expect( actualStatus, message ).toBe( status );
	expect( actualLocation, message ).toBe( to );
}

/**
 * Assert that requesting `url` returns exactly `status`.
 *
 * Also issued with redirect-following disabled, so an unexpected redirect
 * surfaces here as a status mismatch (e.g. expected 200, got 301) rather than
 * being silently followed to whatever page it targets.
 *
 * @param request Playwright API request context.
 * @param url     URL (relative or absolute) to request.
 * @param status  Expected HTTP status.
 */
export async function expectStatus(
	request: APIRequestContext,
	url: string,
	status: number
): Promise< void > {
	const response = await request.get( url, { maxRedirects: 0 } );
	const actualStatus = response.status();
	const actualLocation = response.headers()[ 'location' ] ?? null;

	const message =
		`Status assertion failed for ${ url }\n` +
		`  expected status: ${ status }\n` +
		`  actual status:   ${ actualStatus }` +
		( actualLocation ? `\n  actual Location: ${ actualLocation }` : '' );

	expect( actualStatus, message ).toBe( status );
}

/**
 * Assert that requesting `url` returns a plain `200` — i.e. that Disable
 * Blog is NOT redirecting it. The common negative-space assertion: "this URL
 * used to redirect before the toggle was flipped off; confirm it no longer
 * does."
 *
 * @param request Playwright API request context.
 * @param url     URL (relative or absolute) to request.
 */
export async function expectNoRedirect(
	request: APIRequestContext,
	url: string
): Promise< void > {
	await expectStatus( request, url, 200 );
}
