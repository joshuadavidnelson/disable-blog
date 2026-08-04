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
 * Registered at file scope rather than deferred to an action: mu-plugins
 * load before regular plugins, so add_filter() calls made here are in place
 * long before the plugin's own files run, including its dynamically-built
 * hook names -- e.g. `'dwpb_redirect_' . $filtername` in
 * Disable_Blog_Public::redirect_public_pages(), `'dwpb_' . $function` in
 * Disable_Blog_Admin::redirect_admin_pages(), `"dwpb_disable_{$metabox_id}"`,
 * `"dpwb_create_user_{$post_type}_column"` -- which never appear as string
 * literals anywhere for a bespoke fixture to hook.
 *
 * SECURITY GUARD: only hook names starting with `dwpb_` or the plugin's own
 * `dpwb_` typo-prefix (see `dpwb_disable_user_post_column` and
 * `"dpwb_create_user_{$post_type}_column"` in class-disable-blog-admin.php)
 * are registered; anything else is silently skipped, so this fixture can't
 * become a general-purpose arbitrary-hook injection surface.
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

	if ( ! is_array( $overrides ) ) {
		return;
	}

	foreach ( $overrides as $hook => $spec ) {

		// SECURITY GUARD -- see the file docblock.
		if ( 0 !== strpos( $hook, 'dwpb_' ) && 0 !== strpos( $hook, 'dpwb_' ) ) {
			continue;
		}

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
				continue;
			}

			if ( isset( $spec['priority'] ) ) {
				$priority = (int) $spec['priority'];
			}
		}

		add_filter(
			$hook,
			static function ( ...$args ) use ( $mode, $value ) {
				$incoming = isset( $args[0] ) ? $args[0] : null;

				return dwpb_test_filters_transform( $mode, $value, $incoming );
			},
			$priority,
			// Accepts up to 10 args: covers every plugin filter (max is 5), and
			// only the first is ever transformed -- WP passes exactly however
			// many the caller's own apply_filters() supplied, never more, so a
			// generous accepted_args here can't corrupt a multi-arg filter.
			10
		);
	}
}
dwpb_test_filters_register_overrides();
