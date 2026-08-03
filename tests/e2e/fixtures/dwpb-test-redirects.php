<?php
/**
 * E2E mu-plugin fixture: front-end redirect-behavior toggles for the
 * Playwright suite.
 *
 * TOGGLES: five options, each registered via `register_setting( 'options', ... )`
 * so a spec can flip one with a single `PUT /wp-json/wp/v2/settings` call
 * instead of a WP-CLI round trip. Every option defaults to its "off" value, so
 * a spec that never touches this file sees stock plugin redirect behaviour.
 *
 *   dwpb_test_front_end_redirects_off    boolean  -> dwpb_redirect_front_end               returns false
 *   dwpb_test_admin_redirects_off        boolean  -> dwpb_redirect_admin                    returns false
 *   dwpb_test_redirect_status_code       integer  -> dwpb_redirect_status_code              returns the option's value (0 = off)
 *   dwpb_test_query_string_passthrough   boolean  -> dwpb_pass_query_string_on_redirect     returns true
 *                                                  -> dwpb_allowed_query_vars               returns array( 'utm_source' )
 *   dwpb_test_custom_redirect_url        boolean  -> dwpb_front_end_redirect_url            returns home_url( DWPB_TEST_REDIRECT_LANDING_PATH )
 *
 * This mu-plugin is mapped into `wp-content/mu-plugins` by `.wp-env.json`
 * (alongside `dwpb-test-api.php`), so it loads on every request in the test
 * environment. Every filter below is registered unconditionally but reads its
 * gating option at call time and returns the incoming value untouched when
 * that option is off, so leaving this file active for the whole suite is
 * safe.
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// NOTE: do not add a `function_exists()` early-return guard at the top of this
// file. PHP hoists unconditional top-level function declarations at compile
// time, so every `dwpb_test_redirects_*` function below is already declared
// before the first line of this file executes -- such a guard is therefore
// always true and returns before the `add_action`/`add_filter` calls at the
// bottom ever run, leaving the functions defined but every hook silently
// unregistered. WordPress includes each mu-plugin exactly once via
// `include_once`, so no guard is needed. See `dwpb-test-api.php` for the same
// note, where this exact bug already cost a debugging cycle.

/**
 * Path of the landing page targeted by the `dwpb_test_custom_redirect_url`
 * toggle, relative to `home_url()`.
 *
 * Literal value: '/dwpb-test-landing/'. Kept as a constant, rather than
 * inlined separately here and in the spec that asserts against it, so the two
 * cannot drift.
 */
define( 'DWPB_TEST_REDIRECT_LANDING_PATH', '/dwpb-test-landing/' );

/**
 * Register the five `dwpb_test_*` redirect-toggle options.
 *
 * Each is registered in the `options` group with `show_in_rest => true`, so
 * `PUT /wp/v2/settings` can set it in one request. Hooked on `init`, matching
 * core's own guidance for `register_setting()` calls that need to be visible
 * to both `admin_init` (classic Settings API) and REST requests.
 *
 * @return void
 */
function dwpb_test_redirects_register_settings() {

	register_setting(
		'options',
		'dwpb_test_front_end_redirects_off',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_redirect_front_end to false.',
		)
	);

	register_setting(
		'options',
		'dwpb_test_admin_redirects_off',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_redirect_admin to false.',
		)
	);

	register_setting(
		'options',
		'dwpb_test_redirect_status_code',
		array(
			'type'         => 'integer',
			'default'      => 0,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: override dwpb_redirect_status_code. 0 means off (use the plugin default).',
		)
	);

	register_setting(
		'options',
		'dwpb_test_query_string_passthrough',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_pass_query_string_on_redirect to true and allow-list utm_source via dwpb_allowed_query_vars.',
		)
	);

	register_setting(
		'options',
		'dwpb_test_custom_redirect_url',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_front_end_redirect_url to home_url( DWPB_TEST_REDIRECT_LANDING_PATH ).',
		)
	);
}
add_action( 'init', 'dwpb_test_redirects_register_settings' );

/**
 * Force `dwpb_redirect_front_end` off when the fixture is switched on.
 *
 * @param bool $bool Incoming filter value.
 * @return bool
 */
function dwpb_test_redirects_front_end_off( $bool ) {

	if ( get_option( 'dwpb_test_front_end_redirects_off' ) ) {
		return false;
	}

	return $bool;
}
add_filter( 'dwpb_redirect_front_end', 'dwpb_test_redirects_front_end_off' );

/**
 * Force `dwpb_redirect_admin` off when the fixture is switched on.
 *
 * @param bool $bool Incoming filter value.
 * @return bool
 */
function dwpb_test_redirects_admin_off( $bool ) {

	if ( get_option( 'dwpb_test_admin_redirects_off' ) ) {
		return false;
	}

	return $bool;
}
add_filter( 'dwpb_redirect_admin', 'dwpb_test_redirects_admin_off' );

/**
 * Override `dwpb_redirect_status_code` with the fixture's value, when set.
 *
 * `0` means "off" -- the option's own registered default -- so the incoming
 * status code (whatever the plugin or another filter set it to) passes
 * through untouched.
 *
 * @param int $status_code Incoming filter value.
 * @return int
 */
function dwpb_test_redirects_status_code( $status_code ) {

	$override = (int) get_option( 'dwpb_test_redirect_status_code', 0 );

	if ( 0 !== $override ) {
		return $override;
	}

	return $status_code;
}
add_filter( 'dwpb_redirect_status_code', 'dwpb_test_redirects_status_code' );

/**
 * Force `dwpb_pass_query_string_on_redirect` on when the fixture is switched
 * on.
 *
 * @param bool $bool Incoming filter value.
 * @return bool
 */
function dwpb_test_redirects_query_string_passthrough( $bool ) {

	if ( get_option( 'dwpb_test_query_string_passthrough' ) ) {
		return true;
	}

	return $bool;
}
add_filter( 'dwpb_pass_query_string_on_redirect', 'dwpb_test_redirects_query_string_passthrough' );

/**
 * Allow-list `utm_source` via `dwpb_allowed_query_vars` when the query-string
 * passthrough fixture is switched on.
 *
 * Paired with {@see dwpb_test_redirects_query_string_passthrough()} under the
 * same option -- passthrough with no allow-list would carry every query var,
 * which is not what this fixture is for.
 *
 * @param array $vars Incoming filter value.
 * @return array
 */
function dwpb_test_redirects_allowed_query_vars( $vars ) {

	if ( get_option( 'dwpb_test_query_string_passthrough' ) ) {
		return array( 'utm_source' );
	}

	return $vars;
}
add_filter( 'dwpb_allowed_query_vars', 'dwpb_test_redirects_allowed_query_vars' );

/**
 * Override `dwpb_front_end_redirect_url` with a fixed landing page when the
 * fixture is switched on.
 *
 * @param string $url Incoming filter value.
 * @return string
 */
function dwpb_test_redirects_custom_url( $url ) {

	if ( get_option( 'dwpb_test_custom_redirect_url' ) ) {
		return home_url( DWPB_TEST_REDIRECT_LANDING_PATH );
	}

	return $url;
}
add_filter( 'dwpb_front_end_redirect_url', 'dwpb_test_redirects_custom_url' );
