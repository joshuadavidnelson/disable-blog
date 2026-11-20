<?php
/**
 * Minimal stand-in for the subset of $wpdb Disable_Blog_Admin::get_comment_counts() reads.
 *
 * A plain declared class rather than Mockery::mock(): get_comment_counts() reads ->comments
 * and ->posts as real properties (interpolated directly into its SQL string) and calls
 * get_results() as a real method, and phpstan (level 5) needs both to be visible on a known
 * type instead of an untyped Mockery\MockInterface.
 *
 * @package DisableBlog
 */

/**
 * Test double standing in for $wpdb.
 */
class Disable_Blog_Admin_Wpdb_Double {

	/**
	 * @var string
	 */
	public $comments = 'wp_comments';

	/**
	 * @var string
	 */
	public $posts = 'wp_posts';

	/**
	 * The rows get_results() answers with.
	 *
	 * @var array
	 */
	private $rows;

	/**
	 * @param array $rows The rows get_results() should return.
	 */
	public function __construct( array $rows ) {
		$this->rows = $rows;
	}

	/**
	 * @param string      $query  The SQL query (ignored -- this double always answers $rows).
	 * @param string|null $output The output type (ignored).
	 * @return array
	 */
	public function get_results( $query, $output = null ) {
		return $this->rows;
	}
}
