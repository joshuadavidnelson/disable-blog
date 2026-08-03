<?php
/**
 * E2E mu-plugin fixture: register a public custom post type ('news') that
 * uses the built-in 'category' and 'post_tag' taxonomies, for the Playwright
 * suite.
 *
 * TOGGLE: `dwpb_test_cpt_enabled` (boolean, default false), registered via
 * `register_setting( 'options', ..., [ 'show_in_rest' => true ] )` so a spec
 * can flip it with one `PUT /wp-json/wp/v2/settings` call, the same pattern
 * used by every other fixture in this directory.
 *
 * WHY THIS EXISTS: most of Disable Blog's filters (`dwpb_post_types_with_tax()`,
 * `dwpb_post_types_with_feature()`, `Disable_Blog_Functions::author_archive_post_types()`)
 * gate their behaviour on "is any post type OTHER than 'post' using this
 * taxonomy/feature?". In a stock wp-env install the answer is always no for
 * 'category'/'post_tag' (only 'post' uses either), so every "unless another
 * post type uses it" branch in the plugin is unreachable without a second,
 * real post type in the mix. This single toggle supplies that post type,
 * unlocking category/tag archive un-redirection, the categories/tags REST
 * routes and admin menu links, the sitemap taxonomy entries, and the
 * XML-RPC taxonomy methods -- all exercised by `specs/filters/cpt-branches.spec.ts`.
 *
 * REGISTERED ON `init` PRIORITY 10, DELIBERATELY: `Disable_Blog_Admin::modify_post_type_arguments()`
 * and `::modify_taxonomies_arguments()` are both hooked on `init` at priority
 * 25 (see `Disable_Blog::define_admin_hooks()`). Registering 'news' at
 * priority 10 -- strictly before those run -- means `dwpb_post_types_with_tax()`
 * sees 'news' already registered against 'category'/'post_tag' by the time
 * the plugin's own priority-25 taxonomy stripping runs, exactly like a real
 * third-party plugin's `init` callback (WordPress core itself recommends
 * priority 0-9 for CPT registration, but 10 is early enough here since the
 * plugin's own hooks are all >= 25).
 *
 * REWRITE RULES: registering 'news' alone does not make `/news/...` URLs
 * resolve -- WordPress caches its rewrite rules in the `rewrite_rules` option
 * and only regenerates them when that option is empty (see
 * `WP_Rewrite::wp_rewrite_rules()`). A spec that switches this fixture on
 * MUST call `flushRewrites()` (`tests/e2e/config/seed.ts`) afterwards, and
 * again after switching it back off, or `/news/...` and the taxonomy archive
 * base URLs will resolve against whatever rules happened to be cached from
 * before/after the CPT existed.
 *
 * This mu-plugin is mapped into `wp-content/mu-plugins` by `.wp-env.json`
 * (alongside the other `dwpb-test-*.php` fixtures), so it loads on every
 * request in the test environment. Registration is gated at call time on the
 * toggle option, so leaving this file active for the whole suite is safe when
 * the toggle is off.
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// NOTE: do not add a `function_exists()` early-return guard at the top of this
// file. PHP hoists unconditional top-level function declarations at compile
// time, so every `dwpb_test_cpt_*` function below is already declared before
// the first line of this file executes -- such a guard is therefore always
// true and returns before the `add_action`/`add_filter` calls at the bottom
// ever run, leaving the functions defined but every hook silently
// unregistered (the toggle option would never even get registered, and the
// post type would never get registered either). WordPress includes each
// mu-plugin exactly once via `include_once`, so no guard is needed. See
// `dwpb-test-api.php` for the same note, where this exact bug already cost a
// debugging cycle.

/**
 * Register the `dwpb_test_cpt_enabled` toggle option.
 *
 * Hooked on `init` at the default priority (10), matching core's own
 * guidance for `register_setting()` calls that need to be visible to both
 * `admin_init` (classic Settings API) and REST requests.
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
 * Hooked on `init` at priority 10 -- see the file docblock for why this must
 * run strictly before `Disable_Blog_Admin::modify_post_type_arguments()` /
 * `::modify_taxonomies_arguments()` (both priority 25).
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
