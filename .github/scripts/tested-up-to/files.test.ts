import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
	rewriteReadmeMd,
	rewriteReadmeTxt,
	rewriteWpEnv,
	verifyReadmeMd,
	verifyReadmeTxt,
	verifyWpEnv,
} from './files.ts';

const README_TXT = `=== Disable Blog ===
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4

== Changelog ==

- Tested up to WordPress 7.1.4
`;

const README_MD = `# Disable Blog

**Tested up to WordPress:** 7.1.4  
**Tested up to PHP:** 8.5  
`;

const WP_ENV = `{
	"core": "https://wordpress.org/wordpress-7.1.4.zip",
	"phpVersion": "8.3"
}
`;

describe( 'rewriteReadmeTxt', () => {
	it( 'sets the header to a new minor release and leaves the changelog alone', () => {
		assert.equal( rewriteReadmeTxt( README_TXT, '7.2' ), README_TXT.replace( 'Tested up to: 7.1', 'Tested up to: 7.2' ) );
	} );

	it( 'replaces a three-part header with the minor release', () => {
		const text = 'Tested up to: 7.1.4\n';
		assert.equal( rewriteReadmeTxt( text, '7.1' ), 'Tested up to: 7.1\n' );
	} );

	it( 'keeps the spacing after the colon and drops trailing whitespace', () => {
		assert.equal( rewriteReadmeTxt( 'Tested up to:\t7.0  \n', '7.2' ), 'Tested up to:\t7.2\n' );
	} );

	it( 'leaves a readme already at the target unchanged, and it verifies', () => {
		assert.equal( rewriteReadmeTxt( README_TXT, '7.1' ), README_TXT );
		assert.doesNotThrow( () => verifyReadmeTxt( README_TXT, '7.1' ) );
	} );

	it( 'leaves a header in another format untouched, and verification fails', () => {
		for ( const line of [ 'Tested Up To: 7.0', 'Tested up to: 7.0 (beta)', ' Tested up to: 7.0', 'Tested up to: 7' ] ) {
			const text = `${ line }\n`;
			const rewritten = rewriteReadmeTxt( text, '7.2' );
			assert.equal( rewritten, text, line );
			assert.throws(
				() => verifyReadmeTxt( rewritten, '7.2' ),
				{ message: "readme.txt does not state 'Tested up to: 7.2'" },
				line,
			);
		}
	} );

	it( 'keeps CRLF line endings, and verifies', () => {
		const text = README_TXT.replaceAll( '\n', '\r\n' );
		const rewritten = rewriteReadmeTxt( text, '7.2' );
		assert.equal( rewritten, text.replace( 'Tested up to: 7.1', 'Tested up to: 7.2' ) );
		assert.doesNotThrow( () => verifyReadmeTxt( rewritten, '7.2' ) );
	} );

	it( 'needs the whole line to match when verifying', () => {
		assert.throws( () => verifyReadmeTxt( 'Tested up to: 7.21\n', '7.2' ) );
		assert.throws( () => verifyReadmeTxt( 'Tested up to: 7.2 \n', '7.2' ) );
	} );
} );

describe( 'rewriteReadmeMd', () => {
	it( 'sets a three-part release and keeps the Markdown line break', () => {
		const rewritten = rewriteReadmeMd( README_MD, '7.1.5' );
		assert.equal( rewritten, README_MD.replace( '7.1.4  ', '7.1.5  ' ) );
		assert.doesNotThrow( () => verifyReadmeMd( rewritten, '7.1.5' ) );
	} );

	it( 'sets a two-part release', () => {
		const rewritten = rewriteReadmeMd( README_MD, '7.2' );
		assert.ok( rewritten.includes( '**Tested up to WordPress:** 7.2  \n' ) );
		assert.doesNotThrow( () => verifyReadmeMd( rewritten, '7.2' ) );
	} );

	it( 'replaces a two-part release', () => {
		assert.equal( rewriteReadmeMd( '**Tested up to WordPress:** 7.2\n', '7.2.1' ), '**Tested up to WordPress:** 7.2.1\n' );
	} );

	it( 'keeps CRLF line endings', () => {
		const text = README_MD.replaceAll( '\n', '\r\n' );
		assert.equal( rewriteReadmeMd( text, '7.1.5' ), text.replace( '7.1.4', '7.1.5' ) );
	} );

	it( 'leaves a line in another format untouched, and verification fails', () => {
		const text = '**Tested up to:** 7.1.4\n';
		const rewritten = rewriteReadmeMd( text, '7.1.5' );
		assert.equal( rewritten, text );
		assert.throws(
			() => verifyReadmeMd( rewritten, '7.1.5' ),
			{ message: "README.md does not state 'Tested up to WordPress: 7.1.5'" },
		);
	} );
} );

describe( 'rewriteWpEnv', () => {
	it( 'pins core to a three-part release', () => {
		const rewritten = rewriteWpEnv( WP_ENV, '7.1.5' );
		assert.equal( rewritten, WP_ENV.replace( 'wordpress-7.1.4.zip', 'wordpress-7.1.5.zip' ) );
		assert.doesNotThrow( () => verifyWpEnv( rewritten, '7.1.5' ) );
	} );

	it( 'pins core to a two-part release', () => {
		const rewritten = rewriteWpEnv( WP_ENV, '7.2' );
		assert.ok( rewritten.includes( '"https://wordpress.org/wordpress-7.2.zip"' ) );
		assert.doesNotThrow( () => verifyWpEnv( rewritten, '7.2' ) );
	} );

	it( 'keeps CRLF line endings', () => {
		const text = WP_ENV.replaceAll( '\n', '\r\n' );
		assert.equal( rewriteWpEnv( text, '7.2' ), text.replace( '7.1.4', '7.2' ) );
	} );

	it( 'leaves a core that is not a release zip untouched, and verification fails', () => {
		for ( const core of [ 'WordPress/WordPress#7.1.4', 'https://wordpress.org/latest.zip' ] ) {
			const text = WP_ENV.replace( 'https://wordpress.org/wordpress-7.1.4.zip', core );
			const rewritten = rewriteWpEnv( text, '7.2' );
			assert.equal( rewritten, text, core );
			assert.throws( () => verifyWpEnv( rewritten, '7.2' ), { message: '.wp-env.json core is not pinned to 7.2' }, core );
		}
	} );
} );
