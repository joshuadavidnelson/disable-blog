<?php
/**
 * Force every e2e fixture toggle off for the surface dump's request.
 *
 * Loaded through `wp --require=<this file>`, which runs before WordPress and
 * its must-use plugins load. `--skip-plugins` does not skip must-use plugins,
 * and the fixtures read their toggles while loading or on `init`, so the
 * overrides have to exist before WordPress starts. WordPress turns a
 * pre-populated `$wp_filter` into hooks when `plugin.php` loads.
 *
 * The toggle names come from the `register_setting()` calls in the fixtures,
 * so a new toggle is covered without editing this file.
 *
 * @package Disable_Blog
 */

/**
 * The fixture toggle options registered in `tests/e2e/fixtures/`.
 *
 * @return string[]
 */
function dwpb_compat_fixture_options() {
	$options = array();
	foreach ( (array) glob( dirname( __DIR__ ) . '/e2e/fixtures/*.php' ) as $file ) {
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- a local fixture file.
		$source = (string) file_get_contents( (string) $file );
		if ( preg_match_all( "/register_setting\(\s*'[^']*',\s*'(dwpb_test_[a-z0-9_]+)'/", $source, $matches ) ) {
			$options = array_merge( $options, $matches[1] );
		}
	}
	return array_values( array_unique( $options ) );
}

/**
 * The value every fixture toggle reads during the dump: empty, which each
 * fixture treats as off.
 *
 * @return string
 */
function dwpb_compat_fixture_off() {
	return '';
}

define( 'DWPB_COMPAT_FIXTURE_OPTIONS', dwpb_compat_fixture_options() );

foreach ( DWPB_COMPAT_FIXTURE_OPTIONS as $dwpb_compat_option ) {
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress is not loaded yet; this is how hooks are registered before it.
	$GLOBALS['wp_filter'][ 'pre_option_' . $dwpb_compat_option ][10]['dwpb_compat_fixture_off'] = array(
		'function'      => 'dwpb_compat_fixture_off',
		'accepted_args' => 1,
	);
}
unset( $dwpb_compat_option );
