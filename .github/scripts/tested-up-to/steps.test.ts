import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, beforeEach, describe, it } from 'node:test';

import {
	resolveLatest,
	STABLE_CHECK_URL,
	updateFiles,
	verifyFiles,
	versionsFromEnv,
	writeOutputs,
	type Fetch,
} from './steps.ts';

function stubFetch( body: string, status = 200 ): Fetch & { urls: string[] } {
	const urls: string[] = [];
	const stub = async ( url: string ) => {
		urls.push( url );
		return new Response( body, { status } );
	};
	return Object.assign( stub, { urls } );
}

describe( 'resolveLatest', () => {
	it( 'resolves a three-part release from the stable-check API', async () => {
		const fetchStub = stubFetch( '{"7.1.4":"insecure","7.1.5":"latest"}' );
		assert.deepEqual( await resolveLatest( fetchStub ), { full: '7.1.5', minor: '7.1' } );
		assert.deepEqual( fetchStub.urls, [ STABLE_CHECK_URL ] );
	} );

	it( 'resolves a two-part release', async () => {
		assert.deepEqual( await resolveLatest( stubFetch( '{"7.1.5":"outdated","7.2":"latest"}' ) ), { full: '7.2', minor: '7.2' } );
	} );

	it( 'rejects a response with no latest release', async () => {
		await assert.rejects(
			resolveLatest( stubFetch( '{"7.1.5":"outdated"}' ) ),
			{ message: "Unusable version from the WordPress.org API: ''" },
		);
	} );

	it( 'rejects a latest key that is not a version', async () => {
		await assert.rejects(
			resolveLatest( stubFetch( '{"7.2-RC1":"latest"}' ) ),
			{ message: "Unusable version from the WordPress.org API: '7.2-RC1'" },
		);
	} );

	it( 'rejects an empty or malformed body', async () => {
		await assert.rejects( resolveLatest( stubFetch( '' ) ), SyntaxError );
		await assert.rejects( resolveLatest( stubFetch( '<html>' ) ), SyntaxError );
		await assert.rejects( resolveLatest( stubFetch( '[]' ) ), /Unusable version/ );
		await assert.rejects( resolveLatest( stubFetch( 'null' ) ), /Unusable version/ );
	} );

	it( 'rejects an HTTP error', async () => {
		await assert.rejects( resolveLatest( stubFetch( '{"7.2":"latest"}', 503 ) ), { message: `${ STABLE_CHECK_URL } returned HTTP 503` } );
	} );
} );

describe( 'versionsFromEnv', () => {
	it( 'reads FULL and MINOR', () => {
		assert.deepEqual( versionsFromEnv( { FULL: '7.1.5', MINOR: '7.1' } ), { full: '7.1.5', minor: '7.1' } );
	} );

	it( 'rejects a missing or mismatched pair', () => {
		assert.throws( () => versionsFromEnv( {} ), /Unusable version/ );
		assert.throws( () => versionsFromEnv( { FULL: '7.1.5' } ), /MINOR '' is not the minor release of 7.1.5/ );
		assert.throws( () => versionsFromEnv( { FULL: '7.1.5', MINOR: '7.1.5' } ), /not the minor release/ );
	} );
} );

describe( 'files on disk', () => {
	let dir: string;

	beforeEach( async () => {
		dir = await mkdtemp( join( tmpdir(), 'tested-up-to-' ) );
		await writeFile( join( dir, 'readme.txt' ), '=== Disable Blog ===\nTested up to: 7.1\n' );
		await writeFile( join( dir, 'README.md' ), '**Tested up to WordPress:** 7.1.4  \n' );
		await writeFile( join( dir, '.wp-env.json' ), '{\n\t"core": "https://wordpress.org/wordpress-7.1.4.zip"\n}\n' );
	} );

	afterEach( async () => {
		await rm( dir, { recursive: true, force: true } );
	} );

	it( 'writes the outputs the pull request step reads', async () => {
		const output = join( dir, 'output' );
		await writeFile( output, 'earlier=1\n' );
		await writeOutputs( output, { full: '7.2', minor: '7.2' } );
		assert.equal( await readFile( output, 'utf8' ), 'earlier=1\nfull=7.2\nminor=7.2\n' );
	} );

	it( 'updates all three files, then verifies them', async () => {
		const versions = { full: '7.2.1', minor: '7.2' };
		await updateFiles( dir, versions );
		assert.equal( await readFile( join( dir, 'readme.txt' ), 'utf8' ), '=== Disable Blog ===\nTested up to: 7.2\n' );
		assert.equal( await readFile( join( dir, 'README.md' ), 'utf8' ), '**Tested up to WordPress:** 7.2.1  \n' );
		assert.equal(
			await readFile( join( dir, '.wp-env.json' ), 'utf8' ),
			'{\n\t"core": "https://wordpress.org/wordpress-7.2.1.zip"\n}\n',
		);
		await verifyFiles( dir, versions );
	} );

	it( 'fails verification when a readme line no longer matches', async () => {
		await writeFile( join( dir, 'readme.txt' ), 'Tested Up To: 7.1\n' );
		const versions = { full: '7.2', minor: '7.2' };
		await updateFiles( dir, versions );
		await assert.rejects( verifyFiles( dir, versions ), { message: "readme.txt does not state 'Tested up to: 7.2'" } );
	} );
} );
