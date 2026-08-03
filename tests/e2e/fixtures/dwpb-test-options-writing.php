<?php
/**
 * E2E mu-plugin fixture: force `dwpb_remove_options_writing` to `true`, for
 * the Playwright suite.
 *
 * TOGGLE: `dwpb_test_remove_options_writing` (boolean, default false),
 * registered via `register_setting( 'options', ..., [ 'show_in_rest' => true ] )`
 * so a spec can flip it with one `PUT /wp-json/wp/v2/settings` call, the same
 * pattern used by every other fixture in this directory.
 *
 * WHY THIS EXISTS: `Disable_Blog_Admin::remove_writing_options()`
 * (`dwpb_remove_options_writing`) defaults to false -- "other plugins often
 * extend this page" -- so `options-writing.php`'s redirect
 * (`Disable_Blog_Admin::redirect_admin_options_writing()`) and its Settings
 * submenu removal (`Disable_Blog_Admin::remove_menu_pages()`) are both
 * unreachable in their non-default state without this fixture.
 *
 * This mu-plugin is mapped into `wp-content/mu-plugins` by `.wp-env.json`
 * (alongside the other `dwpb-test-*.php` fixtures), so it loads on every
 * request in the test environment. The filter below is registered
 * unconditionally but reads its gating option at call time and returns the
 * incoming value untouched when that option is off, so leaving this file
 * active for the whole suite is safe.
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// NOTE: do not add a `function_exists()` early-return guard at the top of this
// file. PHP hoists unconditional top-level function declarations at compile
// time, so every `dwpb_test_options_writing_*` function below is already
// declared before the first line of this file executes -- such a guard is
// therefore always true and returns before the `add_action`/`add_filter`
// calls at the bottom ever run, leaving the functions defined but every hook
// silently unregistered (the toggle option would never even get
// registered). WordPress includes each mu-plugin exactly once via
// `include_once`, so no guard is needed. See `dwpb-test-api.php` for the same
// note, where this exact bug already cost a debugging cycle.

/**
 * Register the `dwpb_test_remove_options_writing` toggle option.
 *
 * Hooked on `init`, matching core's own guidance for `register_setting()`
 * calls that need to be visible to both `admin_init` (classic Settings API)
 * and REST requests.
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
