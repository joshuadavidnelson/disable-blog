<?php
/**
 * Global WordPress function stubs that WP_Mock::userFunction() cannot express.
 *
 * Keep this file minimal: only declare a global function here when it needs real
 * behavior (e.g. control flow via an exception) rather than a per-test return value.
 * Everything else should be stubbed per-test with WP_Mock::userFunction().
 *
 * @package DisableBlog
 */

/**
 * wp_die() normally terminates the request with exit(). Tests can't survive exit(),
 * so this throws instead, letting exit-terminated code paths be asserted with
 * expectException()/expectExceptionMessage() in later phases.
 *
 * @param string $message Error message.
 * @param string $title   Error title.
 * @param array  $args    Arguments.
 *
 * @throws Exception Always, carrying $message.
 */
function wp_die( $message = '', $title = '', $args = array() ) {
	throw new Exception( $message );
}

// Intentionally NOT stubbed here: wp_cache_get() / wp_cache_set().
// PHP cannot un-declare a function, so a global stub here would fix its return
// value for the entire suite and make one of the cache-hit/cache-miss branches
// in includes/functions.php permanently untestable. Stub both per-test via
// WP_Mock::userFunction() instead, so each test controls its own branch.
