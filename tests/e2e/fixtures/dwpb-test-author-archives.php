<?php
/**
 * E2E mu-plugin fixture: author-archive toggles for the Playwright suite.
 *
 * TOGGLES: two options, each registered via
 * `register_setting( 'options', ..., [ 'show_in_rest' => true ] )` so a spec
 * can flip one with a single `PUT /wp-json/wp/v2/settings` call, the same
 * pattern used by every other fixture in this directory.
 *
 *   dwpb_test_author_archives_disabled  boolean  -> dwpb_disable_author_archives     returns true
 *   dwpb_test_author_archive_cpt        boolean  -> dwpb_author_archive_post_types   returns array( 'news' )
 *
 * WHY THIS EXISTS: `Disable_Blog_Functions::disable_author_archives()`
 * (`dwpb_disable_author_archives`) defaults to false and
 * `::author_archive_post_types()` (`dwpb_author_archive_post_types`) defaults
 * to an empty array, so neither of `Disable_Blog_Public::redirect_public_pages()`'s
 * `author_archive` branch, `Disable_Blog_Admin::user_row_actions()`'s "view"
 * removal, `::available_permalink_structure_tags()`'s `%author%` removal, nor
 * `::wp_author_sitemaps()`'s users-sitemap gate are reachable in their
 * non-default state without this fixture.
 *
 * `dwpb_test_author_archive_cpt` deliberately points at the 'news' post type
 * registered by `dwpb-test-cpt.php` -- pair it with that fixture's
 * `dwpb_test_cpt_enabled` toggle when exercising the "author archives backed
 * by a CPT" branches (`Disable_Blog_Functions::author_archive_post_types()`
 * feeding `Disable_Blog_Public::modify_query()` and `::wp_author_sitemaps()`).
 *
 * This mu-plugin is mapped into `wp-content/mu-plugins` by `.wp-env.json`
 * (alongside the other `dwpb-test-*.php` fixtures), so it loads on every
 * request in the test environment. Every filter below is registered
 * unconditionally but reads its gating option at call time and returns the
 * incoming value untouched when that option is off, so leaving this file
 * active for the whole suite is safe.
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// NOTE: do not add a `function_exists()` early-return guard at the top of this
// file. PHP hoists unconditional top-level function declarations at compile
// time, so every `dwpb_test_author_archives_*` function below is already
// declared before the first line of this file executes -- such a guard is
// therefore always true and returns before the `add_action`/`add_filter`
// calls at the bottom ever run, leaving the functions defined but every hook
// silently unregistered (the toggle options would never even get
// registered). WordPress includes each mu-plugin exactly once via
// `include_once`, so no guard is needed. See `dwpb-test-api.php` for the same
// note, where this exact bug already cost a debugging cycle.

/**
 * Register the two `dwpb_test_author_archive*` toggle options.
 *
 * Hooked on `init`, matching core's own guidance for `register_setting()`
 * calls that need to be visible to both `admin_init` (classic Settings API)
 * and REST requests.
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
