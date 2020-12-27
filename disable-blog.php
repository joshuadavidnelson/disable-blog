<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link    https://github.com/joshuadavidnelson/disable-blog
 * @since   0.4.0
 * @package Disable_Blog
 *
 * @wordpress-plugin
 * Plugin Name: Disable Blog
 * Plugin URI:  https://wordpress.org/plugins/disable-blog/
 * Description: Go blog-less with WordPress. This plugin disables all blog-related functionality (by hiding, removing, and redirecting).
 * Version:     0.4.10
 * Author:      Joshua Nelson
 * Author URI:  http://joshuadnelson.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: disable-blog
 * Domain Path: /languages
 */

/**
 * Exit if accessed directly.
 *
 * Prevent direct access to this file.
 *
 * @since 0.1.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-disable-blog-activator.php
 */
function activate_disable_blog() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-disable-blog-activator.php';
	Disable_Blog_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-disable-blog-deactivator.php
 */
function deactivate_disable_blog() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-disable-blog-deactivator.php';
	Disable_Blog_Deactivator::deactivate();
}
register_activation_hook( __FILE__, 'activate_disable_blog' );
register_deactivation_hook( __FILE__, 'deactivate_disable_blog' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-disable-blog.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 0.4.0
 */
function run_disable_blog() {
	$plugin = new Disable_Blog();
	$plugin->run();
}
add_action( 'plugins_loaded', 'run_disable_blog', 10, 0 );
