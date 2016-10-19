<?php

/**
 * Disable Blog unintstall.
 *
 * Fired when the plugin is uninstalled.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.0
 *
 * @package    Disable_Blog
 */

/**
 * Prevent direct access to this file.
 *
 * @since 0.4.0
 */
if ( !defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

/**
 * If uninstall not called from WordPress, exit.
 *
 * @since 0.4.0
 *
 * @uses  WP_UNINSTALL_PLUGIN
 */
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    header( 'Status: 403 Forbidden' );
    header( 'HTTP/1.1 403 Forbidden' );
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
if( !is_user_logged_in() ) {
	wp_die(
		__( 'You must be logged in to run this script.', 'disable-blog' ),
		__( 'Disable Blog', 'disable-blog' ),
		array( 'back_link' => true )
	);
} 

if( !current_user_can( 'install_plugins' ) ) {
	wp_die(
		__( 'You do not have permission to run this script.', 'disable-blog' ),
		__( 'Disable Blog', 'disable-blog' ),
		array( 'back_link' => true )
	);	
}

/**
 * Delete options array (settings field) from the database.
 *    Note: Respects Multisite setups and single installs.
 *
 * @since 0.4.0
 *
 * @uses  switch_to_blog()
 * @uses  restore_current_blog()
 *
 * @param array $blogs
 * @param int 	$blog
 *
 * @global $wpdb
 */
// First, check for Multisite, if yes, delete options on a per site basis
if ( is_multisite() ) {
	global $wpdb;
	
	// Get array of Site/Blog IDs from the database 
	$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );
	
	if ( $blogs ) {
		foreach ( $blogs as $blog ) {
			// Repeat for every Site ID 
			switch_to_blog( $blog[ 'blog_id' ] );
			
			// Delete plugin options
			delete_option( 'dwpb_version' );
		} 
		restore_current_blog();
	}
	
} else { // Otherwise, delete options from main options table
	// Delete plugin options
	delete_option( 'dwpb_version' );
}