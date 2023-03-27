<?php
/**
 * Disable Blog unintstall.
 *
 * Fired when the plugin is uninstalled.
 *
 * @link    https://github.com/joshuadavidnelson/disable-blog
 * @since   0.4.0
 *
 * @package Disable_Blog
 */

/**
 * Prevent direct access to this file.
 *
 * @since 0.4.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

/**
 * If uninstall not called from WordPress,
 * If no uninstall action,
 * If not this plugin,
 * If no caps,
 * then exit.
 *
 * @since 0.4.0
 * @uses  WP_UNINSTALL_PLUGIN
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' )
		|| empty( $_REQUEST )
		|| ! isset( $_REQUEST['plugin'] )
		|| ! isset( $_REQUEST['action'] )
		|| strpos( $_REQUEST['plugin'], 'disable-blog.php' ) === false
		|| 'delete-plugin' !== $_REQUEST['action']
		|| ! check_ajax_referer( 'updates', '_ajax_nonce' )
		|| ! current_user_can( 'activate_plugins' )
	) {

	exit();

}

/**
 * Various user checks.
 *
 * @since 0.4.0
 *
 * @uses  is_user_logged_in()
 * @uses  current_user_can()
 * @uses  wp_die()
 */
if ( ! is_user_logged_in() ) {
	// translators: This error shows up when the uninstall process is run but the user login session is invalid.
	$message = __( 'You must be logged in to run this script.', 'disable-blog' );

	// translators: The plugin name.
	$message_title = __( 'Disable Blog', 'disable-blog' );
	wp_die(
		esc_attr( $message ),
		esc_attr( $message_title ),
		array( 'back_link' => true )
	);
}

if ( ! current_user_can( 'install_plugins' ) ) {
	// translators: This error shows up if the user does not have permissions to uninstall the plugin.
	$message = __( 'You do not have permission to run this script.', 'disable-blog' );

	// translators: The plugin name.
	$message_title = __( 'Disable Blog', 'disable-blog' );
	wp_die(
		esc_attr( $message ),
		esc_attr( $message_title ),
		array( 'back_link' => true )
	);
}

/**
 * Delete options array (settings field) from the database.
 *
 * Note: Respects Multisite setups and single installs.
 *
 * @since 0.4.0
 * @since 0.6.0 included check for large networks.
 *
 * @uses  switch_to_blog()
 * @uses  restore_current_blog()
 *
 * @param array $blogs
 * @param int   $blog
 *
 * @global $wpdb
 */
// First, check for Multisite, if yes, delete options on a per site basis.
// But we only do this if it's ~not~ a large network to avoid performance issue.
if ( is_multisite() ) {

	// core assumes 10k is large, but 5k is still pretty big.
	$network_id = get_current_network_id();
	$count      = get_blog_count( $network_id );
	if ( $count < 5000 ) {

		global $wpdb;

		// Get array of Site/Blog IDs from the database.
		$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );

		if ( $blogs ) {
			foreach ( $blogs as $blog ) {
				// Repeat for every Site ID.
				switch_to_blog( $blog['blog_id'] );

				// Delete plugin options.
				delete_option( 'dwpb_version' );
				delete_option( 'dwpb_previous_version' );
			}
			restore_current_blog();
		}
	}
} else { // Otherwise, delete options from main options table.
	// Delete plugin options.
	delete_option( 'dwpb_version' );
	delete_option( 'dwpb_previous_version' );
}
