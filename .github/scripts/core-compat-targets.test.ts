import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import {
	BETA_CHECK_URL,
	VERSION_CHECK_URL,
	customMatrix,
	main,
	offerDownload,
	resolveTargets,
	scheduledMatrix,
} from './core-compat-targets.ts';
import type { Deps } from './core-compat-targets.ts';

const LATEST_ZIP = 'https://downloads.wordpress.org/release/wordpress-7.1.zip';
const BETA_ZIP = 'https://wordpress.org/wordpress-7.2-RC1.zip';

const stableFeed = {
	offers: [
		{ response: 'upgrade', download: LATEST_ZIP },
		{ response: 'autoupdate', download: 'https://example.test/old.zip' },
	],
};
const betaFeed = {
	offers: [
		{ response: 'autoupdate', download: 'https://example.test/old.zip' },
		{ response: 'development', download: BETA_ZIP },
	],
};

function stubFetch( routes: Record<string, unknown> ): { fetch: typeof fetch; calls: string[] } {
	const calls: string[] = [];
	const fetchStub = async ( input: string | URL | Request ): Promise<Response> => {
		const url = String( input );
		calls.push( url );
		if ( ! ( url in routes ) ) {
			return new Response( 'not found', { status: 404 } );
		}
		return new Response( JSON.stringify( routes[ url ] ) );
	};
	return { fetch: fetchStub as typeof fetch, calls };
}

function deps( env: NodeJS.ProcessEnv, routes: Record<string, unknown> ): Deps & { calls: string[]; logs: string[] } {
	const { fetch: fetchStub, calls } = stubFetch( routes );
	const logs: string[] = [];
	return { env, fetch: fetchStub, log: ( line ) => logs.push( line ), calls, logs };
}

test( 'offerDownload returns the first matching offer download', () => {
	assert.equal( offerDownload( stableFeed, 'upgrade' ), LATEST_ZIP );
	assert.equal( offerDownload( betaFeed, 'development' ), BETA_ZIP );
	assert.equal(
		offerDownload( { offers: [ { response: 'upgrade', download: 'a' }, { response: 'upgrade', download: 'b' } ] }, 'upgrade' ),
		'a'
	);
} );

test( 'offerDownload is empty when nothing matches or the download is missing', () => {
	assert.equal( offerDownload( stableFeed, 'development' ), '' );
	assert.equal( offerDownload( { offers: [] }, 'upgrade' ), '' );
	assert.equal( offerDownload( { offers: [ { response: 'upgrade', download: null } ] }, 'upgrade' ), '' );
	assert.equal( offerDownload( { offers: [ { response: 'upgrade' } ] }, 'upgrade' ), '' );
} );

test( 'offerDownload throws on a feed without an offers list', () => {
	assert.throws( () => offerDownload( {}, 'upgrade' ) );
	assert.throws( () => offerDownload( null, 'upgrade' ) );
	assert.throws( () => offerDownload( { offers: null }, 'upgrade' ) );
} );

test( 'scheduledMatrix includes the beta leg only when there is one', () => {
	assert.equal(
		JSON.stringify( scheduledMatrix( LATEST_ZIP, BETA_ZIP ) ),
		`{"include":[{"name":"latest","wp":"${ LATEST_ZIP }","php":"8.3"},{"name":"beta","wp":"${ BETA_ZIP }","php":"8.4"},{"name":"master","wp":"master","php":"8.4"}]}`
	);
	assert.equal(
		JSON.stringify( scheduledMatrix( LATEST_ZIP, '' ) ),
		`{"include":[{"name":"latest","wp":"${ LATEST_ZIP }","php":"8.3"},{"name":"master","wp":"master","php":"8.4"}]}`
	);
} );

test( 'customMatrix runs the input verbatim on PHP 8.4', () => {
	assert.equal(
		JSON.stringify( customMatrix( '6.8 "quoted"' ) ),
		'{"include":[{"name":"custom","wp":"6.8 \\"quoted\\"","php":"8.4"}]}'
	);
} );

test( 'resolveTargets uses the dispatch input without fetching', async () => {
	const d = deps( { DWPB_WP_INPUT: 'master' }, {} );
	assert.deepEqual( await resolveTargets( d ), customMatrix( 'master' ) );
	assert.deepEqual( d.calls, [] );
} );

test( 'resolveTargets reads both feeds when there is no input', async () => {
	const d = deps( {}, { [ VERSION_CHECK_URL ]: stableFeed, [ BETA_CHECK_URL ]: betaFeed } );
	assert.deepEqual( await resolveTargets( d ), scheduledMatrix( LATEST_ZIP, BETA_ZIP ) );
	assert.deepEqual( d.calls, [ VERSION_CHECK_URL, BETA_CHECK_URL ] );
} );

test( 'resolveTargets stops with an error when there is no upgrade offer', async () => {
	const d = deps( { DWPB_WP_INPUT: '' }, { [ VERSION_CHECK_URL ]: { offers: [] }, [ BETA_CHECK_URL ]: betaFeed } );
	assert.equal( await resolveTargets( d ), null );
	assert.deepEqual( d.logs, [ '::error title=Targets::The version-check feed has no upgrade offer.' ] );
	assert.deepEqual( d.calls, [ VERSION_CHECK_URL ] );
} );

test( 'resolveTargets throws when a feed is unreachable', async () => {
	await assert.rejects( resolveTargets( deps( {}, {} ) ), /HTTP 404/ );
	await assert.rejects( resolveTargets( deps( {}, { [ VERSION_CHECK_URL ]: stableFeed } ) ), /HTTP 404/ );
} );

test( 'main logs the matrix and writes the step output', async () => {
	const dir = mkdtempSync( join( tmpdir(), 'targets-test-' ) );
	try {
		const output = join( dir, 'output' );
		const d = deps( { GITHUB_OUTPUT: output }, { [ VERSION_CHECK_URL ]: stableFeed, [ BETA_CHECK_URL ]: { offers: [] } } );
		assert.equal( await main( d ), 0 );
		const json = JSON.stringify( scheduledMatrix( LATEST_ZIP, '' ) );
		assert.deepEqual( d.logs, [ json ] );
		assert.equal( readFileSync( output, 'utf8' ), `matrix=${ json }\n` );
	} finally {
		rmSync( dir, { recursive: true, force: true } );
	}
} );

test( 'main returns 1 without writing output when there is no upgrade offer', async () => {
	const d = deps( {}, { [ VERSION_CHECK_URL ]: { offers: [] } } );
	assert.equal( await main( d ), 1 );
} );
