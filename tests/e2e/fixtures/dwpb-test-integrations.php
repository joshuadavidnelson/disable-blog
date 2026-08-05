<?php
/**
 * E2E mu-plugin fixture: stub the Disable Comments / WooCommerce detection
 * `Disable_Blog_Integrations` relies on, for the Playwright suite.
 *
 * Two `show_in_rest` boolean options, each default `false`:
 *
 *   dwpb_test_integrations_disable_comments  defines a `Disable_Comments`
 *                                             class -- the `class_exists()`
 *                                             fallback
 *                                             `is_disable_comments_active()`
 *                                             checks when the real plugin
 *                                             isn't installed.
 *   dwpb_test_integrations_woocommerce       defines a `WC()` function and a
 *                                             `WC_VERSION` constant below
 *                                             2.6.3 -- the `function_exists()`
 *                                             fallback `is_woocommerce_active()`
 *                                             checks, and the version
 *                                             `woocommerce_version_check()`
 *                                             compares against.
 *
 * Read directly at file scope, not deferred to an action: mu-plugins load
 * before regular plugins, so `class_exists( 'Disable_Comments' )` /
 * `function_exists( 'WC' )` must already be true before
 * `Disable_Blog::plugin_integrations()` runs on `plugins_loaded`.
 * `get_option()` already works this early -- see dwpb-test-filters.php.
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// Inert without DWPB_TEST_FIXTURES: this mu-plugin also mounts on the :8888 dev site.
if ( ! defined( 'DWPB_TEST_FIXTURES' ) ) {
	return;
}

// No function_exists() guard on the top-level functions below: PHP hoists
// these top-level function declarations, so the guard would always be true
// and skip the add_action() calls, silently unregistering the settings/route.

/**
 * Register the two `dwpb_test_integrations_*` toggle options.
 *
 * @return void
 */
function dwpb_test_integrations_register_settings() {

	register_setting(
		'options',
		'dwpb_test_integrations_disable_comments',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: stub a Disable_Comments class so is_disable_comments_active() reports true.',
		)
	);

	register_setting(
		'options',
		'dwpb_test_integrations_woocommerce',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: stub a WC() function and WC_VERSION constant so is_woocommerce_active() reports true.',
		)
	);
}
add_action( 'init', 'dwpb_test_integrations_register_settings' );

/**
 * Report `wp_count_comments( 0 )`'s PHP type.
 *
 * The only end-to-end signal of
 * `Disable_Blog_Integrations::filter_woocommerce_comment_count()`: on a
 * pre-2.6.3 WooCommerce it casts the site-wide comment count back to an
 * array, a type WordPress core's own `wp_count_comments()` never returns
 * (always an object) and nothing in this stubbed environment consumes --
 * without this route the cast has no visible effect at all.
 *
 * @return array<string, string>
 */
function dwpb_test_integrations_wp_count_comments_type() {
	return array( 'type' => gettype( wp_count_comments( 0 ) ) );
}

/**
 * Register the `wp-count-comments-type` probe route.
 *
 * @return void
 */
function dwpb_test_integrations_register_routes() {

	register_rest_route(
		'dwpb-test/v1',
		'/wp-count-comments-type',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'callback'            => static function () {
				return rest_ensure_response( dwpb_test_integrations_wp_count_comments_type() );
			},
		)
	);
}
add_action( 'rest_api_init', 'dwpb_test_integrations_register_routes' );

// Stub Disable Comments: is_disable_comments_active() falls back to
// class_exists( 'Disable_Comments' ) when the real plugin isn't installed.
if ( get_option( 'dwpb_test_integrations_disable_comments' ) && ! class_exists( 'Disable_Comments' ) ) {
	class Disable_Comments {} // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- must match the real plugin's class name exactly, that's what class_exists() checks for.
}

// Stub WooCommerce: is_woocommerce_active() falls back to
// function_exists( 'WC' ); woocommerce_version_check() reads WC_VERSION,
// pinned below 2.6.3 so filter_woocommerce_comment_count() takes its
// array-cast branch.
if ( get_option( 'dwpb_test_integrations_woocommerce' ) ) {

	if ( ! function_exists( 'WC' ) ) {
		function WC() {
			return null;
		}
	}

	if ( ! defined( 'WC_VERSION' ) ) {
		define( 'WC_VERSION', '2.6.0' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- must match the real constant name exactly, that's what woocommerce_version_check() reads.
	}
}
