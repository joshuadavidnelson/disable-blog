import { appendFileSync, realpathSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

/**
 * Appends a step output to the file GitHub Actions reads after the step.
 * Values must be single-line.
 */
export function setOutput(
	name: string,
	value: string,
	env: NodeJS.ProcessEnv = process.env
): void {
	const file = env.GITHUB_OUTPUT;
	if ( ! file ) {
		throw new Error( 'GITHUB_OUTPUT is not set.' );
	}
	appendFileSync( file, `${ name }=${ value }\n` );
}

/**
 * True when the module at `moduleUrl` is the script node was started with,
 * so a script runs its entry point only when invoked directly, not when a
 * test imports it.
 */
export function isMain( moduleUrl: string ): boolean {
	const script = process.argv[ 1 ];
	if ( ! script ) {
		return false;
	}
	// import.meta.url holds the real path, so resolve symlinks such as
	// macOS /tmp before comparing.
	return pathToFileURL( realpathSync( script ) ).href === moduleUrl;
}
