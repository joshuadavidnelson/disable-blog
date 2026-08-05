/**
 * Plugin (de)activation and upgrade handling -- the only spec in the suite
 * that mutates activation state, so it gets its own 'lifecycle' Playwright
 * project (see `playwright.config.ts`), guaranteed by a project dependency
 * to run only after every other spec file has finished. `mode: 'serial'`
 * keeps its own tests strictly ordered (deactivate before reactivate;
 * corrupt `dwpb_version` before triggering the fix), and the `afterAll`
 * below restores both activation state and the version options
 * unconditionally, even if an assertion above it failed.
 *
 * COVERAGE: `Disable_Blog_Activator::activate()` / `Disable_Blog_Deactivator::deactivate()`
 * (both otherwise 0% -- the plugin is activated exactly once, in
 * `global-setup.ts`, and never deactivated) and `Disable_Blog::upgrade_check()`
 * (includes/class-disable-blog.php), which reads/writes `dwpb_version` /
 * `dwpb_previous_version` on every admin page load where the stored version
 * doesn't match `DWPB_VERSION`.
 *
 * `edit.php`'s redirect to the pages list (`Disable_Blog_Admin::redirect_admin_pages()`,
 * see `admin/admin-redirects.spec.ts`) is the observable proxy for
 * activation state: it only exists while the plugin's hooks are registered,
 * so it disappears the moment the plugin is deactivated and comes back only
 * if reactivation genuinely re-registered them. That proxy alone only shows
 * WordPress's own `active_plugins` toggle works -- it would look identical
 * even if `Disable_Blog_Activator::activate()` / `Disable_Blog_Deactivator::deactivate()`
 * were empty. Both methods' one WP-CLI-observable side effect --
 * `delete_transient( 'wc_count_comments' )` -- is primed and checked
 * directly around each (de)activation to cover the method bodies
 * themselves. Their other two calls, `wp_cache_delete()` and
 * `flush_rewrite_rules()`, have no effect that survives across a separate
 * WP-CLI process (the object cache is not persistent here) or that is
 * distinguishable from WordPress's own request-time behaviour, so they stay
 * uncovered by design.
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
 * Set `PROBE_TRANSIENT` and confirm it actually took -- the control that
 * makes its later absence meaningful rather than the transient having
 * simply never been set.
 */
async function primeProbeTransient(): Promise< void > {
	await wpCliOk( [ 'transient', 'set', PROBE_TRANSIENT, PROBE_VALUE ] );
	expect( await wpCliOk( [ 'transient', 'get', PROBE_TRANSIENT ] ) ).toBe( PROBE_VALUE );
}

/**
 * Assert `PROBE_TRANSIENT` is gone -- only true if `delete_transient()`
 * ran inside the (de)activation hook that was just triggered.
 */
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

	// Hook-guaranteed teardown: runs even if an assertion above failed, so a
	// failure here can never leave the plugin deactivated or the version
	// options corrupted for the rest of the suite (this project runs last,
	// but a re-run of the whole suite would otherwise inherit the mess).
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

		// Non-vacuous: with the plugin's hooks gone, edit.php is plain core
		// behaviour again -- a 200, not a redirect.
		await expectStatus( request, adminUrl( 'edit.php' ), 200 );

		// Covers Disable_Blog_Deactivator::deactivate()'s own body, not just
		// the active_plugins toggle: only true if delete_transient() ran.
		await expectProbeTransientCleared();
	} );

	test( 'reactivating the plugin restores the edit.php redirect and clears the WooCommerce comment-count transient', async ( {
		request,
	} ) => {
		await primeProbeTransient();

		const result = await wpCli( [ 'plugin', 'activate', PLUGIN_SLUG ] );
		expectCliSuccess( result, `wp plugin activate ${ PLUGIN_SLUG }` );

		await expectRedirect( request, adminUrl( 'edit.php' ), editPageTarget, 301 );

		// Covers Disable_Blog_Activator::activate()'s own body, not just the
		// active_plugins toggle: only true if delete_transient() ran.
		await expectProbeTransientCleared();
	} );

	test( 'an admin page load rewrites a stale dwpb_version and records dwpb_previous_version', async ( {
		request,
	} ) => {
		await wpCliOk( [ 'option', 'update', 'dwpb_version', OLD_VERSION ] );
		await wpCli( [ 'option', 'delete', 'dwpb_previous_version' ] );

		// Control: writing the option directly doesn't itself trigger
		// upgrade_check() -- it only runs on an admin page load with
		// is_admin() true, which a wp-cli option write never is.
		expect( ( await wpCliOk( [ 'option', 'get', 'dwpb_version' ] ) ).trim() ).toBe( OLD_VERSION );

		await expectStatus( request, adminUrl( 'index.php' ), 200 );

		expect( ( await wpCliOk( [ 'option', 'get', 'dwpb_version' ] ) ).trim() ).toBe( currentVersion );
		expect( ( await wpCliOk( [ 'option', 'get', 'dwpb_previous_version' ] ) ).trim() ).toBe( OLD_VERSION );
	} );
} );
