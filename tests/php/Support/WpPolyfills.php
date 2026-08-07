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
	// $message never reaches any output sink here -- it only carries the string a test
	// asserts against via expectExceptionMessage(), inside the same PHP process.
	throw new Exception( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
}

// Intentionally NOT stubbed here: wp_cache_get() / wp_cache_set().
// PHP cannot un-declare a function, so a global stub here would fix its return
// value for the entire suite and make one of the cache-hit/cache-miss branches
// in includes/functions.php permanently untestable. Stub both per-test via
// WP_Mock::userFunction() instead, so each test controls its own branch.

/**
 * wp_parse_str() mutates its second argument by reference, and WP_Mock::userFunction()
 * stubs are declared without by-reference parameters (they collect args via
 * func_get_args(), which copies), so a stub can never write back into the caller's
 * variable. Declaring the real behavior here -- identical to WordPress core's own
 * wp_parse_str() -- is the only way a caller's $query_vars actually gets populated.
 *
 * @param string $string The string to parse.
 * @param array  $array  Variables will be stored in this array.
 * @return void
 */
function wp_parse_str( $string, &$array ) {
	parse_str( (string) $string, $array );
}

/**
 * sanitize_key() is passed to array_map() by name in
 * Disable_Blog_Functions::get_allowed_query_vars(). PHP 8 resolves a string callback
 * eagerly, so array_map() raises a TypeError when the function is undeclared -- even
 * when the array is empty and the callback would never be invoked. A per-test
 * WP_Mock::userFunction() stub only declares it once that test has run, which would
 * make every test reaching get_allowed_query_vars() depend on execution order.
 * Patchwork still intercepts this declaration, so tests can override it as usual.
 *
 * @param string $key The key to sanitize.
 * @return string
 */
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
