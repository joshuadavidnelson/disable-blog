<?php
/**
 * E2E mu-plugin fixture: register a public custom post type ('news') that
 * uses the built-in 'category' and 'post_tag' taxonomies, for the Playwright
 * suite.
 *
 * TOGGLE: `dwpb_test_cpt_enabled` (boolean, default false), a `show_in_rest`
 * option so a spec can flip it via `PUT /wp-json/wp/v2/settings`.
 *
 * Most of the plugin's filters gate on "is any post type OTHER than 'post'
 * using this taxonomy/feature?", which is unreachable in a stock install.
 * This toggle supplies that second post type, unlocking category/tag archive
 * un-redirection, the categories/tags REST routes and admin menu links, the
 * sitemap taxonomy entries, and the XML-RPC taxonomy methods.
 *
 * Registered on `init` priority 10, strictly before
 * `Disable_Blog_Admin::modify_post_type_arguments()` /
 * `::modify_taxonomies_arguments()` (both priority 25), so the plugin's own
 * taxonomy stripping sees 'news' already registered.
 *
 * A spec that switches this on must call `flushRewrites()` afterwards (and
 * again after switching off) — WordPress only regenerates rewrite rules when
 * the cached `rewrite_rules` option is empty.
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
 * Register the `dwpb_test_cpt_enabled` toggle option.
 *
 * @return void
 */
function dwpb_test_cpt_register_settings() {

	register_setting(
		'options',
		'dwpb_test_cpt_enabled',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: register a public "news" post type using category + post_tag.',
		)
	);
}
add_action( 'init', 'dwpb_test_cpt_register_settings' );

/**
 * Register the 'news' post type when the fixture is switched on.
 *
 * Priority 10 -- see the file docblock for why this must run before the
 * plugin's own priority-25 taxonomy/post-type hooks.
 *
 * @return void
 */
function dwpb_test_cpt_register_post_type() {

	if ( ! get_option( 'dwpb_test_cpt_enabled' ) ) {
		return;
	}

	register_post_type(
		'news',
		array(
			'labels'       => array(
				'name'          => 'News',
				'singular_name' => 'News Item',
			),
			'public'       => true,
			'show_in_rest' => true,
			'has_archive'  => true,
			'taxonomies'   => array( 'category', 'post_tag' ),
			'supports'     => array( 'title', 'editor', 'author', 'comments' ),
			'rewrite'      => array( 'slug' => 'news' ),
		)
	);
}
add_action( 'init', 'dwpb_test_cpt_register_post_type', 10 );
