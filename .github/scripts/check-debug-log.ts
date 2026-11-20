/**
 * Checks the WordPress debug log collected by the E2E jobs. PHP warnings,
 * deprecations and notices produce a warning annotation; a PHP fatal fails
 * the step. A missing or empty log passes.
 *
 * Env: DWPB_WP_LABEL and DWPB_PHP_VERSION name the leg in the warning.
 */
import { readFileSync, statSync } from 'node:fs';

import { isMain } from './actions.ts';

export const LOG_PATH = 'artifacts/debug.log';
const DIAGNOSTIC = /PHP (Warning|Deprecated|Notice)/;
const FATAL = 'PHP Fatal error';

export function logLines( text: string ): string[] {
	const lines = text.split( '\n' );
	if ( lines.at( -1 ) === '' ) {
		lines.pop();
	}
	return lines;
}

/** Unique lines in byte order, matching `sort -u` under a C or C.UTF-8 locale. */
export function uniqueSorted( lines: string[] ): string[] {
	return [ ...new Set( lines ) ].sort( ( a, b ) => Buffer.compare( Buffer.from( a ), Buffer.from( b ) ) );
}

export interface DebugLogResult {
	output: string[];
	failed: boolean;
}

export function checkDebugLog( text: string, leg: string ): DebugLogResult {
	const lines = logLines( text );
	const output: string[] = [];

	const diagnostics = lines.filter( ( line ) => DIAGNOSTIC.test( line ) );
	if ( diagnostics.length > 0 ) {
		output.push( `::warning title=PHP diagnostics::${ leg } logged PHP diagnostics; see the debug.log artifact.` );
		output.push( ...uniqueSorted( diagnostics ).slice( 0, 40 ) );
	}

	const fatals = lines.filter( ( line ) => line.includes( FATAL ) );
	if ( fatals.length > 0 ) {
		output.push( '::error title=PHP fatal::A PHP fatal error was logged during the run.' );
		output.push( ...uniqueSorted( fatals ).slice( 0, 20 ) );
	}

	return { output, failed: fatals.length > 0 };
}

export function legName( env: NodeJS.ProcessEnv ): string {
	return `WP ${ env.DWPB_WP_LABEL ?? '' } / PHP ${ env.DWPB_PHP_VERSION ?? '' }`;
}

function readLog(): string | null {
	try {
		return statSync( LOG_PATH ).size > 0 ? readFileSync( LOG_PATH, 'utf8' ) : null;
	} catch {
		return null;
	}
}

if ( isMain( import.meta.url ) ) {
	const text = readLog();
	if ( text !== null ) {
		const result = checkDebugLog( text, legName( process.env ) );
		for ( const line of result.output ) {
			console.log( line );
		}
		process.exitCode = result.failed ? 1 : 0;
	}
}
