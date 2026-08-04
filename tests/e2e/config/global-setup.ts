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
import { resetFilterOverrides } from './filter-overrides';
import { ADMIN_STORAGE_STATE, PLUGIN_SLUG, THEME_SLUG } from './roles';
import { resetContent, setupSite } from './seed';

/**
 * Prepare the wp-env test site once, before any project runs: authenticate,
 * pin the theme, activate the plugin, seed the Home/Blog pages the redirect
 * behaviour depends on, and clear content/fixtures left by a previous run.
 *
 * Order matters — auth before anything, theme before any markup-asserting
 * step, plugin active before content/settings are touched, `setupSite()`
 * before redirects are testable (the plugin's redirect logic is gated on
 * `page_on_front` being set), then reset content and fixtures last so a run
 * that died mid-test doesn't leave state for the next run to trip over.
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

	await requestUtils.setupRest();

	// Pinned rather than inherited: the default theme varies by WordPress
	// version, which would silently change the markup specs assert against.
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
	await resetFilterOverrides( requestUtils );

	await requestContext.dispose();
}

export default globalSetup;
