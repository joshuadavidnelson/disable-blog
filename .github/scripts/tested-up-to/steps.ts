import { appendFile, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

import {
	rewriteReadmeMd,
	rewriteReadmeTxt,
	rewriteWpEnv,
	verifyReadmeMd,
	verifyReadmeTxt,
	verifyWpEnv,
} from './files.ts';
import { assertVersion, latestFromStableCheck, minorOf } from './version.ts';

export const STABLE_CHECK_URL = 'https://api.wordpress.org/core/stable-check/1.0/';

export type Fetch = ( url: string ) => Promise<Response>;

export interface Versions {
	full: string;
	minor: string;
}

/** Fetches the latest WordPress release and derives its minor version. */
export async function resolveLatest( fetchImpl: Fetch = fetch ): Promise<Versions> {
	const response = await fetchImpl( STABLE_CHECK_URL );
	if ( ! response.ok ) {
		throw new Error( `${ STABLE_CHECK_URL } returned HTTP ${ response.status }` );
	}

	const full = assertVersion( latestFromStableCheck( await response.json() ) );

	return { full, minor: minorOf( full ) };
}

export async function writeOutputs( outputPath: string, versions: Versions ): Promise<void> {
	await appendFile( outputPath, `full=${ versions.full }\nminor=${ versions.minor }\n` );
}

/** Reads FULL and MINOR, as the resolve step's outputs pass them in. */
export function versionsFromEnv( env: NodeJS.ProcessEnv ): Versions {
	const full = assertVersion( env.FULL ?? '' );
	const minor = env.MINOR ?? '';
	if ( minor !== minorOf( full ) ) {
		throw new Error( `MINOR '${ minor }' is not the minor release of ${ full }` );
	}

	return { full, minor };
}

async function edit( path: string, rewrite: ( text: string ) => string ): Promise<void> {
	await writeFile( path, rewrite( await readFile( path, 'utf8' ) ) );
}

export async function updateFiles( dir: string, { full, minor }: Versions ): Promise<void> {
	await edit( join( dir, 'readme.txt' ), ( text ) => rewriteReadmeTxt( text, minor ) );
	await edit( join( dir, 'README.md' ), ( text ) => rewriteReadmeMd( text, full ) );
	await edit( join( dir, '.wp-env.json' ), ( text ) => rewriteWpEnv( text, full ) );
}

export async function verifyFiles( dir: string, { full, minor }: Versions ): Promise<void> {
	verifyReadmeTxt( await readFile( join( dir, 'readme.txt' ), 'utf8' ), minor );
	verifyReadmeMd( await readFile( join( dir, 'README.md' ), 'utf8' ), full );
	verifyWpEnv( await readFile( join( dir, '.wp-env.json' ), 'utf8' ), full );
}
