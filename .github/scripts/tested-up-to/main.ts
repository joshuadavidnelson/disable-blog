// Usage: node .github/scripts/tested-up-to/main.ts <resolve|update|verify>
// Runs from the repository root, where the three edited files live.
import { resolveLatest, updateFiles, verifyFiles, versionsFromEnv, writeOutputs } from './steps.ts';

const command = process.argv[ 2 ];

try {
	if ( command === 'resolve' ) {
		const outputPath = process.env.GITHUB_OUTPUT;
		if ( ! outputPath ) {
			throw new Error( 'GITHUB_OUTPUT is not set' );
		}
		const versions = await resolveLatest();
		await writeOutputs( outputPath, versions );
		console.log( `Latest WordPress release: ${ versions.full }` );
	} else if ( command === 'update' ) {
		await updateFiles( process.cwd(), versionsFromEnv( process.env ) );
	} else if ( command === 'verify' ) {
		await verifyFiles( process.cwd(), versionsFromEnv( process.env ) );
	} else {
		throw new Error( `Unknown command '${ command ?? '' }'; expected resolve, update or verify` );
	}
} catch ( error ) {
	console.error( error instanceof Error ? error.message : error );
	process.exit( 1 );
}
