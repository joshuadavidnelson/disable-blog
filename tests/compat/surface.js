#!/usr/bin/env node

/**
 * Core surface snapshot: dump the parts of WordPress core Disable Blog
 * depends on, and diff them against the committed baseline.
 *
 * Usage:
 *   node tests/compat/surface.js dump    Print the current surface.
 *   node tests/compat/surface.js check   Diff against the baseline, write
 *                                        artifacts/surface-diff.md, exit 1 on any change.
 *   node tests/compat/surface.js update  Overwrite the baseline with the current surface.
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { spawnSync } = require( 'node:child_process' );

const BEGIN_MARKER = '---DWPB-SURFACE-BEGIN---';
const END_MARKER = '---DWPB-SURFACE-END---';

const REPO_ROOT = path.join( __dirname, '..', '..' );
const BASELINE_PATH = path.join( __dirname, 'baseline', 'surface.json' );
const REPORT_PATH = path.join( REPO_ROOT, 'artifacts', 'surface-diff.md' );

/**
 * @typedef {{
 *   section: string,
 *   kind: 'added' | 'removed' | 'changed',
 *   key: string,
 *   before?: unknown,
 *   after?: unknown,
 * }} Change
 */

/**
 * @typedef {{ entry: true } | { entry: false, value: unknown }} Leaf
 */

/**
 * Parse the JSON printed between the dump's marker lines, ignoring
 * whatever wp-env prints around it.
 *
 * @param {string} stdout
 * @return {Record<string, unknown>}
 */
function extractJson( stdout ) {
	const begin = stdout.indexOf( BEGIN_MARKER );
	const end = stdout.indexOf( END_MARKER, begin + 1 );

	if ( -1 === begin || -1 === end ) {
		throw new Error(
			`Surface dump output is missing the ${ BEGIN_MARKER } or ${ END_MARKER } marker.`
		);
	}

	return JSON.parse( stdout.slice( begin + BEGIN_MARKER.length, end ) );
}

/**
 * @param {unknown} value
 * @return {value is Record<string, unknown>}
 */
function isPlainObject( value ) {
	return null !== value && 'object' === typeof value && ! Array.isArray( value );
}

/**
 * @param {unknown} value
 * @return {string}
 */
function entryKey( value ) {
	return 'string' === typeof value ? value : JSON.stringify( value );
}

/**
 * Flatten one section into leaf keys. List entries become `prefix: entry`,
 * object keys nest as `outer.inner`, and scalars keep their value.
 *
 * @param {unknown}           value
 * @param {string}            prefix
 * @param {Map<string, Leaf>} leaves
 * @return {Map<string, Leaf>}
 */
function flatten( value, prefix = '', leaves = new Map() ) {
	if ( Array.isArray( value ) ) {
		for ( const entry of value ) {
			const key = entryKey( entry );
			leaves.set( prefix ? `${ prefix }: ${ key }` : key, { entry: true } );
		}
	} else if ( isPlainObject( value ) ) {
		for ( const [ key, child ] of Object.entries( value ) ) {
			flatten( child, prefix ? `${ prefix }.${ key }` : key, leaves );
		}
	} else {
		leaves.set( prefix, { entry: false, value } );
	}

	return leaves;
}

/**
 * List every difference between two surfaces, sorted by section then key.
 *
 * @param {Record<string, unknown>} baseline
 * @param {Record<string, unknown>} current
 * @return {Change[]}
 */
function diffSurface( baseline, current ) {
	/** @type {Change[]} */
	const changes = [];
	const sections = [
		...new Set( [ ...Object.keys( baseline ), ...Object.keys( current ) ] ),
	]
		.filter( ( section ) => 'wp_version' !== section )
		.sort();

	for ( const section of sections ) {
		const before = flatten( baseline[ section ] ?? [] );
		const after = flatten( current[ section ] ?? [] );
		const keys = [ ...new Set( [ ...before.keys(), ...after.keys() ] ) ].sort();

		for ( const key of keys ) {
			const was = before.get( key );
			const now = after.get( key );

			if ( ! was && now ) {
				changes.push(
					now.entry
						? { section, kind: 'added', key }
						: { section, kind: 'added', key, after: now.value }
				);
			} else if ( was && ! now ) {
				changes.push(
					was.entry
						? { section, kind: 'removed', key }
						: { section, kind: 'removed', key, before: was.value }
				);
			} else if ( was && now && ! ( was.entry && now.entry ) ) {
				const beforeValue = was.entry ? '(list entry)' : was.value;
				const afterValue = now.entry ? '(list entry)' : now.value;

				if ( JSON.stringify( beforeValue ) !== JSON.stringify( afterValue ) ) {
					changes.push( {
						section,
						kind: 'changed',
						key,
						before: beforeValue,
						after: afterValue,
					} );
				}
			}
		}
	}

	return changes;
}

/**
 * Render the changes as markdown, grouped by section.
 *
 * @param {Change[]} changes
 * @param {string}   baselineVersion
 * @param {string}   currentVersion
 * @return {string}
 */
function renderReport( changes, baselineVersion, currentVersion ) {
	const versions = `WordPress ${ currentVersion } against the ${ baselineVersion } baseline`;

	if ( 0 === changes.length ) {
		return `No tracked core surface changed: ${ versions }.\n`;
	}

	const lines = [
		`## Core surface changes: ${ versions }`,
		'',
		`${ changes.length } change${ 1 === changes.length ? '' : 's' } to the parts of core Disable Blog depends on.`,
	];

	/** @type {Map<string, Change[]>} */
	const bySection = new Map();
	for ( const change of changes ) {
		const group = bySection.get( change.section ) ?? [];
		group.push( change );
		bySection.set( change.section, group );
	}

	for ( const [ section, group ] of bySection ) {
		lines.push( '', `### \`${ section }\``, '' );
		for ( const change of group ) {
			let line = `- ${ change.kind }: \`${ change.key }\``;
			if ( 'changed' === change.kind ) {
				line += ` from \`${ JSON.stringify( change.before ) }\` to \`${ JSON.stringify( change.after ) }\``;
			} else if ( 'after' in change ) {
				line += ` = \`${ JSON.stringify( change.after ) }\``;
			} else if ( 'before' in change ) {
				line += ` (was \`${ JSON.stringify( change.before ) }\`)`;
			}
			lines.push( line );
		}
	}

	return `${ lines.join( '\n' ) }\n`;
}

/**
 * Render a check that could not compare, so a stale report is never left behind.
 *
 * @param {unknown} error
 * @return {string}
 */
function renderFailure( error ) {
	const message = error instanceof Error ? error.message : String( error );
	return [
		'## Core surface check failed',
		'',
		'The surface could not be dumped or compared, so no changes were checked.',
		'',
		'```',
		message,
		'```',
		'',
	].join( '\n' );
}

/**
 * Recursively sort object keys so the JSON is stable to diff and review.
 *
 * @param {unknown} value
 * @return {unknown}
 */
function sortKeys( value ) {
	if ( Array.isArray( value ) ) {
		return value.map( sortKeys );
	}
	if ( isPlainObject( value ) ) {
		return Object.fromEntries(
			Object.keys( value )
				.sort()
				.map( ( key ) => [ key, sortKeys( value[ key ] ) ] )
		);
	}
	return value;
}

/**
 * The container path of the plugin checkout: the mapping wp-env gives the
 * repository root, or the checkout's basename when nothing maps it.
 *
 * @return {string}
 */
function pluginContainerPath() {
	for ( const file of [ '.wp-env.override.json', '.wp-env.json' ] ) {
		const configPath = path.join( REPO_ROOT, file );
		if ( ! fs.existsSync( configPath ) ) {
			continue;
		}
		const config = JSON.parse( fs.readFileSync( configPath, 'utf8' ) );
		/** @type {Record<string, string>} */
		const mappings = {
			...( config.mappings ?? {} ),
			...( config.env?.tests?.mappings ?? {} ),
		};
		for ( const [ target, source ] of Object.entries( mappings ) ) {
			if (
				target.startsWith( 'wp-content/plugins/' ) &&
				path.resolve( REPO_ROOT, source ) === path.resolve( REPO_ROOT )
			) {
				return target;
			}
		}
	}

	return `wp-content/plugins/${ path.basename( path.resolve( REPO_ROOT ) ) }`;
}

/**
 * Run the dump inside the tests site with plugins and fixtures off.
 *
 * @return {Record<string, unknown>}
 */
function dump() {
	const wpEnv = path.join( REPO_ROOT, 'node_modules', '.bin', 'wp-env' );
	const dir = `${ pluginContainerPath() }/tests/compat`;
	const result = spawnSync(
		wpEnv,
		[
			'run',
			'tests-cli',
			'--',
			'wp',
			`--require=${ dir }/isolate-fixtures.php`,
			'eval-file',
			`${ dir }/dump-surface.php`,
			'--skip-plugins',
		],
		{ cwd: REPO_ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }
	);

	if ( result.error ) {
		throw result.error;
	}
	if ( 0 !== result.status ) {
		const output = `${ result.stdout ?? '' }\n${ result.stderr ?? '' }`
			.split( '\n' )
			.filter( ( line ) => '' !== line.trim() )
			.slice( -20 )
			.join( '\n' );
		throw new Error(
			`Surface dump exited with status ${ result.status }:\n${ output }`
		);
	}

	return /** @type {Record<string, unknown>} */ (
		sortKeys( extractJson( result.stdout ) )
	);
}

/**
 * @param {string[]} argv
 * @return {number} Exit code.
 */
function main( argv ) {
	const command = argv[ 0 ];

	if ( 'dump' === command ) {
		process.stdout.write( `${ JSON.stringify( dump(), null, '\t' ) }\n` );
		return 0;
	}

	if ( 'update' === command ) {
		const surface = dump();
		fs.mkdirSync( path.dirname( BASELINE_PATH ), { recursive: true } );
		fs.writeFileSync( BASELINE_PATH, `${ JSON.stringify( surface, null, '\t' ) }\n` );
		console.log(
			`Wrote the WordPress ${ surface.wp_version } surface to ${ path.relative( REPO_ROOT, BASELINE_PATH ) }.`
		);
		return 0;
	}

	if ( 'check' === command ) {
		fs.mkdirSync( path.dirname( REPORT_PATH ), { recursive: true } );
		let report = '';
		let exitCode = 1;
		try {
			const baseline = JSON.parse( fs.readFileSync( BASELINE_PATH, 'utf8' ) );
			const current = dump();
			const changes = diffSurface( baseline, current );
			report = renderReport(
				changes,
				String( baseline.wp_version ),
				String( current.wp_version )
			);
			exitCode = 0 === changes.length ? 0 : 1;
		} catch ( error ) {
			report = renderFailure( error );
		}
		fs.writeFileSync( REPORT_PATH, report );
		process.stdout.write( report );
		return exitCode;
	}

	console.error( 'Usage: node tests/compat/surface.js dump|check|update' );
	return 2;
}

if ( require.main === module ) {
	try {
		process.exitCode = main( process.argv.slice( 2 ) );
	} catch ( error ) {
		console.error( error instanceof Error ? error.message : error );
		process.exitCode = 1;
	}
}

module.exports = { extractJson, diffSurface, renderReport, renderFailure };
