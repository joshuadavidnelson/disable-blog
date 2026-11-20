import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
	BODY_LIMIT,
	LABEL,
	TRUNCATION_NOTE,
	TRUNK_VERSION_URL,
	bodyHead,
	buildIssueBody,
	fetchTrunkVersion,
	fileIssue,
	findIssue,
	issueTitle,
	normalizeVersion,
	parseVersionPhp,
	targetVersion,
	versionFromWp,
} from './core-compat-issue.ts';
import type { Deps, RunInfo, RunOptions } from './core-compat-issue.ts';

const VERSION_PHP = `<?php
/**
 * WordPress Version
 */

/**
 * The WordPress version string.
 *
 * @global string $wp_version
 */
$wp_version = '7.3-alpha-61234';

$wp_db_version = 60717;
`;

test( 'normalizeVersion drops the nightly build number', () => {
	assert.equal( normalizeVersion( '7.3-alpha-61234' ), '7.3-alpha' );
	assert.equal( normalizeVersion( '7.2-RC2-65716' ), '7.2-RC2' );
	assert.equal( normalizeVersion( '7.2-beta1-60000' ), '7.2-beta1' );
	assert.equal( normalizeVersion( '7.2.1-RC1-60000' ), '7.2.1-RC1' );
} );

test( 'normalizeVersion leaves releases and tags without a build alone', () => {
	for ( const version of [ '7.1', '7.1.2', '7.2-RC2', '7.3-alpha', 'master', '', '7.3-alpha-61234x', 'v7.3-alpha-1' ] ) {
		assert.equal( normalizeVersion( version ), version );
	}
} );

test( 'versionFromWp reads X.Y[-tag] from a zip URL', () => {
	assert.equal( versionFromWp( 'https://downloads.wordpress.org/release/wordpress-7.1.zip' ), '7.1' );
	assert.equal( versionFromWp( 'https://wordpress.org/wordpress-7.2-RC1.zip' ), '7.2-RC1' );
	assert.equal( versionFromWp( 'https://example.test/builds/custom.zip' ), 'custom' );
	assert.equal( versionFromWp( 'master' ), 'master' );
	assert.equal( versionFromWp( '6.8' ), '6.8' );
	assert.equal( versionFromWp( 'https://example.test/dir/' ), 'dir' );
} );

test( 'parseVersionPhp reads $wp_version', () => {
	assert.equal( parseVersionPhp( VERSION_PHP ), '7.3-alpha-61234' );
	assert.equal( parseVersionPhp( VERSION_PHP.replace( /\n/g, '\r\n' ) ), '7.3-alpha-61234' );
	assert.equal( parseVersionPhp( 'no version here' ), '' );
	assert.equal( parseVersionPhp( "  $wp_version = '1.0';" ), '' );
} );

test( 'targetVersion prefers the running version', () => {
	assert.equal( targetVersion( '7.2-RC2-65716', 'https://wordpress.org/wordpress-7.2-RC2.zip' ), '7.2-RC2' );
	assert.equal( targetVersion( '7.1', 'master', '7.3-alpha-1' ), '7.1' );
} );

test( 'targetVersion falls back to the matrix value, and trunk for master', () => {
	assert.equal( targetVersion( '', 'https://wordpress.org/wordpress-7.2-RC2.zip' ), '7.2-RC2' );
	assert.equal( targetVersion( '', 'master', '7.3-alpha-61234' ), '7.3-alpha' );
	assert.equal( targetVersion( '', 'master', '' ), 'master' );
	assert.equal( targetVersion( '', '6.8', '7.3-alpha-61234' ), '6.8' );
} );

test( 'issueTitle', () => {
	assert.equal( issueTitle( '7.3-alpha' ), 'Core compat: WordPress 7.3-alpha' );
} );

const info: RunInfo = {
	envOutcome: 'success',
	snapshot: 'failure',
	suite: 'success',
	debuglog: 'success',
	fullVersion: '',
	matrixWp: 'master',
	runUrl: 'https://github.com/o/r/actions/runs/1',
};

test( 'bodyHead lists the outcomes, version and run', () => {
	assert.equal(
		bodyHead( info ),
		'Environment: success. Snapshot check: failure. Browser suite: success. PHP fatals: success.\n\nWordPress: master\n\nRun: https://github.com/o/r/actions/runs/1\n\n'
	);
	assert.match( bodyHead( { ...info, fullVersion: '7.1' } ), /\nWordPress: 7\.1\n/ );
} );

test( 'buildIssueBody appends the report with one trailing newline', () => {
	assert.equal( buildIssueBody( 'H\n\n', null ), 'H\n\n' );
	assert.equal( buildIssueBody( 'H\n\n', '# Diff\n\n- a\n\n\n' ), 'H\n\n# Diff\n\n- a\n' );
	assert.equal( buildIssueBody( 'H\n\n', '\n\n' ), 'H\n\n\n' );
} );

test( 'buildIssueBody truncates by code point to fit the limit', () => {
	const head = 'Héad\n\n';
	// Room is limit - 6 code points in the head - 100.
	const fits = 'é'.repeat( 194 );
	assert.equal( buildIssueBody( head, fits, 300 ), `${ head }${ fits }\n` );
	assert.equal(
		buildIssueBody( head, `${ fits }🙂x`, 300 ),
		`${ head }${ fits }\n\n${ TRUNCATION_NOTE }\n`
	);
	assert.equal(
		buildIssueBody( head, `${ 'é'.repeat( 193 ) }🙂x`, 300 ),
		`${ head }${ 'é'.repeat( 193 ) }🙂\n\n${ TRUNCATION_NOTE }\n`
	);
	const body = buildIssueBody( head, 'x'.repeat( 100000 ) );
	assert.ok( Array.from( body ).length < BODY_LIMIT );
} );

test( 'findIssue matches the exact title', () => {
	const json = JSON.stringify( [
		{ number: 3, title: 'Core compat: WordPress 7.3-alpha-61234' },
		{ number: 5, title: 'Core compat: WordPress 7.3-alpha' },
		{ number: 7, title: 'Core compat: WordPress 7.3-alpha' },
	] );
	assert.equal( findIssue( json, 'Core compat: WordPress 7.3-alpha' ), 5 );
	assert.equal( findIssue( json, 'Core compat: WordPress 7.2' ), null );
	assert.equal( findIssue( '[]', 'x' ), null );
} );

function textFetch( status: number, body: string ): { fetch: typeof fetch; calls: string[] } {
	const calls: string[] = [];
	const stub = async ( input: string | URL | Request ): Promise<Response> => {
		calls.push( String( input ) );
		return new Response( body, { status } );
	};
	return { fetch: stub as typeof fetch, calls };
}

test( 'fetchTrunkVersion returns empty on HTTP errors and network failures', async () => {
	assert.equal( await fetchTrunkVersion( textFetch( 200, VERSION_PHP ).fetch ), '7.3-alpha-61234' );
	assert.equal( await fetchTrunkVersion( textFetch( 500, VERSION_PHP ).fetch ), '' );
	const failing = ( async () => {
		throw new TypeError( 'fetch failed' );
	} ) as typeof fetch;
	assert.equal( await fetchTrunkVersion( failing ), '' );
} );

interface Call {
	command: string;
	args: string[];
	options: RunOptions;
}

function issueDeps( env: NodeJS.ProcessEnv, issues: unknown[], options: { report?: string | null; labelFails?: boolean } = {} ) {
	const calls: Call[] = [];
	const logs: string[] = [];
	const { fetch: fetchStub, calls: fetches } = textFetch( 200, VERSION_PHP );
	const deps: Deps = {
		env,
		fetch: fetchStub,
		readReport: () => options.report ?? null,
		log: ( text ) => logs.push( text ),
		run: ( command, args, runOptions = {} ) => {
			calls.push( { command, args, options: runOptions } );
			if ( args[ 0 ] === 'label' && options.labelFails ) {
				throw new Error( 'label exists' );
			}
			if ( args[ 0 ] === 'issue' && args[ 1 ] === 'list' ) {
				return JSON.stringify( issues );
			}
			return 'https://github.com/o/r/issues/9\n';
		},
	};
	return { deps, calls, logs, fetches };
}

const baseEnv = {
	ENV_OUTCOME: 'success',
	SNAPSHOT: 'failure',
	SUITE: 'success',
	DEBUGLOG: 'success',
	RUN_URL: 'https://github.com/o/r/actions/runs/1',
};

test( 'fileIssue creates an issue when none is open', async () => {
	const { deps, calls, logs, fetches } = issueDeps(
		{ ...baseEnv, FULL_VERSION: '7.3-alpha-61234', MATRIX_WP: 'master' },
		[ { number: 2, title: 'Core compat: WordPress 7.2' } ],
		{ report: '# Diff\n', labelFails: true }
	);
	await fileIssue( deps );
	assert.deepEqual( fetches, [] );
	assert.deepEqual( calls.map( ( c ) => c.args.slice( 0, 2 ) ), [ [ 'label', 'create' ], [ 'issue', 'list' ], [ 'issue', 'create' ] ] );
	assert.equal( calls[ 0 ].options.quiet, true );
	assert.deepEqual( calls[ 1 ].args, [ 'issue', 'list', '--label', LABEL, '--state', 'open', '--limit', '100', '--json', 'number,title' ] );
	assert.deepEqual( calls[ 2 ].args, [ 'issue', 'create', '--title', 'Core compat: WordPress 7.3-alpha', '--label', LABEL, '--body-file', '-' ] );
	assert.equal(
		calls[ 2 ].options.input,
		'Environment: success. Snapshot check: failure. Browser suite: success. PHP fatals: success.\n\nWordPress: 7.3-alpha-61234\n\nRun: https://github.com/o/r/actions/runs/1\n\n# Diff\n'
	);
	assert.deepEqual( logs, [ 'https://github.com/o/r/issues/9\n' ] );
} );

test( 'fileIssue comments on the open issue with the same title', async () => {
	const { deps, calls } = issueDeps(
		{ ...baseEnv, FULL_VERSION: '', MATRIX_WP: 'https://wordpress.org/wordpress-7.2-RC2.zip' },
		[ { number: 4, title: 'Core compat: WordPress 7.2-RC2' } ]
	);
	await fileIssue( deps );
	assert.deepEqual( calls[ 2 ].args, [ 'issue', 'comment', '4', '--body-file', '-' ] );
	assert.match( calls[ 2 ].options.input ?? '', /\nWordPress: https:\/\/wordpress\.org\/wordpress-7\.2-RC2\.zip\n\nRun: [^\n]+\n\n$/ );
} );

test( 'fileIssue reads trunk for master when the environment failed', async () => {
	const { deps, calls, fetches } = issueDeps( { ...baseEnv, FULL_VERSION: '', MATRIX_WP: 'master' }, [] );
	await fileIssue( deps );
	assert.deepEqual( fetches, [ TRUNK_VERSION_URL ] );
	assert.equal( calls[ 2 ].args[ 3 ], 'Core compat: WordPress 7.3-alpha' );
} );

test( 'fileIssue fails when gh cannot list issues', async () => {
	const { deps } = issueDeps( { ...baseEnv, FULL_VERSION: '7.1', MATRIX_WP: 'latest' }, [] );
	const run = deps.run;
	deps.run = ( command, args, options ) => {
		if ( args[ 1 ] === 'list' ) {
			throw new Error( 'gh: HTTP 401' );
		}
		return run( command, args, options );
	};
	await assert.rejects( fileIssue( deps ), /HTTP 401/ );
} );
