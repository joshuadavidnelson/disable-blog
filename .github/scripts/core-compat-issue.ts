/**
 * Files or updates the core-compat issue for one leg of core-compat.yml.
 *
 * Env: FULL_VERSION (the version wp-env reported; empty when the environment
 * failed), MATRIX_WP, ENV_OUTCOME, SNAPSHOT, SUITE, DEBUGLOG, RUN_URL and
 * GH_TOKEN for the gh CLI. Reads artifacts/surface-diff.md when present.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, statSync } from 'node:fs';
import { posix } from 'node:path';

import { isMain } from './actions.ts';

export const LABEL = 'core-compat';
export const LABEL_COLOR = 'd93f0b';
export const LABEL_DESCRIPTION = 'A WordPress release changed something Disable Blog depends on';
export const REPORT_PATH = 'artifacts/surface-diff.md';
export const TRUNK_VERSION_URL =
	'https://raw.githubusercontent.com/WordPress/WordPress/master/wp-includes/version.php';
// GitHub caps an issue body at 65536 characters.
export const BODY_LIMIT = 65000;
export const TRUNCATION_NOTE = '_Report truncated; see the run artifact for the full diff._';

/**
 * Drops the nightly build number (7.3-alpha-61234 becomes 7.3-alpha,
 * 7.2-RC2-65716 becomes 7.2-RC2) so one issue tracks a target from week to
 * week. Anything else is returned unchanged.
 */
export function normalizeVersion( version: string ): string {
	return version.replace( /^([0-9]+\.[0-9]+(\.[0-9]+)?-[A-Za-z]+[0-9]*)-[0-9]+$/, '$1' );
}

/** X.Y[-tag] from a core zip URL or file name, or the input as-is. */
export function versionFromWp( wp: string ): string {
	let name = posix.basename( wp );
	if ( name.startsWith( 'wordpress-' ) ) {
		name = name.slice( 'wordpress-'.length );
	}
	if ( name.endsWith( '.zip' ) ) {
		name = name.slice( 0, -'.zip'.length );
	}
	return name;
}

/** The $wp_version value from a wp-includes/version.php source, or ''. */
export function parseVersionPhp( source: string ): string {
	const match = /^\$wp_version = '([^'\n]+)';/m.exec( source );
	return match ? match[ 1 ] : '';
}

/** Only a failed environment on master needs trunk's version for the title. */
export function needsTrunkVersion( fullVersion: string, matrixWp: string ): boolean {
	return fullVersion === '' && matrixWp === 'master';
}

/**
 * The version in the issue title. Without a running site it comes from the
 * matrix value, and a branch name has no version, so master uses trunk's
 * version.php when that could be read.
 */
export function targetVersion( fullVersion: string, matrixWp: string, trunkVersion = '' ): string {
	if ( fullVersion !== '' ) {
		return normalizeVersion( fullVersion );
	}
	if ( needsTrunkVersion( fullVersion, matrixWp ) && trunkVersion !== '' ) {
		return normalizeVersion( trunkVersion );
	}
	return normalizeVersion( versionFromWp( matrixWp ) );
}

export function issueTitle( target: string ): string {
	return `Core compat: WordPress ${ target }`;
}

export interface RunInfo {
	envOutcome: string;
	snapshot: string;
	suite: string;
	debuglog: string;
	fullVersion: string;
	matrixWp: string;
	runUrl: string;
}

export function bodyHead( info: RunInfo ): string {
	return (
		`Environment: ${ info.envOutcome }. Snapshot check: ${ info.snapshot }. Browser suite: ${ info.suite }. PHP fatals: ${ info.debuglog }.\n` +
		'\n' +
		`WordPress: ${ info.fullVersion || info.matrixWp }\n` +
		'\n' +
		`Run: ${ info.runUrl }\n` +
		'\n'
	);
}

/**
 * The head plus the surface report, truncated to fit under `limit`. Lengths
 * count code points. Trailing newlines in the report are dropped and one is
 * added back. A null report (no file, or an empty one) leaves just the head.
 */
export function buildIssueBody( head: string, report: string | null, limit = BODY_LIMIT ): string {
	if ( report === null ) {
		return head;
	}
	const text = report.replace( /\n+$/, '' );
	const room = limit - Array.from( head ).length - 100;
	const chars = Array.from( text );
	if ( chars.length > room ) {
		return `${ head }${ chars.slice( 0, room ).join( '' ) }\n\n${ TRUNCATION_NOTE }\n`;
	}
	return `${ head }${ text }\n`;
}

/** The number of the first issue with exactly this title, from gh's JSON. */
export function findIssue( issuesJson: string, title: string ): number | null {
	const issues = JSON.parse( issuesJson ) as Array<{ number?: number; title?: string }>;
	return issues.find( ( issue ) => issue.title === title )?.number ?? null;
}

export interface RunOptions {
	input?: string;
	quiet?: boolean;
}

/** Runs a command and returns its stdout; throws when it exits non-zero. */
export type Runner = ( command: string, args: string[], options?: RunOptions ) => string;

export interface Deps {
	env: NodeJS.ProcessEnv;
	fetch: typeof fetch;
	run: Runner;
	readReport: () => string | null;
	log: ( text: string ) => void;
}

/** Trunk's version, or '' when it cannot be fetched or parsed. */
export async function fetchTrunkVersion( fetchFn: typeof fetch ): Promise<string> {
	try {
		const response = await fetchFn( TRUNK_VERSION_URL );
		return response.ok ? parseVersionPhp( await response.text() ) : '';
	} catch {
		return '';
	}
}

export async function fileIssue( deps: Deps ): Promise<void> {
	const env = deps.env;
	const info: RunInfo = {
		envOutcome: env.ENV_OUTCOME ?? '',
		snapshot: env.SNAPSHOT ?? '',
		suite: env.SUITE ?? '',
		debuglog: env.DEBUGLOG ?? '',
		fullVersion: env.FULL_VERSION ?? '',
		matrixWp: env.MATRIX_WP ?? '',
		runUrl: env.RUN_URL ?? '',
	};

	const trunk = needsTrunkVersion( info.fullVersion, info.matrixWp )
		? await fetchTrunkVersion( deps.fetch )
		: '';
	const title = issueTitle( targetVersion( info.fullVersion, info.matrixWp, trunk ) );
	const body = buildIssueBody( bodyHead( info ), deps.readReport() );

	// The label usually exists already, so a failure here is expected.
	try {
		deps.run( 'gh', [ 'label', 'create', LABEL, '--color', LABEL_COLOR, '--description', LABEL_DESCRIPTION ], { quiet: true } );
	} catch {}

	const existing = findIssue(
		deps.run( 'gh', [ 'issue', 'list', '--label', LABEL, '--state', 'open', '--limit', '100', '--json', 'number,title' ] ),
		title
	);

	const out =
		existing !== null
			? deps.run( 'gh', [ 'issue', 'comment', String( existing ), '--body-file', '-' ], { input: body } )
			: deps.run( 'gh', [ 'issue', 'create', '--title', title, '--label', LABEL, '--body-file', '-' ], { input: body } );
	deps.log( out );
}

function readReport(): string | null {
	try {
		return statSync( REPORT_PATH ).size > 0 ? readFileSync( REPORT_PATH, 'utf8' ) : null;
	} catch {
		return null;
	}
}

const run: Runner = ( command, args, options = {} ) =>
	execFileSync( command, args, {
		encoding: 'utf8',
		input: options.input,
		stdio: [ 'pipe', 'pipe', options.quiet ? 'ignore' : 'inherit' ],
	} );

if ( isMain( import.meta.url ) ) {
	await fileIssue( {
		env: process.env,
		fetch,
		run,
		readReport,
		log: ( text ) => process.stdout.write( text ),
	} );
}
