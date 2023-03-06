<?php
/**
 * Integrations with other plugins.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @package    Disable_Blog
 * @subpackage Disable_Blog_Integrations
 */

/**
 * Integrations with other plugins.
 */
class Disable_Blog_Integrations {

	/**
	 * The ID of this plugin.
	 *
	 * @access private
	 * @var    string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @access private
	 * @var    string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Object with common utility functions.
	 *
	 * @access private
	 * @var    object
	 */
	private $functions;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->functions   = new Disable_Blog_Functions();

	}

	/**
	 * Check if the plugin is active.
	 *
	 * @param string $plugin
	 * @return bool
	 */
	public function is_plugin_active( $plugin ) {

		// Check if the is_plugin_active function is available.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check if the the plugin is active.
		if ( is_plugin_active( $plugin ) ) {
			return true;
		}

		return false;

	}

}
