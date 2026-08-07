<?php
/**
 * Configurable Disable_Blog_Functions double for Disable_Blog_Public tests.
 *
 * Overrides every Disable_Blog_Functions method Disable_Blog_Public calls through
 * $this->functions, so a test can control each answer independently and, for redirect(),
 * assert exactly what url the caller decided on -- without exercising Disable_Blog_Functions's
 * own real WordPress-calling logic, which DisableBlogFunctionsTest already covers.
 *
 * @package DisableBlog
 */

/**
 * Test double standing in for Disable_Blog_Functions.
 */
class Disable_Blog_Public_Functions_Double extends Disable_Blog_Functions {

	/**
	 * @var bool
	 */
	public $disable_author_archives_return = false;

	/**
	 * @var array|bool
	 */
	public $author_archive_post_types_return = false;

	/**
	 * @var bool
	 */
	public $disable_feeds_return = false;

	/**
	 * Every url passed to redirect(), in call order.
	 *
	 * @var string[]
	 */
	public $redirect_calls = array();

	/**
	 * @return bool
	 */
	public function disable_author_archives() {
		return $this->disable_author_archives_return;
	}

	/**
	 * @return array|bool
	 */
	public function author_archive_post_types() {
		return $this->author_archive_post_types_return;
	}

	/**
	 * @param object $post            The global post object.
	 * @param bool   $is_comment_feed True if this is a comment feed.
	 * @return bool
	 */
	public function disable_feeds( $post, $is_comment_feed = false ) {
		return $this->disable_feeds_return;
	}

	/**
	 * @param string $redirect_url The url that was redirected to.
	 * @return void
	 */
	public function redirect( $redirect_url ) {
		$this->redirect_calls[] = $redirect_url;
	}
}
