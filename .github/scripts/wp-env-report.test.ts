import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { isCoreVersion, parseCoreVersion, report } from './wp-env-report.ts';

test( 'parseCoreVersion takes the last line and strips carriage returns', () => {
	assert.equal( parseCoreVersion( '7.1\n' ), '7.1' );
	assert.equal( parseCoreVersion( 'ℹ Starting...\r\n7.3-alpha-61234\r\n' ), '7.3-alpha-61234' );
	assert.equal( parseCoreVersion( 'noise\n6.8.1' ), '6.8.1' );
	assert.equal( parseCoreVersion( '7.1\n\n' ), '' );
	assert.equal( parseCoreVersion( '' ), '' );
} );

test( 'isCoreVersion accepts X.Y prefixes only', () => {
	for ( const version of [ '7.1', '6.8.1', '7.3-alpha-61234', '10.0' ] ) {
		assert.equal( isCoreVersion( version ), true, version );
	}
	for ( const version of [ '', '7', 'v7.1', 'Error: not installed', '.1' ] ) {
		assert.equal( isCoreVersion( version ), false, version );
	}
} );

function withOutput( fn: ( file: string ) => void ): void {
	const dir = mkdtempSync( join( tmpdir(), 'wp-env-report-test-' ) );
	try {
		fn( join( dir, 'output' ) );
	} finally {
		rmSync( dir, { recursive: true, force: true } );
	}
}

test( 'report writes the version and then prints PHP', () => {
	withOutput( ( file ) => {
		const logs: string[] = [];
		const streamed: string[][] = [];
		const code = report( {
			env: { GITHUB_OUTPUT: file },
			capture: () => 'ℹ Starting...\n7.1\r\n',
			stream: ( _command, args ) => streamed.push( args ),
			log: ( line ) => logs.push( line ),
		} );
		assert.equal( code, 0 );
		assert.deepEqual( logs, [ 'WordPress 7.1' ] );
		assert.equal( readFileSync( file, 'utf8' ), 'version=7.1\n' );
		assert.deepEqual( streamed, [ [ 'wp-env', 'run', 'tests-cli', '--', 'php', '-r', 'echo PHP_VERSION, PHP_EOL;' ] ] );
	} );
} );

test( 'report fails on unexpected output without writing the output', () => {
	withOutput( ( file ) => {
		const logs: string[] = [];
		const code = report( {
			env: { GITHUB_OUTPUT: file },
			capture: () => 'Error: WordPress is not installed\n',
			stream: () => assert.fail( 'PHP is not reported after a failure' ),
			log: ( line ) => logs.push( line ),
		} );
		assert.equal( code, 1 );
		assert.deepEqual( logs, [ '::error title=wp-env::Unexpected output from wp core version: Error: WordPress is not installed' ] );
		assert.equal( existsSync( file ), false );
	} );
} );
