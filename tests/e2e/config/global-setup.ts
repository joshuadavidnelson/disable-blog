/**
 * External dependencies
 */
import { request } from '@playwright/test';
import type { FullConfig } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { resetFixtures } from './fixtures';
import { ADMIN_STORAGE_STATE, PLUGIN_SLUG, THEME_SLUG } from './roles';
import { resetContent, setupSite } from './seed';

/**
 * Prepare the wp-env test site once, before any project runs.
 *
 * Signs in as the wp-env admin and persists the authenticated state so the
 * `auth` setup project and every spec have a session to start from, pins the
 * theme the suite was written against, makes sure the plugin under test is
 * active, seeds the Home/Blog pages and reading settings the redirect
 * behaviour depends on, and clears content and fixture toggles left over
 * from a previous run.
 *
 * Order matters:
 *  1. Authenticate — every later step needs a session.
 *  2. Activate the pinned theme — every step after this renders markup, and
 *     the suite asserts against that markup, so the theme has to be settled
 *     before anything else runs.
 *  3. Activate the plugin — the behaviour under test (post type visibility,
 *     admin notices, front-end redirects) only exists while Disable Blog is
 *     running, so nothing that inspects content or settings should run
 *     before this.
 *  4. `setupSite()` (POST `dwpb-test/v1/setup`) creates the Home/Blog pages
 *     and sets `show_on_front` / `page_on_front` / `page_for_posts` /
 *     `permalink_structure`, then flushes rewrites. This is what makes
 *     redirects testable at all: the plugin's entire front-end redirect
 *     block is gated on `get_option( 'page_on_front' )` being non-zero, so
 *     without this step every redirect spec would run against a site that
 *     never triggers the code path it is trying to exercise.
 *  5. `resetContent()` (POST `dwpb-test/v1/reset-content`) clears posts,
 *     non-Home/Blog pages, comments and terms stranded by a previous run
 *     that died before its own teardown — a run that dies mid-test leaves
 *     content in the database that silently changes the starting state (and
 *     therefore the assertions) of the *next* run.
 *  6. `resetFixtures()` switches every mu-plugin fixture toggle back off, for
 *     the same reason: a toggle left on by a run that died mid-test would
 *     otherwise leave a fixture filter hooked for specs that never opted
 *     into it.
 *
 * @param config Resolved Playwright config.
 */
async function globalSetup( config: FullConfig ): Promise< void > {
	const { baseURL } = config.projects[ 0 ].use;

	const requestContext = await request.newContext( { baseURL } );
	const requestUtils = new RequestUtils( requestContext, {
		baseURL,
		storageStatePath: ADMIN_STORAGE_STATE,
	} );

	// Authenticate and write the admin storage state to disk.
	await requestUtils.setupRest();

	// Pin the theme instead of inheriting whatever the running WordPress
	// version happens to ship as its default: on the long-lived local dev
	// volume that's Twenty Twenty-Four, on a clean install of current
	// WordPress it's Twenty Twenty-Five, and on WordPress 6.2 it's Twenty
	// Twenty-Three. Each of those has different markup, so specs asserting
	// against the DOM would silently start failing (or passing for the
	// wrong reason) depending purely on which WordPress version CI happened
	// to spin up — turning a version-matrix job red and flaky instead of a
	// clean pass/fail. Twenty Twenty-Two is pinned because it only requires
	// WordPress 5.9+, so — unlike Twenty Twenty-Four, which needs 6.4+ — it
	// installs and activates cleanly across the plugin's whole supported
	// version range. `.wp-env.json` installs it explicitly via `themes` so
	// it exists regardless of which themes a given core tarball bundles.
	//
	// Activation failure throws rather than being swallowed: continuing
	// against whatever theme happened to already be active would produce
	// confusing DOM-assertion failures scattered across the suite instead
	// of one clear error here.
	try {
		await requestUtils.activateTheme( THEME_SLUG );
	} catch ( error ) {
		throw new Error(
			`Failed to activate the "${ THEME_SLUG }" theme required by the e2e suite. ` +
				'Every spec asserts against this theme\'s markup, so the run cannot ' +
				'continue against whatever theme is currently active.',
			{ cause: error }
		);
	}

	// The plugin under test must be active before anything else touches
	// content or settings.
	await requestUtils.activatePlugin( PLUGIN_SLUG );

	// Seed the Home/Blog pages and reading settings the redirect behaviour
	// depends on.
	await setupSite( requestUtils );

	// Start from a clean content set and stock fixture behaviour.
	await resetContent( requestUtils );
	await resetFixtures( requestUtils );

	await requestContext.dispose();
}

export default globalSetup;
