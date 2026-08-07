<?php
/**
 * Configurable Disable_Blog_Functions double for Disable_Blog_Admin tests.
 *
 * Overrides the Disable_Blog_Functions methods Disable_Blog_Admin calls through
 * $this->functions, so a test can assert exactly what url redirect_admin_pages() decided
 * on -- without exercising Disable_Blog_Functions's own real WordPress-calling logic, which
 * DisableBlogFunctionsTest already covers.
 *
 * @package DisableBlog
 */

/**
 * Test double standing in for Disable_Blog_Functions.
 */
class Disable_Blog_Admin_Functions_Double extends Disable_Blog_Functions {

	/**
	 * Every url passed to redirect(), in call order.
	 *
	 * @var string[]
	 */
	public $redirect_calls = array();

	/**
	 * @param string $redirect_url The url that was redirected to.
	 * @return void
	 */
	public function redirect( $redirect_url ) {
		$this->redirect_calls[] = $redirect_url;
	}
}
