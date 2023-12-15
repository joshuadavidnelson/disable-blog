<?php
/**
 * Integrations with other plugins.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.5.3
 * @package    Disable_Blog
 * @subpackage Disable_Blog_Integrations
 */

/**
 * Integrations with other plugins.
 *
 * @since 0.5.3
 */
class Disable_Blog_Integrations {

	/**
	 * Check if the plugin is active.
	 *
	 * A wrapper function of is_plugin_active to call wp-admin/includes/plugin.php as needed.
	 *
	 * @since 0.5.3
	 * @see https://developer.wordpress.org/reference/functions/is_plugin_active/
	 * @param string $plugin the plugin path.
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
	 * @since 0.5.3
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
	 * @since 0.5.3
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
	 * @since 0.5.3 Moved to the Disable_Blog_Integrations class.
	 * @param object $comments the array of comments.
	 * @param int    $post_id  the post id.
	 * @return array
	 */
	public function filter_woocommerce_comment_count( $comments, $post_id ) {

		if ( 0 === $post_id && $this->woocommerce_version_check( '2.6.2' ) ) {
			$comments = (array) $comments;
		}

		return $comments;
	}

	/**
	 * Check if the WooCommerce version is less than the checked version.
	 *
	 * @since 0.5.4
	 * @param string $checked_version The version to check against.
	 * @param string $check           The comparison operator.
	 * @return bool
	 */
	private function woocommerce_version_check( $checked_version, $check = '<=' ) {

		// Check if WooCommerce is active.
		if ( $this->is_woocommerce_active() ) {

			// Figure out the version of WooCommerce.
			if ( defined( 'WC_VERSION' ) ) {
				$woo_version = WC_VERSION;
			} elseif ( defined( 'WOOCOMMERCE_VERSION' ) ) {
				$woo_version = WOOCOMMERCE_VERSION;
			} else {
				return false;
			}

			// Check if the WooCommerce version is less than the checked version.
			return version_compare( $woo_version, $checked_version, $check );
		}

		return false;
	}
}
