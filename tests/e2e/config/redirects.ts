/**
 * Redirect assertions against the request layer, never against browser
 * navigation.
 *
 * WHY THE REQUEST LAYER: Chromium caches 301 responses aggressively (per the
 * HTTP cache rules for permanent redirects) and will keep serving a cached
 * redirect to a `page.goto()` call even after the Reading settings that
 * produced it have changed mid-suite — a later spec's navigation-based
 * assertion would then silently observe a stale result instead of the
 * current one. Issuing a plain `request.get()` with redirect-following
 * disabled sidesteps the browser cache entirely and reads exactly what
 * WordPress sent back for that one request.
 *
 * ⚠️ TRAILING SLASHES ARE NOT NORMALIZED HERE, ON PURPOSE. This suite
 * deliberately distinguishes two different, both-correct redirect targets:
 *   - page redirects resolve to `get_permalink( page_on_front )`, which DOES
 *     carry a trailing slash, e.g. `http://localhost:8889/home/`;
 *   - the feed redirect resolves to `home_url()`, which does NOT,
 *     e.g. `http://localhost:8889`.
 * `dwpb-test/v1/setup` returns both verbatim for exactly this reason (see
 * `tests/e2e/fixtures/dwpb-test-api.php`). Do not trim or append a slash to
 * either side of a comparison in this file, or to a value passed into it —
 * doing so would make a real regression (e.g. a redirect target losing or
 * gaining a slash it shouldn't) invisible to every assertion that runs
 * through here.
 */

/**
 * External dependencies
 */
import { expect } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';

/**
 * Assert that requesting `from` returns exactly `status`, with a `Location`
 * header exactly equal to `to`.
 *
 * Always issued with redirect-following disabled (`maxRedirects: 0`): the
 * point is to inspect the single redirect response WordPress produced, not
 * whatever page the request client would land on after chasing it.
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
