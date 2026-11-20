import { test } from 'node:test';
import assert from 'node:assert/strict';

import { checkDebugLog, legName, logLines, uniqueSorted } from './check-debug-log.ts';

const LEG = 'WP latest / PHP 8.3';

test( 'logLines splits on newlines and keeps a final unterminated line', () => {
	assert.deepEqual( logLines( 'a\nb\n' ), [ 'a', 'b' ] );
	assert.deepEqual( logLines( 'a\nb' ), [ 'a', 'b' ] );
	assert.deepEqual( logLines( 'a\n\nb\n' ), [ 'a', '', 'b' ] );
} );

test( 'uniqueSorted dedupes and sorts by byte', () => {
	assert.deepEqual( uniqueSorted( [ 'b', 'a', 'b', 'B', 'é', 'z' ] ), [ 'B', 'a', 'b', 'z', 'é' ] );
} );

test( 'a clean log passes silently', () => {
	assert.deepEqual( checkDebugLog( '[01-Jan-2026] Something harmless\n', LEG ), { output: [], failed: false } );
} );

test( 'diagnostics warn with sorted unique lines and do not fail', () => {
	const log = [
		'[2] PHP Warning:  Undefined variable $x in /a.php on line 3',
		'[1] PHP Deprecated:  Creation of dynamic property',
		'[2] PHP Warning:  Undefined variable $x in /a.php on line 3',
		'[3] PHP Notice:  Trying to get property',
		'[4] PHP Parse thing',
		'',
	].join( '\n' );
	assert.deepEqual( checkDebugLog( log, LEG ), {
		output: [
			'::warning title=PHP diagnostics::WP latest / PHP 8.3 logged PHP diagnostics; see the debug.log artifact.',
			'[1] PHP Deprecated:  Creation of dynamic property',
			'[2] PHP Warning:  Undefined variable $x in /a.php on line 3',
			'[3] PHP Notice:  Trying to get property',
		],
		failed: false,
	} );
} );

test( 'diagnostics are capped at 40 lines and fatals at 20', () => {
	const warnings = Array.from( { length: 50 }, ( _, i ) => `PHP Warning: w${ String( i ).padStart( 2, '0' ) }` );
	const fatals = Array.from( { length: 25 }, ( _, i ) => `PHP Fatal error: f${ String( i ).padStart( 2, '0' ) }` );
	const result = checkDebugLog( [ ...warnings, ...fatals ].join( '\n' ), LEG );
	assert.equal( result.failed, true );
	assert.equal( result.output.length, 1 + 40 + 1 + 20 );
	assert.equal( result.output[ 40 ], 'PHP Warning: w39' );
	assert.equal( result.output[ 41 ], '::error title=PHP fatal::A PHP fatal error was logged during the run.' );
	assert.equal( result.output.at( -1 ), 'PHP Fatal error: f19' );
} );

test( 'a fatal fails without a diagnostics warning', () => {
	assert.deepEqual( checkDebugLog( 'PHP Fatal error:  Uncaught Error', LEG ), {
		output: [ '::error title=PHP fatal::A PHP fatal error was logged during the run.', 'PHP Fatal error:  Uncaught Error' ],
		failed: true,
	} );
} );

test( 'legName names the WordPress target and PHP version', () => {
	assert.equal( legName( { DWPB_WP_LABEL: 'beta', DWPB_PHP_VERSION: '8.4' } ), 'WP beta / PHP 8.4' );
} );
