<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.0
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.4.0
 * @package    Disable_Blog
 * @subpackage Disable_Blog/includes
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */
class Disable_Blog {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    0.4.0
	 *
	 * @access   protected
	 *
	 * @var      Disable_Blog_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    0.4.0
	 *
	 * @access   protected
	 *
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    0.4.0
	 *
	 * @access   protected
	 *
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since 0.4.0
	 *
	 * @access public
	 */
	public function __construct() {

		$this->plugin_name = 'disable-blog';
		$this->version     = '0.4.10';

		do_action( 'dwpb_init' );

		$this->setup_constants();
		$this->upgrade_check();
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Upgrade check.
	 *
	 * @since 0.4.0
	 *
	 * @access private
	 */
	private static function upgrade_check() {

		// let's only run these checks on the admin page load.
		if ( ! is_admin() ) {
			return;
		}

		// Get the current version option.
		$current_version = get_option( 'dwpb_version', false );

		// Update the previous version if we're upgrading.
		if ( $current_version && DWPB_VERSION !== $current_version ) {
			update_option( 'dwpb_previous_version', $current_version, false );
		}

		// See if it's a previous version, which may not have set the version option.
		if ( false === $current_version || DWPB_VERSION !== $current_version ) {
			// do things on update.

			// Save current version.
			update_option( 'dwpb_version', DWPB_VERSION, false );
		}

	}

	/**
	 * Define Constants
	 *
	 * @since 0.3.0
	 *
	 * @access private
	 */
	private function setup_constants() {

		// For includes and whatnot.
		if ( ! defined( 'DWPB_DIR' ) ) {
			define( 'DWPB_DIR', dirname( __FILE__ ) );
		}

		// For calling scripts and so forth.
		if ( ! defined( 'DWPB_URL' ) ) {
			define( 'DWPB_URL', plugins_url( '/', __FILE__ ) );
		}

		// For admin settings field.
		if ( ! defined( 'DWPB_SETTINGS_FIELD' ) ) {
			define( 'DWPB_SETTINGS_FIELD', $this->plugin_name );
		}

		// To keep track of versions, useful if you need to make updates specific to versions.
		define( 'DWPB_VERSION', $this->version );

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Disable_Blog_Loader. Orchestrates the hooks of the plugin.
	 * - Disable_Blog_I18n. Defines internationalization functionality.
	 * - Disable_Blog_Admin. Defines all hooks for the admin area.
	 * - Disable_Blog_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since 0.4.0
	 *
	 * @access private
	 */
	private function load_dependencies() {

		$includes_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'includes';
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once $includes_dir . '/class-disable-blog-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once $includes_dir . '/class-disable-blog-i18n.php';

		/**
		 * The class containing all common functions for use in the plugin
		 */
		require_once $includes_dir . '/functions.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once $includes_dir . '/class-disable-blog-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once $includes_dir . '/class-disable-blog-public.php';

		$this->loader = new Disable_Blog_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Disable_Blog_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    0.4.0
	 *
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Disable_Blog_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    0.4.0
	 *
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Disable_Blog_Admin( $this->get_plugin_name(), $this->get_version() );

		// Hide items with CSS.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );

		// Hide Blog Related Admin pages.
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'remove_menu_pages' );

		// Modify the main 'post' post_type arguments to shut pubic things down.
		$this->loader->add_action( 'init', $plugin_admin, 'modify_post_type_arguments', 25 );

		// Modify the core taxonomy arguments to filter out posts and shut down public things.
		$this->loader->add_action( 'init', $plugin_admin, 'modify_taxonomies_arguments', 25 );

		// Redirect Blog-related Admin Pages.
		$this->loader->add_action( 'current_screen', $plugin_admin, 'redirect_admin_pages' );

		// Filter comment counts in admin table.
		$this->loader->add_filter( 'views_edit-comments', $plugin_admin, 'filter_admin_table_comment_count', 20, 1 );

		// Filter post open status for comments and pings.
		$this->loader->add_action( 'comments_open', $plugin_admin, 'filter_comment_status', 20, 2 );
		$this->loader->add_action( 'pings_open', $plugin_admin, 'filter_comment_status', 20, 2 );

		// Filter wp_count_comments, which addresses comments in admin bar.
		$this->loader->add_filter( 'wp_count_comments', $plugin_admin, 'filter_wp_count_comments', 10, 2 );

		// Convert the $comments object back into an array if older version of WooCommerce is active.
		$this->loader->add_filter( 'wp_count_comments', $plugin_admin, 'filter_woocommerce_comment_count', 10, 2 );

		// Remove Admin Bar Links.
		$this->loader->add_action( 'wp_before_admin_bar_render', $plugin_admin, 'remove_admin_bar_links' );

		// Filter Comments off Admin Page.
		$this->loader->add_action( 'pre_get_comments', $plugin_admin, 'comment_filter', 10, 1 );

		// Clear comments from 'post' post type.
		$this->loader->add_filter( 'comments_array', $plugin_admin, 'filter_existing_comments', 20, 2 );

		// Remove the X-Pingback HTTP header.
		$this->loader->add_filter( 'wp_headers', $plugin_admin, 'filter_wp_headers', 10, 1 );

		// Disable Update Services configruation, no pingbacks.
		add_filter( 'enable_update_services_configuration', '__return_false' );

		// Clear comments from 'post' post type.
		$this->loader->add_filter( 'comments_array', $plugin_admin, 'filter_existing_comments', 20, 2 );

		// Remove Dashboard Widgets.
		$this->loader->add_action( 'admin_init', $plugin_admin, 'remove_dashboard_widgets' );

		// Admin notices.
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'admin_notices' );

		// Add a class to the admin body for the reading options page.
		$this->loader->add_filter( 'admin_body_class', $plugin_admin, 'admin_body_class', 10, 1 );

		// Remove Post via Email Settings.
		add_filter( 'enable_post_by_email_configuration', '__return_false' );

		// Disable Press This Function.
		$this->loader->add_action( 'load-press-this.php', $plugin_admin, 'disable_press_this' );

		// Remove Post Related Widgets.
		$this->loader->add_action( 'widgets_init', $plugin_admin, 'remove_widgets' );

		// Filter removal of widgets for some checks.
		$this->loader->add_filter( 'dwpb_unregister_widgets', $plugin_admin, 'filter_widget_removal', 10, 2 );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    0.4.0
	 *
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Disable_Blog_Public( $this->get_plugin_name(), $this->get_version() );

		// Redirect Single Posts.
		$this->loader->add_action( 'template_redirect', $plugin_public, 'redirect_posts' );

		// Modify Query.
		$this->loader->add_action( 'pre_get_posts', $plugin_public, 'modify_query' );

		// Disable Feed.
		$this->loader->add_action( 'do_feed', $plugin_public, 'disable_feed', 1, 2 );
		$this->loader->add_action( 'do_feed_rdf', $plugin_public, 'disable_feed', 1, 2 );
		$this->loader->add_action( 'do_feed_rss', $plugin_public, 'disable_feed', 1, 2 );
		$this->loader->add_action( 'do_feed_rss2', $plugin_public, 'disable_feed', 1, 2 );
		$this->loader->add_action( 'do_feed_atom', $plugin_public, 'disable_feed', 1, 2 );

		// Remove feed links from the header.
		$this->loader->add_action( 'wp_loaded', $plugin_public, 'header_feeds', 1, 1 );

		// Hide Feed links.
		$this->loader->add_filter( 'feed_links_show_posts_feed', $plugin_public, 'feed_links_show_posts_feed', 10, 1 );
		$this->loader->add_filter( 'feed_links_show_comments_feed', $plugin_public, 'feed_links_show_comments_feed', 10, 1 );

		// Disable XML-RPC methods related to posts and built-in taxonomies.
		$this->loader->add_filter( 'xmlrpc_methods', $plugin_public, 'xmlrpc_methods', 10, 1 );

		// Remove posts from xml sitemaps.
		$this->loader->add_filter( 'wp_sitemaps_post_types', $plugin_public, 'wp_sitemaps_post_types', 10, 1 );

		// Conditionally remove built-in taxonomies from sitemaps, if they are not being used by a custom post type.
		$this->loader->add_filter( 'wp_sitemaps_taxonomies', $plugin_public, 'wp_sitemaps_taxonomies', 10, 1 );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    0.4.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     0.4.0
	 *
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     0.4.0
	 *
	 * @return    Disable_Blog_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     0.4.0
	 *
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
