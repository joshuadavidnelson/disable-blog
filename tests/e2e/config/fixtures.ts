/**
 * Toggles for the mu-plugin fixtures mapped into `wp-content/mu-plugins/`.
 *
 * Each fixture registers its hooks unconditionally and stays inert until its
 * option is switched on, so a spec can flip one with a single
 * `PUT /wp/v2/settings` instead of a WP-CLI round trip.
 *
 * `tests/e2e/fixtures/` is mapped into both the :8888 dev site and the :8889
 * test site (see `.wp-env.json`), so a toggle left on pollutes local
 * development, not just the next test run. Calling `resetFixtures()` in
 * `afterEach`/`afterAll` is not optional.
 *
 * @see tests/e2e/fixtures/
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Option name of each fixture toggle, keyed by a readable alias.
 */
export const FIXTURE_TOGGLES = {
	frontEndRedirectsOff: 'dwpb_test_front_end_redirects_off',
	adminRedirectsOff: 'dwpb_test_admin_redirects_off',
	/** Integer status code override for redirects; `0` means "off" (use the plugin's default). */
	redirectStatusCode: 'dwpb_test_redirect_status_code',
	queryStringPassthrough: 'dwpb_test_query_string_passthrough',
	customRedirectUrl: 'dwpb_test_custom_redirect_url',
	cptEnabled: 'dwpb_test_cpt_enabled',
	authorArchivesDisabled: 'dwpb_test_author_archives_disabled',
	authorArchiveCpt: 'dwpb_test_author_archive_cpt',
	commentsUnsupported: 'dwpb_test_comments_unsupported',
	feedDieMessage: 'dwpb_test_feed_die_message',
	removeOptionsWriting: 'dwpb_test_remove_options_writing',
	xmlrpcRestore: 'dwpb_test_xmlrpc_restore',
} as const;

export type FixtureToggle =
	( typeof FIXTURE_TOGGLES )[ keyof typeof FIXTURE_TOGGLES ];

/**
 * Value each toggle returns to when a spec is done with it.
 *
 * Every toggle is boolean except `redirectStatusCode`, which is an integer
 * status code where `0` means "off".
 */
const TOGGLE_OFF: Record< FixtureToggle, boolean | number > = {
	[ FIXTURE_TOGGLES.frontEndRedirectsOff ]: false,
	[ FIXTURE_TOGGLES.adminRedirectsOff ]: false,
	[ FIXTURE_TOGGLES.redirectStatusCode ]: 0,
	[ FIXTURE_TOGGLES.queryStringPassthrough ]: false,
	[ FIXTURE_TOGGLES.customRedirectUrl ]: false,
	[ FIXTURE_TOGGLES.cptEnabled ]: false,
	[ FIXTURE_TOGGLES.authorArchivesDisabled ]: false,
	[ FIXTURE_TOGGLES.authorArchiveCpt ]: false,
	[ FIXTURE_TOGGLES.commentsUnsupported ]: false,
	[ FIXTURE_TOGGLES.feedDieMessage ]: false,
	[ FIXTURE_TOGGLES.removeOptionsWriting ]: false,
	[ FIXTURE_TOGGLES.xmlrpcRestore ]: false,
};

/**
 * Switch one or more fixture toggles.
 *
 * @param requestUtils Admin request utils.
 * @param toggles      Map of option name to value.
 */
export async function setFixtures(
	requestUtils: RequestUtils,
	toggles: Partial< Record< FixtureToggle, boolean | number > >
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
	const payload: Partial< Record< FixtureToggle, boolean | number > > = {};

	for ( const toggle of toggles ) {
		payload[ toggle ] = TOGGLE_OFF[ toggle ];
	}

	await setFixtures( requestUtils, payload );
}
