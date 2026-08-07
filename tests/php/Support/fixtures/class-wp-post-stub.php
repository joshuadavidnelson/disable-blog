<?php
/**
 * Minimal stand-in for WordPress core's WP_Post class.
 *
 * Disable_Blog_Public::redirect_public_pages() checks `$post instanceof WP_Post`. This
 * WP_Mock-based unit suite never loads WordPress core, so a plain container with settable
 * public properties is enough to satisfy that check and carry whatever property value a
 * given test needs.
 *
 * @package DisableBlog
 */

/**
 * Stand-in for WP_Post.
 */
class WP_Post {

	/**
	 * @var int
	 */
	public $ID = 1;

	/**
	 * @var string
	 */
	public $post_type = 'post';

	/**
	 * @param array $data Property values to set on the instance, keyed by property name.
	 */
	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			$this->$key = $value;
		}
	}
}
