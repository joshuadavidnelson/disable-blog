<?php
/**
 * E2E mu-plugin fixture: short-circuit the plugin's XML-RPC method removal
 * entirely, for the Playwright suite.
 *
 * TOGGLE: `dwpb_test_xmlrpc_restore` (boolean, default false), registered via
 * `register_setting( 'options', ..., [ 'show_in_rest' => true ] )` so a spec
 * can flip it with one `PUT /wp-json/wp/v2/settings` call, the same pattern
 * used by every other fixture in this directory.
 *
 * WHY THIS EXISTS: `Disable_Blog_Public::get_disabled_xmlrpc_methods()`
 * (includes/class-disable-blog-public.php) applies `dwpb_disabled_xmlrpc_methods`
 * to its removal list and then does:
 *
 *     return is_array( $methods_to_remove ) ? array_filter( $methods_to_remove, 'is_string' ) : false;
 *
 * -- so returning anything other than an array from that filter makes the
 * function return `false` instead of a list of method names.
 * `Disable_Blog_Public::xmlrpc_methods()`, the filter this feeds, then does
 * `if ( ! empty( $methods_to_remove ) && is_array( $methods_to_remove ) )`
 * before it ever loops and `unset()`s anything -- `is_array( false )` is
 * false, so the whole removal loop is skipped and every XML-RPC method
 * (`wp.getPosts`, `pingback.ping`, the taxonomy methods, ...) stays callable.
 * This is the plugin's own documented escape hatch ("Return false to disable
 * this functionality entirely and keep all methods in place.", see that
 * filter's docblock) -- this fixture simply flips it on for the suite.
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
// time, so every `dwpb_test_xmlrpc_*` function below is already declared
// before the first line of this file executes -- such a guard is therefore
// always true and returns before the `add_action`/`add_filter` calls at the
// bottom ever run, leaving the functions defined but every hook silently
// unregistered (the toggle option would never even get registered).
// WordPress includes each mu-plugin exactly once via `include_once`, so no
// guard is needed. See `dwpb-test-api.php` for the same note, where this
// exact bug already cost a debugging cycle.

/**
 * Register the `dwpb_test_xmlrpc_restore` toggle option.
 *
 * Hooked on `init`, matching core's own guidance for `register_setting()`
 * calls that need to be visible to both `admin_init` (classic Settings API)
 * and REST requests.
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
 * switched on.
 *
 * A non-array return value short-circuits `get_disabled_xmlrpc_methods()`
 * entirely -- see the file docblock.
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
