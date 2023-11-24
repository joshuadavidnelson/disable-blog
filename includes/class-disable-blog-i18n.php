<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.0
 * @package    Disable_Blog
 * @subpackage Disable_Blog\Includes
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since 0.4.0
 */
class Disable_Blog_I18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 0.4.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'disable-blog',
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
		);
	}
}
