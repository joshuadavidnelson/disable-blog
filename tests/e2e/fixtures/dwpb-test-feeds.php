<?php
/**
 * E2E mu-plugin fixture: force the disabled-feed response to `wp_die()` with
 * a fixed message instead of redirecting, for the Playwright suite.
 *
 * TOGGLE: `dwpb_test_feed_die_message` (boolean, default false), a
 * `show_in_rest` option so a spec can flip it via
 * `PUT /wp-json/wp/v2/settings`.
 *
 * `Disable_Blog_Public::disable_feed()` defaults `dwpb_feed_message` to
 * `false`, so a disabled feed always redirects rather than `wp_die()`s --
 * this fixture forces the message branch so it's reachable in tests.
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// No function_exists() guard here: PHP hoists these top-level function
// declarations, so the guard would always be true and skip the
// add_action()/add_filter() calls below, silently unregistering every hook.

/**
 * The literal `wp_die()` message the `dwpb_test_feed_die_message` toggle
 * forces `dwpb_feed_die_message` to return. Kept as a constant so this file
 * and the asserting spec can't drift.
 */
define( 'DWPB_TEST_FEED_DIE_MESSAGE', 'DWPB test fixture: feed die message.' );

/**
 * Register the `dwpb_test_feed_die_message` toggle option.
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
