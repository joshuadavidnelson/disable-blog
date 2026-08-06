/**
 * Toggles for the mu-plugin fixtures mapped into `wp-content/mu-plugins/`.
 *
 * Each fixture registers its hooks unconditionally and stays inert until its
 * option is switched on, so a spec can flip one with a single
 * `PUT /wp/v2/settings` instead of a WP-CLI round trip.
 *
 * `tests/e2e/fixtures/` mounts into both the :8888 dev site and the :8889
 * test site (see `.wp-env.json`), but each fixture file is inert unless
 * `DWPB_TEST_FIXTURES` is defined, a constant only the tests environment
 * sets — so a toggle left on has no effect on :8888.
 *
 * A toggle exists only where a spec needs behaviour the plugin cannot reach
 * on its own; for a one-off filter value, prefer `setFilterOverrides()` in
 * `config/filter-overrides.ts`, which needs no new fixture.
 *
 * Which spec owns which toggle, relative to `tests/e2e/specs/`:
 *
 *   cptEnabled                   filters/cpt-branches, filters/author-archives
 *   integrationsDisableComments  filters/integrations
 *   integrationsWoocommerce      filters/integrations
 *
 * @see tests/e2e/fixtures/
 * @see tests/e2e/config/filter-overrides.ts
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Option name of each fixture toggle, keyed by a readable alias.
 */
export const FIXTURE_TOGGLES = {
	cptEnabled: 'dwpb_test_cpt_enabled',
	integrationsDisableComments: 'dwpb_test_integrations_disable_comments',
	integrationsWoocommerce: 'dwpb_test_integrations_woocommerce',
} as const;

export type FixtureToggle =
	( typeof FIXTURE_TOGGLES )[ keyof typeof FIXTURE_TOGGLES ];

/**
 * Value each toggle returns to when a spec is done with it. Every remaining
 * toggle is boolean.
 */
const TOGGLE_OFF: Record< FixtureToggle, boolean > = {
	[ FIXTURE_TOGGLES.cptEnabled ]: false,
	[ FIXTURE_TOGGLES.integrationsDisableComments ]: false,
	[ FIXTURE_TOGGLES.integrationsWoocommerce ]: false,
};

/**
 * Switch one or more fixture toggles.
 *
 * @param requestUtils Admin request utils.
 * @param toggles      Map of option name to value.
 */
export async function setFixtures(
	requestUtils: RequestUtils,
	toggles: Partial< Record< FixtureToggle, boolean > >
): Promise< void > {
	await requestUtils.rest( {
		method: 'PUT',
		path: '/wp/v2/settings',
		data: toggles,
	} );
}

/**
 * Switch the named toggles back off.
 *
 * @param requestUtils Admin request utils.
 * @param toggles      Toggles to reset. Defaults to every known toggle.
 */
export async function resetFixtures(
	requestUtils: RequestUtils,
	toggles: FixtureToggle[] = Object.values( FIXTURE_TOGGLES )
): Promise< void > {
	const payload: Partial< Record< FixtureToggle, boolean > > = {};

	for ( const toggle of toggles ) {
		payload[ toggle ] = TOGGLE_OFF[ toggle ];
	}

	await setFixtures( requestUtils, payload );
}
