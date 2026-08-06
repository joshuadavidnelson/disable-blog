<?php
/**
 * E2E mu-plugin fixture: generic filter-override mechanism for the
 * Playwright suite.
 *
 * A single `show_in_rest` option, `dwpb_test_filter_overrides`, holds a JSON
 * *string* (not a nested object/array setting type -- WordPress 5.9, in the
 * CI matrix, doesn't reliably validate/save nested-object REST settings
 * schemas) mapping hook name to an override spec:
 *
 *   <bool|int|string>          bare scalar -> the filter returns it verbatim
 *   { "set": <any> }           -> the filter returns the value verbatim
 *   { "append": [ ... ] }      -> array_merge()'d onto the incoming array value
 *   { "remove": [ ... ] }      -> array_diff()'d from the incoming array value
 *
 * Each object form takes an optional "priority" (default 10).
 *
 * Registered at file scope, not deferred to an action: mu-plugins load
 * before regular plugins, so these add_filter() calls are already in place
 * before the plugin's own dynamically-built hook names (e.g.
 * `'dwpb_redirect_' . $filtername`) ever fire, even though those names never
 * appear as string literals for a bespoke fixture to hook.
 *
 * SECURITY GUARD: only hook names starting with `dwpb_` or the plugin's own
 * `dpwb_` typo-prefix are registered; anything else is silently skipped, so
 * this can't become a general-purpose arbitrary-hook injection surface.
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
 * Register the `dwpb_test_filter_overrides` option.
 *
 * @return void
 */
function dwpb_test_filters_register_settings() {

	register_setting(
		'options',
		'dwpb_test_filter_overrides',
		array(
			'type'         => 'string',
			'default'      => '',
			'show_in_rest' => true,
			'description'  => 'E2E fixture: JSON-encoded map of hook name to override spec; see dwpb-test-filters.php.',
		)
	);
}
add_action( 'init', 'dwpb_test_filters_register_settings' );

/**
 * Transform an incoming filter value per a decoded override spec.
 *
 * Shared with the `dwpb-test/v1/resolve-filter-override` probe route in
 * dwpb-test-api.php, which calls this directly so it exercises the exact
 * same code the live registration below runs -- not a copy. That route's
 * callback only runs at REST-dispatch time, long after every mu-plugin
 * (including this file) has loaded, so it's safe for it to call this despite
 * dwpb-test-api.php loading first alphabetically.
 *
 * @param string $mode     One of 'set', 'append', 'remove'.
 * @param mixed  $override The spec's override value.
 * @param mixed  $incoming The filter's incoming (first) argument.
 * @return mixed
 */
function dwpb_test_filters_transform( $mode, $override, $incoming ) {

	if ( 'append' === $mode ) {
		return array_merge( (array) $incoming, (array) $override );
	}

	if ( 'remove' === $mode ) {
		return array_diff( (array) $incoming, (array) $override );
	}

	return $override;
}

/**
 * Resolve a decoded override spec into a mode/value/priority triple.
 *
 * Shared by the live registration loop in
 * `dwpb_test_filters_register_overrides()` below and the
 * `dwpb-test/v1/resolve-filter-override` probe route in dwpb-test-api.php, so
 * both decode overrides identically. See {@see dwpb_test_filters_transform()}
 * for why the cross-file load order is safe.
 *
 * @param mixed $spec Decoded override spec: a bare scalar, or an object
 *                     (assoc array) with one of 'set'/'append'/'remove' and
 *                     an optional 'priority'.
 * @return array{mode: string, value: mixed, priority: int}|null Null when
 *                     $spec is an object with none of the recognized keys --
 *                     the caller should skip the entry rather than guess.
 */
function dwpb_test_filters_resolve_spec( $spec ) {

	$mode     = 'set';
	$value    = $spec;
	$priority = 10;

	if ( is_array( $spec ) ) {

		if ( array_key_exists( 'append', $spec ) ) {
			$mode  = 'append';
			$value = $spec['append'];
		} elseif ( array_key_exists( 'remove', $spec ) ) {
			$mode  = 'remove';
			$value = $spec['remove'];
		} elseif ( array_key_exists( 'set', $spec ) ) {
			$value = $spec['set'];
		} else {
			// Unrecognized object shape -- skip rather than guess.
			return null;
		}

		if ( isset( $spec['priority'] ) ) {
			$priority = (int) $spec['priority'];
		}
	}

	return array(
		'mode'     => $mode,
		'value'    => $value,
		'priority' => $priority,
	);
}

/**
 * Decode `dwpb_test_filter_overrides` and register one filter per entry.
 *
 * get_option() already works this early: wp_not_installed(), which itself
 * calls get_option(), runs before must-use plugins load.
 *
 * @return void
 */
function dwpb_test_filters_register_overrides() {

	$raw = get_option( 'dwpb_test_filter_overrides', '' );

	if ( ! is_string( $raw ) || '' === $raw ) {
		return;
	}

	$overrides = json_decode( $raw, true );

	// Malformed or non-object JSON decodes to a non-array; bail explicitly
	// rather than let the foreach() below tolerate it with a PHP warning.
	if ( ! is_array( $overrides ) ) {
		return;
	}

	foreach ( $overrides as $hook => $spec ) {

		// SECURITY GUARD -- see the file docblock.
		if ( 0 !== strpos( $hook, 'dwpb_' ) && 0 !== strpos( $hook, 'dpwb_' ) ) {
			continue;
		}

		$resolved = dwpb_test_filters_resolve_spec( $spec );

		if ( null === $resolved ) {
			continue;
		}

		add_filter(
			$hook,
			static function ( ...$args ) use ( $resolved ) {
				$incoming = isset( $args[0] ) ? $args[0] : null;

				return dwpb_test_filters_transform( $resolved['mode'], $resolved['value'], $incoming );
			},
			$resolved['priority'],
			// A generous accepted_args is safe: WP passes only however many
			// args the caller's apply_filters() actually supplied.
			10
		);
	}
}
dwpb_test_filters_register_overrides();
