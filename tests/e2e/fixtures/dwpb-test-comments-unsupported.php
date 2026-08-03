<?php
/**
 * E2E mu-plugin fixture: force `dwpb_post_types_supporting_comments` to
 * `false`, for the Playwright suite.
 *
 * TOGGLE: `dwpb_test_comments_unsupported` (boolean, default false),
 * registered via `register_setting( 'options', ..., [ 'show_in_rest' => true ] )`
 * so a spec can flip it with one `PUT /wp-json/wp/v2/settings` call, the same
 * pattern used by every other fixture in this directory.
 *
 * WHY THIS EXISTS: `dwpb_post_types_with_feature( 'comments' )`
 * (includes/functions.php) applies the `dwpb_post_types_supporting_{$feature}`
 * filter to its result before returning it — for the 'comments' feature that
 * filter is `dwpb_post_types_supporting_comments`.
 * `Disable_Blog_Admin::enqueue_scripts()` casts that value straight to
 * `dwpb.commentsSupported` (see `assets/js/disable-blog-admin.js`), which is
 * otherwise unreachable in this test environment: 'page' and 'attachment'
 * both support comments by default, so `dwpb_post_types_with_feature( 'comments' )`
 * (and therefore `commentsSupported`) is always truthy out of the box, and
 * the `! dwpb.commentsSupported` branch in the Dashboard case of
 * `disable-blog-admin.js` never runs. This fixture is the only way to force
 * that branch and reproduce the defect it contains (D6, a TypeError thrown
 * from a `.welcome-icon.welcome-comments` selector that no longer matches
 * anything on modern WordPress).
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
// time, so every `dwpb_test_comments_unsupported_*` function below is already
// declared before the first line of this file executes -- such a guard is
// therefore always true and returns before the `add_action`/`add_filter`
// calls at the bottom ever run, leaving the functions defined but every hook
// silently unregistered (the toggle option would never even get registered).
// WordPress includes each mu-plugin exactly once via `include_once`, so no
// guard is needed. See `dwpb-test-api.php` for the same note, where this
// exact bug already cost a debugging cycle.

/**
 * Register the `dwpb_test_comments_unsupported` toggle option.
 *
 * Hooked on `init`, matching core's own guidance for `register_setting()`
 * calls that need to be visible to both `admin_init` (classic Settings API)
 * and REST requests.
 *
 * @return void
 */
function dwpb_test_comments_unsupported_register_settings() {

	register_setting(
		'options',
		'dwpb_test_comments_unsupported',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_post_types_supporting_comments to false.',
		)
	);
}
add_action( 'init', 'dwpb_test_comments_unsupported_register_settings' );

/**
 * Force `dwpb_post_types_supporting_comments` to `false` when the fixture is
 * switched on.
 *
 * @param array|bool $post_types Incoming filter value.
 * @return array|bool
 */
function dwpb_test_comments_unsupported_filter( $post_types ) {

	if ( get_option( 'dwpb_test_comments_unsupported' ) ) {
		return false;
	}

	return $post_types;
}
add_filter( 'dwpb_post_types_supporting_comments', 'dwpb_test_comments_unsupported_filter' );
