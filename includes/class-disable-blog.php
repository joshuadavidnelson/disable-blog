<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.0
 * @package    Disable_Blog
 * @subpackage Disable_Blog\Includes
 * @author     Joshua Nelson <josh@joshuadnelson.com>
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
 * @since 0.4.0
 */
class Disable_Blog {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since 0.4.0
	 * @access protected
	 * @var Disable_Blog_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since 0.4.0
	 * @access protected
	 * @var string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since 0.4.0
	 * @access protected
	 * @var string $version The current version of the plugin.
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
	 * @access public
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		do_action( 'dwpb_init' );

		$this->upgrade_check();
		$this->load_dependencies();
		$this->set_locale();
		$this->plugin_integrations();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Upgrade check.
	 *
	 * @since 0.4.0
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
	 * @since 0.5.3 Added Integrations class.
	 * @access private
	 */
	private function load_dependencies() {

		// Includes directory.
		$includes_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'includes';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once $includes_dir . '/class-disable-blog-loader.php';

		/**
		 * File with common functions.
		 */
		require_once $includes_dir . '/functions.php';

		/**
		 * Make it so.
		 */
		$this->loader = new Disable_Blog_Loader();

		$classes = array(
			'Disable_Blog_I18n',
			'Disable_Blog_Functions',
			'Disable_Blog_Admin',
			'Disable_Blog_Public',
			'Disable_Blog_Integrations',
		);
		foreach ( $classes as $class ) {
			$this->loader->autoLoader( $class );
		}

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Disable_Blog_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since 0.4.0
	 * @access private
	 */
	private function set_locale() {

		$plugin_i18n = new Disable_Blog_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since 0.4.0
	 * @since 0.5.3 Separated comment functions to run only if comments are supported.
	 * @access private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Disable_Blog_Admin( $this->get_plugin_name(), $this->get_version() );

		// Add Links to Plugin Bar.
		$this->loader->add_filter( 'plugin_row_meta', $plugin_admin, 'plugin_links', 10, 2 );

		// Hide items with CSS.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );

		// Hide items with JavaScript where CSS doesn't do the job as well.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts', 100 );

		// Hide Blog Related Admin pages.
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'remove_menu_pages' );

		// Modify the main 'post' post_type arguments to shut pubic things down.
		$this->loader->add_action( 'init', $plugin_admin, 'modify_post_type_arguments', 25 );

		// Modify the core taxonomy arguments to filter out posts and shut down public things.
		$this->loader->add_action( 'init', $plugin_admin, 'modify_taxonomies_arguments', 25 );

		// Redirect Blog-related Admin Pages.
		$this->loader->add_action( 'current_screen', $plugin_admin, 'redirect_admin_pages' );

		// Filter post open status for pings.
		$this->loader->add_action( 'pings_open', $plugin_admin, 'filter_comment_status', 20, 2 );

		// Remove Admin Bar Links.
		$this->loader->add_action( 'wp_before_admin_bar_render', $plugin_admin, 'remove_admin_bar_links' );

		// Disable Update Services configruation, no pingbacks.
		add_filter( 'enable_update_services_configuration', '__return_false' );

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

		// Custom Post State for the Blog Page redirect.
		$this->loader->add_filter( 'display_post_states', $plugin_admin, 'page_post_states', 10, 2 );

		// Remove REST API site health check related to posts.
		$this->loader->add_filter( 'site_status_tests', $plugin_admin, 'site_status_tests', 10, 1 );

		// Replace 'post' column with 'page' column.
		$this->loader->add_action( 'manage_users_columns', $plugin_admin, 'manage_users_columns', 10, 1 );
		$this->loader->add_filter( 'manage_users_custom_column', $plugin_admin, 'manage_users_custom_column', 10, 3 );

		// Remove the "view" link from the user options if author archives are not supported.
		$this->loader->add_filter( 'user_row_actions', $plugin_admin, 'user_row_actions', 10, 2 );

		// Filter post counts on post-related taxonomy edit screens for custom post types.
		$this->loader->add_filter( 'post_tag_row_actions', $plugin_admin, 'filter_taxonomy_count', 10, 2 );
		$this->loader->add_filter( 'category_row_actions', $plugin_admin, 'filter_taxonomy_count', 10, 2 );

		// Update customizer homepage settings panel to match the Reading settings.
		$this->loader->add_action( 'customize_controls_print_styles', $plugin_admin, 'customizer_styles', 999 );
		$this->loader->add_action( 'customize_controls_enqueue_scripts', $plugin_admin, 'customizer_scripts', 999 );

		// Update Blog page notice.
		$this->loader->add_action( 'post_edit_form_tag', $plugin_admin, 'update_posts_page_notice', 10, 1 );

		// Remove and update available permalink structure tags.
		$this->loader->add_filter( 'available_permalink_structure_tags', $plugin_admin, 'available_permalink_structure_tags', 10, 1 );

		// Only run comment related functions if comments are supported.
		if ( dwpb_post_types_with_feature( 'comments' ) ) {

			// Filter comment counts in admin table.
			$this->loader->add_filter( 'views_edit-comments', $plugin_admin, 'filter_admin_table_comment_count', 20, 1 );

			// Filter post open status for comments.
			$this->loader->add_action( 'comments_open', $plugin_admin, 'filter_comment_status', 20, 2 );

			// Filter wp_count_comments, which addresses comments in admin bar.
			$this->loader->add_filter( 'wp_count_comments', $plugin_admin, 'filter_wp_count_comments', 10, 2 );

			// Filter Comments off Admin Page.
			$this->loader->add_action( 'pre_get_comments', $plugin_admin, 'comment_filter', 10, 1 );

			// Clear comments from 'post' post type.
			$this->loader->add_filter( 'comments_array', $plugin_admin, 'filter_existing_comments', 20, 2 );

		}

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since 0.4.0
	 * @access private
	 */
	private function define_public_hooks() {

		$plugin_public = new Disable_Blog_Public( $this->get_plugin_name(), $this->get_version() );

		// Redirect Public pages (single posts, archives, etc).
		$this->loader->add_action( 'template_redirect', $plugin_public, 'redirect_public_pages' );

		// Modify Query. Setting to priority 9 to allow default filter priority to override.
		$this->loader->add_action( 'pre_get_posts', $plugin_public, 'modify_query', 9 );

		// Disable Feed.
		$this->loader->add_action( 'do_feed', $plugin_public, 'disable_feed', 1, 2 );
		$this->loader->add_action( 'do_feed_rdf', $plugin_public, 'disable_feed', 1, 2 );
		$this->loader->add_action( 'do_feed_rss', $plugin_public, 'disable_feed', 1, 2 );
		$this->loader->add_action( 'do_feed_rss2', $plugin_public, 'disable_feed', 1, 2 );
		$this->loader->add_action( 'do_feed_atom', $plugin_public, 'disable_feed', 1, 2 );

		// Remove feed links from the header.
		$this->loader->add_action( 'wp_loaded', $plugin_public, 'header_feeds', 1, 1 );

		// Remove the X-Pingback HTTP header.
		$this->loader->add_filter( 'wp_headers', $plugin_public, 'filter_wp_headers', 10, 1 );

		// Hide Feed links.
		$this->loader->add_filter( 'feed_links_show_posts_feed', $plugin_public, 'feed_links_show_posts_feed', 10, 1 );
		$this->loader->add_filter( 'feed_links_show_comments_feed', $plugin_public, 'feed_links_show_comments_feed', 10, 1 );

		// Disable XML-RPC methods related to posts and built-in taxonomies.
		$this->loader->add_filter( 'xmlrpc_methods', $plugin_public, 'xmlrpc_methods', 10, 1 );

		// Remove posts from xml sitemaps.
		$this->loader->add_filter( 'wp_sitemaps_post_types', $plugin_public, 'wp_sitemaps_post_types', 10, 1 );

		// Conditionally remove built-in taxonomies from sitemaps, if they are not being used by a custom post type.
		$this->loader->add_filter( 'wp_sitemaps_taxonomies', $plugin_public, 'wp_sitemaps_taxonomies', 10, 1 );

		// Conditionally remove author sitemaps, if author archives are not being supported.
		$this->loader->add_filter( 'wp_sitemaps_add_provider', $plugin_public, 'wp_author_sitemaps', 100, 2 );

	}

	/**
	 * Integrate with other plugins.
	 *
	 * @since 0.5.3
	 * @return void
	 */
	public function plugin_integrations() {

		$plugin_integrations = new Disable_Blog_Integrations( $this->get_plugin_name(), $this->get_version() );

		// Disable Comments.
		if ( $plugin_integrations->is_disable_comments_active() ) {

			// If Disabled Comments is active, return false for post types supporting comments,
			// and functionality in Disable Blog related to comments will be turned off,
			// the assumption being that the Disable Comments plugin is handling it.
			add_filter( 'dwpb_post_types_supporting_comments', '__return_false' );
		}

		// WooCommerce.
		if ( $plugin_integrations->is_woocommerce_active() && dwpb_post_types_with_feature( 'comments' ) ) {

			// Convert the $comments object back into an array if older version of WooCommerce is active.
			$this->loader->add_filter( 'wp_count_comments', $plugin_integrations, 'filter_woocommerce_comment_count', 15, 2 );
		}

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since 0.4.0
	 * @access public
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since 0.4.0
	 * @access public
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since 0.4.0
	 * @access public
	 * @return Disable_Blog_Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since 0.4.0
	 * @access public
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
