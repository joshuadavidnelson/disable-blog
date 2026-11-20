// WordPress ships both two- and three-part releases (7.2 and 7.1.5).
const VERSION = /^[0-9]+\.[0-9]+(\.[0-9]+)?$/;

/**
 * Returns the version the stable-check API marks "latest", or an empty string
 * when the response has none. The API maps every release to "latest",
 * "outdated" or "insecure".
 */
export function latestFromStableCheck( body: unknown ): string {
	if ( typeof body !== 'object' || body === null || Array.isArray( body ) ) {
		return '';
	}

	for ( const [ version, status ] of Object.entries( body ) ) {
		if ( status === 'latest' ) {
			return version;
		}
	}

	return '';
}

export function isVersion( value: string ): boolean {
	return VERSION.test( value );
}

/**
 * Throws on anything that is not a WordPress release number, so an empty or
 * malformed response never reaches the readmes.
 */
export function assertVersion( value: string ): string {
	if ( ! isVersion( value ) ) {
		throw new Error( `Unusable version from the WordPress.org API: '${ value }'` );
	}

	return value;
}

/** The minor release, which is what readme.txt states: 7.1.5 becomes 7.1. */
export function minorOf( full: string ): string {
	return full.split( '.' ).slice( 0, 2 ).join( '.' );
}
