<?php
/**
 * E2E mu-plugin fixture: force `dwpb_remove_options_writing` to `true`, for
 * the Playwright suite.
 *
 * TOGGLE: `dwpb_test_remove_options_writing` (boolean, default false), a
 * `show_in_rest` option so a spec can flip it via
 * `PUT /wp-json/wp/v2/settings`.
 *
 * `Disable_Blog_Admin::remove_writing_options()` defaults to false ("other
 * plugins often extend this page"), so the Writing-screen redirect and its
 * Settings submenu removal are unreachable without this fixture.
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// Inert without DWPB_TEST_FIXTURES: this mu-plugin also mounts on the :8888 dev site.
if ( ! defined( 'DWPB_TEST_FIXTURES' ) ) {
	return;
}

// No function_exists() guard here: PHP hoists these top-level function
// declarations, so the guard would always be true and skip the
// add_action()/add_filter() calls below, silently unregistering every hook.

/**
 * Register the `dwpb_test_remove_options_writing` toggle option.
 *
 * @return void
 */
function dwpb_test_options_writing_register_settings() {

	register_setting(
		'options',
		'dwpb_test_remove_options_writing',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_remove_options_writing to true.',
		)
	);
}
add_action( 'init', 'dwpb_test_options_writing_register_settings' );

/**
 * Force `dwpb_remove_options_writing` to `true` when the fixture is switched
 * on.
 *
 * @param bool $bool Incoming filter value.
 * @return bool
 */
function dwpb_test_options_writing_remove( $bool ) {

	if ( get_option( 'dwpb_test_remove_options_writing' ) ) {
		return true;
	}

	return $bool;
}
add_filter( 'dwpb_remove_options_writing', 'dwpb_test_options_writing_remove' );
