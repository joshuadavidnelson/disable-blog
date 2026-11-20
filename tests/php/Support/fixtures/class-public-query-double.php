<?php
/**
 * Stand-in for the subset of WordPress core's WP_Query class Disable_Blog_Public reads.
 *
 * Deliberately NOT named WP_Query: PHPStan resolves the real WP_Query class from the
 * szepeviktor/phpstan-wordpress stubs when analysing includes/, and a same-named class declared
 * here (a real, scanned class, not a stub) would shadow that -- breaking phpstan's analysis of
 * every OTHER real WP_Query usage in includes/ (e.g. class-disable-blog-admin.php's
 * `new WP_Query( $args )`), since this file's minimal stand-in has no constructor or ->posts.
 *
 * Disable_Blog_Public::modify_query() / set_post_types_in_query() call
 * method_exists( $query, 'set' ), which is false for a bare Mockery mock -- Mockery only
 * answers unstubbed method names through __call(), which method_exists() can't see. Declaring
 * real (empty) methods here gives Mockery::mock( 'Disable_Blog_Public_Query_Double' ) an actual
 * class to subclass, so the generated double both satisfies method_exists() and stays fully
 * controllable via shouldReceive().
 *
 * @package DisableBlog
 */

/**
 * Test double standing in for a WP_Query instance.
 */
class Disable_Blog_Public_Query_Double {

	/**
	 * @return bool
	 */
	public function is_main_query() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function is_tag() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function is_category() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function is_author() {
		return false;
	}

	/**
	 * @param string $key   The query var key.
	 * @param mixed  $value The query var value.
	 * @return void
	 */
	public function set( $key, $value ) {}

	/**
	 * @return void
	 */
	public function set_404() {}
}
