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

// `@wordpress/scripts`' shared Playwright config reads these at require time,
// so they have to be set before it is loaded. Pointing `STORAGE_STATE_PATH` at
// our admin state keeps the `requestUtils` worker fixture and the browser
// projects on a single file.
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

	// `@wordpress/scripts`' base config already sets this to 1, but only as an
	// inherited default — this repo never pins it itself, so a future upstream
	// config change could silently parallelise workers. The whole suite shares
	// one wp-env site and assumes serial execution: `global-setup.ts` wipes all
	// content once up front rather than per test, and specs seed/clean up
	// against that single shared site. Pinned here so the assumption is
	// enforced by this repo, not borrowed from upstream.
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
			dependencies: [ 'auth' ],
		},
	],
} );
