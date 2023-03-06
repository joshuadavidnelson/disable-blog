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

	/**
	 * Check if the Disable Comments plugin is active.
	 *
	 * @return bool
	 */
	public function is_disable_comments_active() {

		// Check if the Disable Comments plugin is active.
		if ( $this->is_plugin_active( 'disable-comments/disable-comments.php' ) || class_exists( 'Disable_Comments' ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public function is_woocommerce_active() {

		// Check if the Disable Comments plugin is active.
		if ( $this->is_plugin_active( 'woocommerce/woocommerce.php' ) || function_exists( 'WC' ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Turn the comments object back into an array if WooCommerce is active.
	 *
	 * This is only necessary for version of WooCommerce prior to 2.6.3, where it failed
	 * to check/convert the $comment object into an array.
	 *
	 * @since 0.4.3
	 * @param object $comments the array of comments.
	 * @param int    $post_id  the post id.
	 * @return array
	 */
	public function filter_woocommerce_comment_count( $comments, $post_id ) {

		if ( 0 === $post_id && version_compare( WC()->version, '2.6.2', '<=' ) ) {
			$comments = (array) $comments;
		}

		return $comments;

	}

}
