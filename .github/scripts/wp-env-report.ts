/**
 * Reports the WordPress and PHP versions wp-env is running, and writes the
 * WordPress version to the `version` step output for the e2e-env action.
 */
import { execFileSync } from 'node:child_process';

import { setOutput, isMain } from './actions.ts';

/**
 * The version from `wp core version` output: the last line, without carriage
 * returns, since wp-env prints its own status lines around the command's.
 */
export function parseCoreVersion( output: string ): string {
	const text = output.endsWith( '\n' ) ? output.slice( 0, -1 ) : output;
	return text.slice( text.lastIndexOf( '\n' ) + 1 ).replaceAll( '\r', '' );
}

export function isCoreVersion( version: string ): boolean {
	return /^[0-9]+\.[0-9]+/.test( version );
}

export interface Deps {
	env: NodeJS.ProcessEnv;
	/** Runs a command and returns its stdout; throws when it exits non-zero. */
	capture: ( command: string, args: string[] ) => string;
	/** Runs a command with its output going straight to the log. */
	stream: ( command: string, args: string[] ) => void;
	log: ( line: string ) => void;
}

export function report( deps: Deps ): number {
	const version = parseCoreVersion(
		deps.capture( 'npx', [ 'wp-env', 'run', 'tests-cli', '--', 'wp', 'core', 'version' ] )
	);
	if ( ! isCoreVersion( version ) ) {
		deps.log( `::error title=wp-env::Unexpected output from wp core version: ${ version }` );
		return 1;
	}
	deps.log( `WordPress ${ version }` );
	setOutput( 'version', version, deps.env );
	deps.stream( 'npx', [ 'wp-env', 'run', 'tests-cli', '--', 'php', '-r', 'echo PHP_VERSION, PHP_EOL;' ] );
	return 0;
}

if ( isMain( import.meta.url ) ) {
	process.exitCode = report( {
		env: process.env,
		capture: ( command, args ) =>
			execFileSync( command, args, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'inherit' ] } ),
		stream: ( command, args ) => {
			execFileSync( command, args, { stdio: [ 'ignore', 'inherit', 'inherit' ] } );
		},
		log: console.log,
	} );
}
