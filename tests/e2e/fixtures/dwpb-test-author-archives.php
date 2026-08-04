<?php
/**
 * E2E mu-plugin fixture: author-archive toggles for the Playwright suite.
 *
 * Two `show_in_rest` options so a spec can flip either with a single
 * `PUT /wp-json/wp/v2/settings` call:
 *
 *   dwpb_test_author_archives_disabled  boolean  -> dwpb_disable_author_archives     returns true
 *   dwpb_test_author_archive_cpt        boolean  -> dwpb_author_archive_post_types   returns array( 'news' )
 *
 * `dwpb_test_author_archive_cpt` points at the 'news' post type registered by
 * `dwpb-test-cpt.php` -- pair the two when exercising the "author archives
 * backed by a CPT" branches.
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
 * Register the two `dwpb_test_author_archive*` toggle options.
 *
 * @return void
 */
function dwpb_test_author_archives_register_settings() {

	register_setting(
		'options',
		'dwpb_test_author_archives_disabled',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_disable_author_archives to true.',
		)
	);

	register_setting(
		'options',
		'dwpb_test_author_archive_cpt',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_author_archive_post_types to array( "news" ).',
		)
	);
}
add_action( 'init', 'dwpb_test_author_archives_register_settings' );

/**
 * Force `dwpb_disable_author_archives` to `true` when the fixture is
 * switched on.
 *
 * @param bool $bool Incoming filter value.
 * @return bool
 */
function dwpb_test_author_archives_disable( $bool ) {

	if ( get_option( 'dwpb_test_author_archives_disabled' ) ) {
		return true;
	}

	return $bool;
}
add_filter( 'dwpb_disable_author_archives', 'dwpb_test_author_archives_disable' );

/**
 * Force `dwpb_author_archive_post_types` to `array( 'news' )` when the
 * fixture is switched on.
 *
 * @param array|bool $post_types Incoming filter value.
 * @return array|bool
 */
function dwpb_test_author_archives_cpt( $post_types ) {

	if ( get_option( 'dwpb_test_author_archive_cpt' ) ) {
		return array( 'news' );
	}

	return $post_types;
}
add_filter( 'dwpb_author_archive_post_types', 'dwpb_test_author_archives_cpt' );
