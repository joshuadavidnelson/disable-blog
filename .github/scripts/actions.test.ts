import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { setOutput, isMain } from './actions.ts';

test( 'setOutput appends name=value lines', () => {
	const dir = mkdtempSync( join( tmpdir(), 'actions-test-' ) );
	try {
		const file = join( dir, 'output' );
		setOutput( 'a', '1', { GITHUB_OUTPUT: file } );
		setOutput( 'b', '{"x":2}', { GITHUB_OUTPUT: file } );
		assert.equal( readFileSync( file, 'utf8' ), 'a=1\nb={"x":2}\n' );
	} finally {
		rmSync( dir, { recursive: true, force: true } );
	}
} );

test( 'setOutput throws without GITHUB_OUTPUT', () => {
	assert.throws( () => setOutput( 'a', '1', {} ), /GITHUB_OUTPUT/ );
} );

test( 'isMain is false for an imported module', () => {
	assert.equal( isMain( new URL( './actions.ts', import.meta.url ).href ), false );
} );
