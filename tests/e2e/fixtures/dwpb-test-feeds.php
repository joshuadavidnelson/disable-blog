<?php
/**
 * E2E mu-plugin fixture: force the disabled-feed response to `wp_die()` with
 * a fixed message instead of redirecting, for the Playwright suite.
 *
 * TOGGLE: `dwpb_test_feed_die_message` (boolean, default false), registered
 * via `register_setting( 'options', ..., [ 'show_in_rest' => true ] )` so a
 * spec can flip it with one `PUT /wp-json/wp/v2/settings` call, the same
 * pattern used by every other fixture in this directory.
 *
 * WHY THIS EXISTS: `Disable_Blog_Public::disable_feed()`
 * (includes/class-disable-blog-public.php) defaults `dwpb_feed_message` to
 * `false`, so a disabled feed always redirects
 * (`Disable_Blog_Functions::redirect()`) rather than `wp_die()`s -- the
 * message branch, and the `dwpb_feed_die_message` filter that customizes its
 * text, are both unreachable in their non-default state without this
 * fixture.
 *
 * THE LITERAL: `DWPB_TEST_FEED_DIE_MESSAGE` below. Kept as a constant, rather
 * than inlined separately here and in the spec that asserts against it, so
 * the two files' literals are easy to compare and cannot silently drift --
 * same convention as `DWPB_TEST_REDIRECT_LANDING_PATH` in
 * `dwpb-test-redirects.php`.
 *
 * This mu-plugin is mapped into `wp-content/mu-plugins` by `.wp-env.json`
 * (alongside the other `dwpb-test-*.php` fixtures), so it loads on every
 * request in the test environment. Both filters below are registered
 * unconditionally but read their gating option at call time and return the
 * incoming value untouched when that option is off, so leaving this file
 * active for the whole suite is safe.
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// NOTE: do not add a `function_exists()` early-return guard at the top of this
// file. PHP hoists unconditional top-level function declarations at compile
// time, so every `dwpb_test_feeds_*` function below is already declared
// before the first line of this file executes -- such a guard is therefore
// always true and returns before the `add_action`/`add_filter` calls at the
// bottom ever run, leaving the functions defined but every hook silently
// unregistered (the toggle option would never even get registered).
// WordPress includes each mu-plugin exactly once via `include_once`, so no
// guard is needed. See `dwpb-test-api.php` for the same note, where this
// exact bug already cost a debugging cycle.

/**
 * The literal `wp_die()` message the `dwpb_test_feed_die_message` toggle
 * forces `dwpb_feed_die_message` to return.
 *
 * Literal value: 'DWPB test fixture: feed die message.'
 */
define( 'DWPB_TEST_FEED_DIE_MESSAGE', 'DWPB test fixture: feed die message.' );

/**
 * Register the `dwpb_test_feed_die_message` toggle option.
 *
 * Hooked on `init`, matching core's own guidance for `register_setting()`
 * calls that need to be visible to both `admin_init` (classic Settings API)
 * and REST requests.
 *
 * @return void
 */
function dwpb_test_feeds_register_settings() {

	register_setting(
		'options',
		'dwpb_test_feed_die_message',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_feed_message to true and dwpb_feed_die_message to a fixed literal.',
		)
	);
}
add_action( 'init', 'dwpb_test_feeds_register_settings' );

/**
 * Force `dwpb_feed_message` to `true` when the fixture is switched on.
 *
 * @param bool   $bool            Incoming filter value.
 * @param object $post            Global post object.
 * @param bool   $is_comment_feed True if the feed is a comment feed.
 * @return bool
 */
function dwpb_test_feeds_message( $bool, $post, $is_comment_feed ) {

	if ( get_option( 'dwpb_test_feed_die_message' ) ) {
		return true;
	}

	return $bool;
}
add_filter( 'dwpb_feed_message', 'dwpb_test_feeds_message', 10, 3 );

/**
 * Force `dwpb_feed_die_message` to the fixed `DWPB_TEST_FEED_DIE_MESSAGE`
 * literal when the fixture is switched on.
 *
 * @param string $message Incoming filter value.
 * @return string
 */
function dwpb_test_feeds_die_message( $message ) {

	if ( get_option( 'dwpb_test_feed_die_message' ) ) {
		return DWPB_TEST_FEED_DIE_MESSAGE;
	}

	return $message;
}
add_filter( 'dwpb_feed_die_message', 'dwpb_test_feeds_die_message' );
