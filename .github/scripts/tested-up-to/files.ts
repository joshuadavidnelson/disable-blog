// Matches two- and three-part releases alike (7.1 and 7.1.2).
const V = '[0-9]+\\.[0-9]+(?:\\.[0-9]+)?';
// POSIX [[:space:]], without \n since matching runs one line at a time.
const S = '[ \\t\\v\\f\\r]';

const README_TXT = new RegExp( `^(Tested up to:${ S }*)${ V }${ S }*$` );
const README_MD = new RegExp( `(\\*\\*Tested up to WordPress:\\*\\*${ S }*)${ V }` );
const WP_ENV = new RegExp( `(https://wordpress\\.org/wordpress-)${ V }(\\.zip)` );

/**
 * Applies `pattern` to the first match on each line, the way `sed s///` does.
 * A trailing \r stays out of the match and is put back, so CRLF files keep
 * their line endings.
 */
function replacePerLine( text: string, pattern: RegExp, replace: ( match: RegExpExecArray ) => string ): string {
	return text
		.split( '\n' )
		.map( ( line ) => {
			const cr = line.endsWith( '\r' ) ? '\r' : '';
			const body = cr ? line.slice( 0, -1 ) : line;
			const match = pattern.exec( body );
			if ( ! match ) {
				return line;
			}
			return body.slice( 0, match.index ) + replace( match ) + body.slice( match.index + match[ 0 ].length ) + cr;
		} )
		.join( '\n' );
}

/** readme.txt: `Tested up to: <minor>`, the form WordPress.org and Plugin Check read. */
export function rewriteReadmeTxt( text: string, minor: string ): string {
	return replacePerLine( text, README_TXT, ( [ , prefix ] ) => `${ prefix }${ minor }` );
}

/** README.md: `**Tested up to WordPress:** <full>`. */
export function rewriteReadmeMd( text: string, full: string ): string {
	return replacePerLine( text, README_MD, ( [ , prefix ] ) => `${ prefix }${ full }` );
}

/** .wp-env.json: the `wordpress-<full>.zip` core pin. */
export function rewriteWpEnv( text: string, full: string ): string {
	return replacePerLine( text, WP_ENV, ( [ , prefix, zip ] ) => `${ prefix }${ full }${ zip }` );
}

/*
 * A pattern that stops matching leaves the file untouched, which looks
 * identical to "already up to date". These checks tell the two apart.
 */

export function verifyReadmeTxt( text: string, minor: string ): void {
	const expected = `Tested up to: ${ minor }`;
	const found = text.split( '\n' ).some( ( line ) => line.replace( /\r$/, '' ) === expected );
	if ( ! found ) {
		throw new Error( `readme.txt does not state '${ expected }'` );
	}
}

export function verifyReadmeMd( text: string, full: string ): void {
	if ( ! text.includes( `**Tested up to WordPress:** ${ full }` ) ) {
		throw new Error( `README.md does not state 'Tested up to WordPress: ${ full }'` );
	}
}

export function verifyWpEnv( text: string, full: string ): void {
	if ( ! text.includes( `wordpress-${ full }.zip` ) ) {
		throw new Error( `.wp-env.json core is not pinned to ${ full }` );
	}
}
