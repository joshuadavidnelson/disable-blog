<?php

/**
 * Fired during plugin activation
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.3
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.4.3
 * @package    Disable_Blog
 * @subpackage Disable_Blog/includes
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */
class Disable_Blog_Activator {

	/**
	 * Clear the global comment count cache.
	 *
	 * @since    0.4.3
	 */
	public static function activate() {
		wp_cache_delete( 'comments-0', 'counts' );
		delete_transient( 'wc_count_comments' );
	}

}
