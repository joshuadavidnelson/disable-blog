/**
 * WP-CLI bridge for the specs that need genuine command-line semantics —
 * used sparingly, since seeding normally goes through the cheaper
 * `dwpb-test/v1` REST fixture. Always targets the tests instance (:8889).
 */
import { execFile } from 'node:child_process';
import path from 'node:path';
import { promisify } from 'node:util';

const execFileAsync = promisify( execFile );

/**
 * Repo root — `wp-env` resolves `.wp-env.json` relative to the process cwd.
 */
const REPO_ROOT = path.join( __dirname, '..', '..', '..' );

/**
 * Local `wp-env` binary. Invoked directly rather than through `npx` to skip
 * npx's package resolution on every call.
 */
const WP_ENV_BIN = path.join( REPO_ROOT, 'node_modules', '.bin', 'wp-env' );

/**
 * wp-env service running WP-CLI against the tests site.
 */
export const CLI_CONTAINER = 'tests-cli';

export interface WpCliResult {
	exitCode: number;
	stdout: string;
	stderr: string;
}

/**
 * Run a `wp` command inside the tests container. Never throws on a non-zero
 * exit — the caller asserts the returned exit code instead.
 *
 * @param args `wp` arguments, already split (no shell quoting needed).
 */
export async function wpCli( args: string[] ): Promise< WpCliResult > {
	try {
		const { stdout, stderr } = await execFileAsync(
			WP_ENV_BIN,
			[ 'run', CLI_CONTAINER, '--', 'wp', ...args ],
			{ cwd: REPO_ROOT, maxBuffer: 10 * 1024 * 1024 }
		);

		return { exitCode: 0, stdout: stdout.trim(), stderr: stderr.trim() };
	} catch ( error ) {
		const failure = error as {
			code?: number;
			stdout?: string;
			stderr?: string;
		};

		return {
			exitCode: failure.code ?? 1,
			stdout: ( failure.stdout ?? '' ).trim(),
			stderr: ( failure.stderr ?? '' ).trim(),
		};
	}
}

/**
 * Run a command and fail loudly if it did not succeed.
 *
 * @param args `wp` arguments.
 * @return Trimmed stdout.
 */
export async function wpCliOk( args: string[] ): Promise< string > {
	const result = await wpCli( args );

	if ( 0 !== result.exitCode ) {
		throw new Error(
			`wp ${ args.join( ' ' ) } exited ${ result.exitCode }\n${
				result.stderr
			}`
		);
	}

	return result.stdout;
}
