import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { assertVersion, isVersion, latestFromStableCheck, minorOf } from './version.ts';

describe( 'latestFromStableCheck', () => {
	it( 'returns the release marked latest', () => {
		const body = { '7.0.3': 'outdated', '7.1.4': 'insecure', '7.1.5': 'latest' };
		assert.equal( latestFromStableCheck( body ), '7.1.5' );
	} );

	it( 'returns a two-part release as is', () => {
		assert.equal( latestFromStableCheck( { '7.1.5': 'outdated', '7.2': 'latest' } ), '7.2' );
	} );

	it( 'returns the first latest entry when there are several', () => {
		assert.equal( latestFromStableCheck( { '7.2': 'latest', '7.1.5': 'latest' } ), '7.2' );
	} );

	it( 'returns an empty string when nothing is latest', () => {
		assert.equal( latestFromStableCheck( { '7.1.5': 'outdated' } ), '' );
		assert.equal( latestFromStableCheck( {} ), '' );
	} );

	it( 'returns an empty string for a response that is not an object', () => {
		for ( const body of [ null, undefined, '', '7.2', 7.2, [ 'latest' ], true ] ) {
			assert.equal( latestFromStableCheck( body ), '', `body: ${ JSON.stringify( body ) }` );
		}
	} );
} );

describe( 'isVersion and assertVersion', () => {
	it( 'accept two- and three-part releases', () => {
		for ( const version of [ '7.2', '7.1.5', '10.0', '6.4.10' ] ) {
			assert.equal( isVersion( version ), true, version );
			assert.equal( assertVersion( version ), version );
		}
	} );

	it( 'reject anything else', () => {
		for ( const version of [ '', '7', '7.', '7.2.', '7.1.5.1', 'v7.2', '7.2-RC1', ' 7.2', '7.2\n', 'latest' ] ) {
			assert.equal( isVersion( version ), false, JSON.stringify( version ) );
			assert.throws(
				() => assertVersion( version ),
				{ message: `Unusable version from the WordPress.org API: '${ version }'` },
			);
		}
	} );
} );

describe( 'minorOf', () => {
	it( 'drops the patch number from a three-part release', () => {
		assert.equal( minorOf( '7.1.5' ), '7.1' );
	} );

	it( 'leaves a two-part release unchanged', () => {
		assert.equal( minorOf( '7.2' ), '7.2' );
	} );
} );
