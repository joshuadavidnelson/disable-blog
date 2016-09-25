<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.3
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.4.3
 * @package    Disable_Blog
 * @subpackage Disable_Blog/includes
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */
class Disable_Blog_Deactivator {

	/**
	 * Clear the global comment cache.
	 *
	 * @since    0.4.3
	 */
	public static function deactivate() {
		wp_cache_delete( 'comments-0', 'counts' );
		delete_transient( 'wc_count_comments' );
	}

}
