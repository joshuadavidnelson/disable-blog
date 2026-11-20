/**
 * `uninstall.php` deleting `dwpb_version` and `dwpb_previous_version`, across
 * both contexts that reach it: the browser-based admin Delete flow, and
 * WP-CLI.
 *
 * Deletes options and (for one test) plugin files, so it lives in the
 * 'lifecycle' Playwright project, which runs serially after every other spec
 * file has finished.
 *
 * The admin flow runs against a throwaway copy of the plugin rather than the
 * plugin itself: `.wp-env.json` maps the repository root onto
 * `wp-content/plugins/disable-blog`, so a genuine `delete_plugins()` against
 * `disable-blog` would recursively delete the working tree through that bind
 * mount. The copy's main file is named `disable-blog.php` on purpose -- the
 * admin branch of the guard requires `strpos( $_REQUEST['plugin'],
 * 'disable-blog.php' )` to match, and the delete request sends
 * `<directory>/<main file>` -- and its `uninstall.php` is copied from the live
 * plugin directory on every install, so the probe can never exercise a stale
 * copy of the file under test.
 *
 * The guard's *rejection* path has no test here. Every route to `uninstall.php`
 * in a web request runs through core's own `wp_ajax_delete_plugin()`, which
 * checks the `updates` nonce and `delete_plugins` before the plugin's guard is
 * ever reached, so a request the guard would reject is one core has already
 * rejected. The guard is defense in depth, and reaching it with bad input takes
 * synthesizing the include rather than making a request.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { PLUGIN_SLUG } from '../../config/roles';
import { expectCliSuccess, wpCli, wpCliOk } from '../../config/wp-cli';

/** Directory of the throwaway copy, under `wp-content/plugins/`. */
const PROBE_SLUG = 'dwpb-uninstall-probe';

/** How the admin delete request and WP-CLI identify the copy. */
const PROBE_FILE = `${ PROBE_SLUG }/disable-blog.php`;

/** The two options `uninstall.php` exists to remove. */
type VersionOptions = {
	dwpb_version: string | null;
	dwpb_previous_version: string | null;
};

/**
 * Values seeded before each uninstall. Distinct from any real `DWPB_VERSION`
 * so a restored-by-accident value is recognizable.
 */
const SEEDED: VersionOptions = {
	dwpb_version: '9.9.9-uninstall-probe',
	dwpb_previous_version: '9.9.8-uninstall-probe',
};

const BOTH_ABSENT: VersionOptions = {
	dwpb_version: null,
	dwpb_previous_version: null,
};

/**
 * WP-CLI's report for a single successful uninstall.
 *
 * Asserted on because the exit code cannot carry this: the bug this spec
 * covers had `uninstall.php` calling `exit()` under WP-CLI, which ended the
 * process at status 0 before WP-CLI printed anything at all.
 */
const CLI_UNINSTALL_SUCCESS = 'Success: Uninstalled 1 of 1 plugins.';

/**
 * (Re)create the throwaway copy, replacing whatever a previous run left.
 */
const INSTALL_PROBE_PHP = `
$dir = WP_PLUGIN_DIR . "/${ PROBE_SLUG }";
foreach ( glob( $dir . "/*" ) ?: array() as $file ) {
	unlink( $file );
}
@rmdir( $dir );
wp_mkdir_p( $dir );
if ( ! copy( WP_PLUGIN_DIR . "/${ PLUGIN_SLUG }/uninstall.php", $dir . "/uninstall.php" ) ) {
	WP_CLI::error( "could not copy uninstall.php into the probe" );
}
file_put_contents( $dir . "/disable-blog.php", "<?php\\n/**\\n * Plugin Name: DWPB Uninstall Probe\\n */\\n" );
`;

const REMOVE_PROBE_PHP = `
$dir = WP_PLUGIN_DIR . "/${ PROBE_SLUG }";
foreach ( glob( $dir . "/*" ) ?: array() as $file ) {
	unlink( $file );
}
@rmdir( $dir );
`;

const PROBE_STATE_PHP = `
echo is_dir( WP_PLUGIN_DIR . "/${ PROBE_SLUG }" ) ? "present" : "absent";
`;

/**
 * Read both options in one round trip.
 *
 * Via `wp eval` rather than `wp option get`, which exits non-zero for a
 * missing option and so reports "deleted" and "wp-cli broke" identically.
 */
async function readVersionOptions(): Promise< VersionOptions > {
	const json = await wpCliOk( [
		'eval',
		'echo json_encode( array( "dwpb_version" => get_option( "dwpb_version", null ), "dwpb_previous_version" => get_option( "dwpb_previous_version", null ) ) );',
	] );

	return JSON.parse( json ) as VersionOptions;
}

async function installProbe(): Promise< void > {
	await wpCliOk( [ 'eval', INSTALL_PROBE_PHP ] );
	expect( await wpCliOk( [ 'eval', PROBE_STATE_PHP ] ) ).toBe( 'present' );
}

/**
 * Seed both options and confirm they took -- their absence afterwards means
 * nothing if they were never there.
 */
async function seedVersionOptions(): Promise< void > {
	await wpCliOk( [ 'option', 'update', 'dwpb_version', SEEDED.dwpb_version as string ] );
	await wpCliOk( [
		'option',
		'update',
		'dwpb_previous_version',
		SEEDED.dwpb_previous_version as string,
	] );

	expect( await readVersionOptions(), 'seeding the version options' ).toEqual( SEEDED );
}

/**
 * Assert both options exist, whatever their values.
 *
 * Separate from {@link seedVersionOptions} because loading any admin page runs
 * `Disable_Blog::upgrade_check()`, which rewrites `dwpb_version` to
 * `DWPB_VERSION` and moves the previous value into `dwpb_previous_version`.
 * The seeded values do not survive that; their presence does, and presence is
 * what makes a later absence meaningful.
 *
 * @param label When this ran, for the failure message.
 */
async function expectVersionOptionsPresent( label: string ): Promise< void > {
	const options = await readVersionOptions();

	expect( options.dwpb_version, `${ label }: dwpb_version` ).not.toBeNull();
	expect( options.dwpb_previous_version, `${ label }: dwpb_previous_version` ).not.toBeNull();
}

/**
 * @param label What ran the uninstall, for the failure message.
 */
async function expectVersionOptionsDeleted( label: string ): Promise< void > {
	expect(
		await readVersionOptions(),
		`${ label } left the version options in the database`
	).toEqual( BOTH_ABSENT );
}

test.describe( 'lifecycle: uninstall cleanup', () => {
	test.describe.configure( { mode: 'serial' } );

	let originalOptions: VersionOptions;

	test.beforeAll( async () => {
		originalOptions = await readVersionOptions();
	} );

	// Unconditional even on failure -- every test here deletes the options, and
	// the last one deactivates the plugin.
	test.afterAll( async () => {
		await wpCli( [ 'eval', REMOVE_PROBE_PHP ] );
		await wpCli( [ 'plugin', 'activate', PLUGIN_SLUG ] );

		for ( const [ option, value ] of Object.entries( originalOptions ) ) {
			if ( null === value ) {
				await wpCli( [ 'option', 'delete', option ] );
			} else {
				await wpCli( [ 'option', 'update', option, value ] );
			}
		}
	} );

	test( 'the admin Delete flow deletes the version options', async ( { admin, page } ) => {
		await installProbe();
		await seedVersionOptions();

		await admin.visitAdminPage( 'plugins.php' );

		const row = page.locator( `tr[data-plugin="${ PROBE_FILE }"]` );
		await expect( row ).toBeVisible();

		// Control, taken after the page load rather than before it -- see
		// expectVersionOptionsPresent() on what that load does to the values.
		await expectVersionOptionsPresent( 'after loading plugins.php' );

		// core's `updates.js` intercepts this link, confirms, and POSTs the
		// `delete-plugin` action with the `updates` nonce -- the one request
		// shape uninstall.php's admin branch accepts. `a.delete` is the
		// selector core's own handler binds to.
		page.once( 'dialog', ( dialog ) => dialog.accept() );

		const ajaxResponse = page.waitForResponse(
			( response ) =>
				response.url().includes( 'admin-ajax.php' ) &&
				true === response.request().postData()?.includes( 'action=delete-plugin' )
		);

		await row.locator( 'a.delete' ).click();

		const body = await ( await ajaxResponse ).json();
		expect( body.success, `delete-plugin responded ${ JSON.stringify( body ) }` ).toBe( true );

		// Control: the delete really ran, rather than the options having been
		// cleared by something else.
		expect( await wpCliOk( [ 'eval', PROBE_STATE_PHP ] ) ).toBe( 'absent' );

		await expectVersionOptionsDeleted( 'the admin delete flow' );
	} );

	test( 'wp plugin uninstall deletes the version options', async () => {
		await installProbe();
		await seedVersionOptions();

		const result = await wpCli( [ 'plugin', 'uninstall', PROBE_SLUG, '--skip-delete' ] );

		expectCliSuccess( result, `wp plugin uninstall ${ PROBE_SLUG }` );
		expect( result.stdout ).toContain( CLI_UNINSTALL_SUCCESS );

		await expectVersionOptionsDeleted( `wp plugin uninstall ${ PROBE_SLUG }` );
	} );

	test( 'wp plugin uninstall deletes the version options for the plugin itself', async () => {
		await seedVersionOptions();

		// --skip-delete runs `uninstall_plugin()` without removing the files,
		// which here are the bind-mounted repository (see the file docblock).
		const result = await wpCli( [
			'plugin',
			'uninstall',
			PLUGIN_SLUG,
			'--deactivate',
			'--skip-delete',
		] );

		expectCliSuccess( result, `wp plugin uninstall ${ PLUGIN_SLUG }` );
		expect( result.stdout ).toContain( CLI_UNINSTALL_SUCCESS );

		await expectVersionOptionsDeleted( `wp plugin uninstall ${ PLUGIN_SLUG }` );

		// Files survived --skip-delete, so the site goes straight back to
		// normal for anything that runs after this.
		expectCliSuccess(
			await wpCli( [ 'plugin', 'activate', PLUGIN_SLUG ] ),
			`wp plugin activate ${ PLUGIN_SLUG }`
		);
	} );
} );
