<?php
/**
 * Tests for includes/class-disable-blog-i18n.php.
 *
 * @package DisableBlog
 */

/**
 * @covers Disable_Blog_I18n
 */
class I18nTest extends TestCase {

	/**
	 * load_plugin_textdomain() must be called with the plugin's text domain, a false
	 * "plugin_rel_path" (deprecated WP argument, kept false), and the languages/ path
	 * resolved relative to the plugin root (two directories up from this class file).
	 */
	public function test_load_plugin_textdomain_calls_wp_with_domain_and_languages_path() {
		WP_Mock::userFunction( 'plugin_basename' )
			->once()
			->andReturn( 'disable-blog/includes/class-disable-blog-i18n.php' );

		$captured_args = null;

		WP_Mock::userFunction( 'load_plugin_textdomain' )
			->once()
			->andReturnUsing(
				function ( $domain, $plugin_rel_path, $languages_path ) use ( &$captured_args ) {
					$captured_args = array( $domain, $plugin_rel_path, $languages_path );

					return true;
				}
			);

		$i18n = new Disable_Blog_I18n();
		$i18n->load_plugin_textdomain();

		$this->assertSame(
			array( 'disable-blog', false, 'disable-blog/languages/' ),
			$captured_args
		);
	}
}
