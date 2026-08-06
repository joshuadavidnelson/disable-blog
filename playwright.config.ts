/**
 * External dependencies
 */
import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';
import type { PlaywrightTestConfig } from '@playwright/test';

/**
 * Internal dependencies
 */
import { ADMIN_STORAGE_STATE } from './tests/e2e/config/roles';

/**
 * Port of the wp-env *tests* instance (see `.wp-env.json`).
 *
 * wp-env 11 defaults `port`/`testsPort` to auto-select, so both are pinned in
 * `.wp-env.json` and the tests port is repeated here for the web server check.
 */
const TESTS_PORT = 8889;

// Must be set before `@wordpress/scripts`' shared config is required below —
// it reads these env vars at require time.
process.env.WP_ARTIFACTS_PATH ??= path.join( __dirname, 'artifacts' );
process.env.STORAGE_STATE_PATH ??= ADMIN_STORAGE_STATE;

// eslint-disable-next-line @typescript-eslint/no-var-requires -- deliberately loaded after the env vars above.
const baseConfig: PlaywrightTestConfig = require( '@wordpress/scripts/config/playwright.config.js' );

/**
 * E2E configuration for the Disable Blog plugin.
 *
 * Starts from the WordPress core defaults shipped by `@wordpress/scripts` and
 * overrides only what this plugin needs: our own test tree, our own global
 * setup, and the wp-env tests instance as the web server.
 */
export default defineConfig( {
	...baseConfig,

	// Covers both `specs/` (UI specs) and `config/auth.setup.ts` (the `auth`
	// project below).
	testDir: path.join( __dirname, 'tests', 'e2e' ),

	// Resolved relative to this config file.
	globalSetup: './tests/e2e/config/global-setup.ts',
	globalTeardown: './tests/e2e/config/global-teardown.ts',

	// Pinned (not just inherited from `@wordpress/scripts`): the suite shares
	// one wp-env site and assumes serial execution.
	workers: 1,

	use: {
		...baseConfig.use,
		storageState: ADMIN_STORAGE_STATE,
		trace: 'retain-on-failure',
	},

	webServer: {
		command: 'npm run env:start',
		port: TESTS_PORT,
		timeout: 600_000,
		reuseExistingServer: true,
	},

	projects: [
		{
			name: 'auth',
			testMatch: /auth\.setup\.ts$/,
		},
		// The harness gate: proves the plugin, theme, test-API fixture, and
		// per-role storage states are all in a trustworthy state before any
		// other spec runs. Depending on 'smoke' (rather than 'auth' directly)
		// makes 'chromium' run only once smoke has passed.
		{
			name: 'smoke',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: ADMIN_STORAGE_STATE,
			},
			testMatch: /\/smoke\.spec\.ts$/,
			dependencies: [ 'auth' ],
		},
		// Firefox and WebKit are deliberately deferred; chromium is the only
		// browser this suite targets.
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
				// Specs run as administrator unless they opt into another
				// role with `test.use( { storageState: storageStatePath( 'editor' ) } )`.
				storageState: ADMIN_STORAGE_STATE,
			},
			testMatch: /.*\.spec\.ts$/,
			// smoke.spec.ts runs under its own 'smoke' project (above);
			// everything in specs/lifecycle/ mutates plugin state and runs
			// under its own 'lifecycle' project (below) — neither is
			// interleaved with the rest of the suite.
			testIgnore: [ /\/smoke\.spec\.ts$/, /\/specs\/lifecycle\// ],
			dependencies: [ 'smoke' ],
		},
		// Mutates plugin activation state and the plugin's own options
		// (Disable_Blog_Activator / Disable_Blog_Deactivator /
		// Disable_Blog::upgrade_check() / uninstall.php coverage), so it must
		// never run interleaved with a spec that assumes the plugin is
		// continuously active. Depending on 'chromium' makes Playwright run
		// this project only once every other spec file has finished,
		// regardless of worker count. Matched by directory, so a new
		// state-mutating spec belongs in specs/lifecycle/ and needs no
		// change here.
		{
			name: 'lifecycle',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: ADMIN_STORAGE_STATE,
			},
			testMatch: /\/specs\/lifecycle\/.*\.spec\.ts$/,
			dependencies: [ 'chromium' ],
		},
	],
} );
