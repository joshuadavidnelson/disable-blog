/**
 * Plugin (de)activation and upgrade handling. The only spec that mutates
 * activation state, so it runs in its own 'lifecycle' Playwright project,
 * guaranteed by a project dependency to run after every other spec file has
 * finished. `mode: 'serial'` keeps its tests ordered (deactivate before
 * reactivate; corrupt `dwpb_version` before triggering the fix), and
 * `afterAll` restores activation state and the version options
 * unconditionally, even if an assertion above it failed.
 *
 * The `edit.php` redirect (see `admin/admin-redirects.spec.ts`) only proves
 * WordPress's own `active_plugins` toggle works -- it would look identical
 * even if `Disable_Blog_Activator::activate()` / `Disable_Blog_Deactivator::deactivate()`
 * were empty. Both methods' one WP-CLI-observable side effect,
 * `delete_transient( 'wc_count_comments' )`, is primed and checked directly
 * around each (de)activation to cover the method bodies themselves; their
 * other calls (`wp_cache_delete()`, `flush_rewrite_rules()`) have no effect
 * observable across a separate WP-CLI process, so they stay uncovered by
 * design.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { siteConfig } from '../../config/seed';
import { adminUrl, editPhp } from '../../config/admin';
import { expectRedirect, expectStatus } from '../../config/redirects';
import { PLUGIN_SLUG } from '../../config/roles';
import { wpCli, wpCliOk } from '../../config/wp-cli';

/** A stand-in old version, distinct from any real released `DWPB_VERSION`. */
const OLD_VERSION = '0.0.1';

/**
 * The transient both `Disable_Blog_Activator::activate()` and
 * `Disable_Blog_Deactivator::deactivate()` delete as one of their few
 * WP-CLI-observable side effects.
 */
const PROBE_TRANSIENT = 'wc_count_comments';
const PROBE_VALUE = 'lifecycle-probe-value';

/**
 * Assert a `wp-cli` command exited cleanly with no PHP fatal in its output.
 *
 * @param result Result of a `wpCli()` call.
 * @param label  Human-readable label for the assertion failure message.
 */
function expectCliSuccess( result: { exitCode: number; stdout: string; stderr: string }, label: string ): void {
	expect( result.exitCode, `${ label } exited ${ result.exitCode }:\n${ result.stderr }` ).toBe( 0 );
	expect( result.stdout + result.stderr ).not.toMatch( /fatal error/i );
}

/**
 * Sets `PROBE_TRANSIENT` and confirms it took, so its later absence is
 * meaningful rather than the transient having simply never been set.
 */
async function primeProbeTransient(): Promise< void > {
	await wpCliOk( [ 'transient', 'set', PROBE_TRANSIENT, PROBE_VALUE ] );
	expect( await wpCliOk( [ 'transient', 'get', PROBE_TRANSIENT ] ) ).toBe( PROBE_VALUE );
}

async function expectProbeTransientCleared(): Promise< void > {
	expect( await wpCliOk( [ 'transient', 'get', PROBE_TRANSIENT ] ) ).toBe( '' );
}

test.describe( 'lifecycle: (de)activation and upgrade_check()', () => {
	test.describe.configure( { mode: 'serial' } );

	let editPageTarget: string;
	let currentVersion: string;
	let originalDwpbVersion: string;
	let hadPreviousVersion: boolean;
	let originalPreviousVersion: string;

	test.beforeAll( async ( { requestUtils } ) => {
		const config = await siteConfig( requestUtils );
		editPageTarget = `${ config.homeUrl }${ editPhp( 'page' ) }`;

		currentVersion = ( await wpCliOk( [ 'eval', 'echo DWPB_VERSION;' ] ) ).trim();
		originalDwpbVersion = ( await wpCliOk( [ 'option', 'get', 'dwpb_version' ] ) ).trim();

		const previous = await wpCli( [ 'option', 'get', 'dwpb_previous_version' ] );
		hadPreviousVersion = 0 === previous.exitCode;
		originalPreviousVersion = previous.stdout.trim();
	} );

	// Unconditional even on failure -- a re-run of the whole suite would
	// otherwise inherit the mess.
	test.afterAll( async () => {
		await wpCli( [ 'plugin', 'activate', PLUGIN_SLUG ] );
		await wpCli( [ 'option', 'update', 'dwpb_version', originalDwpbVersion ] );
		await wpCli( [ 'transient', 'delete', PROBE_TRANSIENT ] );

		if ( hadPreviousVersion ) {
			await wpCli( [ 'option', 'update', 'dwpb_previous_version', originalPreviousVersion ] );
		} else {
			await wpCli( [ 'option', 'delete', 'dwpb_previous_version' ] );
		}
	} );

	test( 'deactivating the plugin removes the edit.php redirect and clears the WooCommerce comment-count transient', async ( {
		request,
	} ) => {
		// Control: the redirect is present before deactivation.
		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );

		await primeProbeTransient();

		const result = await wpCli( [ 'plugin', 'deactivate', PLUGIN_SLUG ] );
		expectCliSuccess( result, `wp plugin deactivate ${ PLUGIN_SLUG }` );

		await expectStatus( request, adminUrl( 'edit.php' ), 200 );
		await expectProbeTransientCleared();
	} );

	test( 'reactivating the plugin restores the edit.php redirect and clears the WooCommerce comment-count transient', async ( {
		request,
	} ) => {
		await primeProbeTransient();

		const result = await wpCli( [ 'plugin', 'activate', PLUGIN_SLUG ] );
		expectCliSuccess( result, `wp plugin activate ${ PLUGIN_SLUG }` );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );
		await expectProbeTransientCleared();
	} );

	test( 'an admin page load rewrites a stale dwpb_version and records dwpb_previous_version', async ( {
		request,
	} ) => {
		await wpCliOk( [ 'option', 'update', 'dwpb_version', OLD_VERSION ] );
		await wpCli( [ 'option', 'delete', 'dwpb_previous_version' ] );

		// upgrade_check() only runs on an admin page load with is_admin()
		// true, so writing the option via wp-cli doesn't itself trigger it.
		expect( ( await wpCliOk( [ 'option', 'get', 'dwpb_version' ] ) ).trim() ).toBe( OLD_VERSION );

		await expectStatus( request, adminUrl( 'index.php' ), 200 );

		expect( ( await wpCliOk( [ 'option', 'get', 'dwpb_version' ] ) ).trim() ).toBe( currentVersion );
		expect( ( await wpCliOk( [ 'option', 'get', 'dwpb_previous_version' ] ) ).trim() ).toBe( OLD_VERSION );
	} );
} );
