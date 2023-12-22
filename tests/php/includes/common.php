<?php

/**
 * Simple common WP classes/functions
 */
/**
 * Mock wp_cache_get() function.
 *
 * @since x.x.x
 *
 * @param string $key
 * @param string $group
 * @return mixed
 */
function wp_cache_get( $key, $group ) {
	return false;
}

/**
 * Mock wp_cache_set() function.
 *
 * @since x.x.x
 *
 * @param string $key
 * @param mixed  $value
 * @param string $group
 * @return bool
 */
function wp_cache_set( $key, $value, $group ) {
	return true;
}
