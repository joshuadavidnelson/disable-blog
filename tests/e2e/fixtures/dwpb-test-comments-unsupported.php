<?php
/**
 * E2E mu-plugin fixture: force `dwpb_post_types_supporting_comments` to
 * `false`, for the Playwright suite.
 *
 * TOGGLE: `dwpb_test_comments_unsupported` (boolean, default false), a
 * `show_in_rest` option so a spec can flip it via
 * `PUT /wp-json/wp/v2/settings`.
 *
 * 'page' and 'attachment' both support comments by default, so
 * `dwpb_post_types_with_feature( 'comments' )` (and the admin JS's
 * `commentsSupported`) is otherwise always truthy in this test environment.
 * This fixture is the only way to force the falsey branch and exercise the
 * dashboard code path that threw a TypeError under it (defect D6, fixed).
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
 * Register the `dwpb_test_comments_unsupported` toggle option.
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
