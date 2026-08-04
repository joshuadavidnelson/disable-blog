<?php
/**
 * E2E mu-plugin fixture: short-circuit the plugin's XML-RPC method removal
 * entirely, for the Playwright suite.
 *
 * TOGGLE: `dwpb_test_xmlrpc_restore` (boolean, default false), a
 * `show_in_rest` option so a spec can flip it via
 * `PUT /wp-json/wp/v2/settings`.
 *
 * `Disable_Blog_Public::get_disabled_xmlrpc_methods()` returns `false` (not
 * an array) when `dwpb_disabled_xmlrpc_methods` returns anything non-array,
 * which skips the removal loop in `xmlrpc_methods()` entirely -- the plugin's
 * own documented escape hatch. This fixture flips that filter on for the
 * suite, restoring every XML-RPC method.
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
 * Register the `dwpb_test_xmlrpc_restore` toggle option.
 *
 * @return void
 */
function dwpb_test_xmlrpc_register_settings() {

	register_setting(
		'options',
		'dwpb_test_xmlrpc_restore',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
			'description'  => 'E2E fixture: force dwpb_disabled_xmlrpc_methods to false, restoring every XML-RPC method.',
		)
	);
}
add_action( 'init', 'dwpb_test_xmlrpc_register_settings' );

/**
 * Force `dwpb_disabled_xmlrpc_methods` to `false` when the fixture is
 * switched on. A non-array return value short-circuits
 * `get_disabled_xmlrpc_methods()` entirely -- see the file docblock.
 *
 * @param array $methods_to_remove Incoming filter value.
 * @return array|bool
 */
function dwpb_test_xmlrpc_restore_filter( $methods_to_remove ) {

	if ( get_option( 'dwpb_test_xmlrpc_restore' ) ) {
		return false;
	}

	return $methods_to_remove;
}
add_filter( 'dwpb_disabled_xmlrpc_methods', 'dwpb_test_xmlrpc_restore_filter' );
