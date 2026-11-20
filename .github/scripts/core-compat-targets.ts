/**
 * Resolves the WordPress targets for core-compat.yml and writes the job
 * matrix to the `matrix` step output.
 *
 * Env: DWPB_WP_INPUT (the workflow_dispatch `wp` input; empty on schedule
 * and pull_request), GITHUB_OUTPUT.
 */
import { setOutput, isMain } from './actions.ts';

export const VERSION_CHECK_URL = 'https://api.wordpress.org/core/version-check/1.7/';
export const BETA_CHECK_URL = `${ VERSION_CHECK_URL }?channel=beta`;

export interface Target {
	name: string;
	wp: string;
	php: string;
}

export interface Matrix {
	include: Target[];
}

/**
 * The download URL of the first offer with the given `response`, or an empty
 * string when no offer matches or it has no download.
 */
export function offerDownload( feed: unknown, response: string ): string {
	const offers = ( feed as { offers?: unknown } | null )?.offers;
	if ( ! Array.isArray( offers ) ) {
		throw new Error( 'The version-check feed has no offers list.' );
	}
	const offer = offers.find(
		( item: { response?: unknown } | null ) => item?.response === response
	) as { download?: unknown } | undefined;
	return typeof offer?.download === 'string' ? offer.download : '';
}

export function customMatrix( wp: string ): Matrix {
	return { include: [ { name: 'custom', wp, php: '8.4' } ] };
}

/**
 * The weekly latest leg tests the newest released core, not the zip pinned in
 * .wp-env.json, so a new release surfaces as an issue. An empty `beta` means
 * no beta or RC is out, so there is no beta leg.
 */
export function scheduledMatrix( latest: string, beta: string ): Matrix {
	return {
		include: [
			{ name: 'latest', wp: latest, php: '8.3' },
			...( beta !== '' ? [ { name: 'beta', wp: beta, php: '8.4' } ] : [] ),
			{ name: 'master', wp: 'master', php: '8.4' },
		],
	};
}

export interface Deps {
	env: NodeJS.ProcessEnv;
	fetch: typeof fetch;
	log: ( line: string ) => void;
}

// Any fetch or parse failure throws and fails the step, so an unreachable
// feed never silently drops a leg.
async function fetchJson( fetchFn: typeof fetch, url: string ): Promise<unknown> {
	const response = await fetchFn( url );
	if ( ! response.ok ) {
		throw new Error( `GET ${ url } returned HTTP ${ response.status }.` );
	}
	return response.json();
}

/** The matrix to run, or null after logging an error annotation. */
export async function resolveTargets( deps: Deps ): Promise<Matrix | null> {
	const input = deps.env.DWPB_WP_INPUT ?? '';
	if ( input !== '' ) {
		return customMatrix( input );
	}

	const latest = offerDownload( await fetchJson( deps.fetch, VERSION_CHECK_URL ), 'upgrade' );
	if ( latest === '' ) {
		deps.log( '::error title=Targets::The version-check feed has no upgrade offer.' );
		return null;
	}

	// The beta channel offers a "development" release only while a beta or
	// RC is out.
	const beta = offerDownload( await fetchJson( deps.fetch, BETA_CHECK_URL ), 'development' );
	return scheduledMatrix( latest, beta );
}

export async function main( deps: Deps ): Promise<number> {
	const matrix = await resolveTargets( deps );
	if ( ! matrix ) {
		return 1;
	}
	const json = JSON.stringify( matrix );
	deps.log( json );
	setOutput( 'matrix', json, deps.env );
	return 0;
}

if ( isMain( import.meta.url ) ) {
	process.exitCode = await main( { env: process.env, fetch, log: console.log } );
}
