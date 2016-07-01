<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.0
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog/admin
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */
class Disable_Blog_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.4.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.4.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since	0.4.0
	 * @param	string    $plugin_name       The name of this plugin.
	 * @param	string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}
	
	/**
	 * Remove Post Related Menus
	 *
	 * @since 0.1.0
	 * @link http://wordpress.stackexchange.com/questions/57464/remove-posts-from-admin-but-show-a-custom-post
	 */
	public function remove_menu_pages() {
		// Menu Pages
		$pages = apply_filters( 'dwpb_menu_pages_to_remove', array( 'edit.php' ) );
		foreach( $pages as $page ) {
			remove_menu_page( $page );
		}
		// Submenu Pages
		$subpages = apply_filters( 'dwpb_menu_pages_to_remove', array( 'options-general.php' => 'options-writing.php' ) );
		foreach( $subpages as $page => $subpage ) {
			remove_submenu_page( $page, $subpage );
		}
	}
	
	/**
	 * Redirect blog-related admin pages
	 *
	 * @uses dwpb_post_types_with_tax()
	 *
	 * @since 0.1.0
	 */
	public function redirect_admin_pages() {
		global $pagenow;

		if( !isset( $pagenow ) ) {
			return;
		}
		
		// Redirect Edit Single Post to Dashboard
		if( 'post.php' == $pagenow && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) && apply_filters( 'dwpb_redirect_admin_edit_single_post', true ) ) {
			$url = admin_url( '/index.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_sinlge_post_edit', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		}
		
		// Redirect Edit Posts Screen to Edit Page
		if( 'edit.php' == $pagenow && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) && apply_filters( 'dwpb_redirect_admin_edit_post', true ) ) {
			$url = admin_url( '/edit.php?post_type=page' );
			$redirect_url = apply_filters( 'dwpb_redirect_edit', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		}
	
		// Redirect New Post to New Page
		if( 'post-new.php' == $pagenow && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) && apply_filters( 'dwpb_redirect_admin_post_new', true ) ) {
			$url = admin_url('/post-new.php?post_type=page' );
			$redirect_url = apply_filters( 'dwpb_redirect_post_new', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		}
	
		// Redirect at edit tags screen
		// If this is a post type other than 'post' that supports categories or tags,
		// then bail. Otherwise if it is a taxonomy only used by 'post'
		// Alternatively, if this is either the edit-tags page and a taxonomy is not set
		// and the built-in default 'post_tags' is not supported by other post types
		if( ( 'edit-tags.php' == $pagenow || 'term.php' == $pagenow ) && ( isset( $_GET['taxonomy'] ) && ! dwpb_post_types_with_tax( $_GET['taxonomy'] ) ) && apply_filters( 'dwpb_redirect_admin_edit_tags', true ) ) {
			$url = admin_url( '/index.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_edit_tax', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		} 
	
		// Redirect posts-only comment queries to comments
		if( 'edit-comments.php' == $pagenow && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' && apply_filters( 'dwpb_redirect_admin_edit_comments', true ) ) {
			$url = admin_url( '/edit-comments.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_edit_comments', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		}
	
		// Redirect writing options to general options
		if( 'options-writing.php' == $pagenow && apply_filters( 'dwpb_redirect_admin_options_writing', true ) ) {
			$url = admin_url( '/options-general.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_options_writing', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		}
	}
	
	/**
	 * Remove blog-related admin bar links
	 *
	 * @uses dwpb_post_types_with_feature()
	 *
	 * @since 0.1.0
	 *
	 * @link http://www.paulund.co.uk/how-to-remove-links-from-wordpress-admin-bar
	 */
	public function remove_admin_bar_links() {
		global $wp_admin_bar;
	
		// If only posts support comments, then remove comment from admin bar
		if( ! dwpb_post_types_with_feature( 'comments' ) )
		    $wp_admin_bar->remove_menu( 'comments' );
	
		// Remove New Post from Content
		$wp_admin_bar->remove_node( 'new-post' );
	}

	/**
	 * Hide all comments from 'post' post type
	 *
	 * @uses dwpb_post_types_with_feature()
	 * 
	 * @since 0.1.0
	 *
	 * @param  (wp_query object) $comments
	 */
	public function comment_filter( $comments ){
		global $pagenow;
	
		if( !isset( $pagenow ) )
			return $comments;
		
		// Filter out comments from post
		if( is_admin() && $pagenow == 'edit-comments.php' ) {
			if( $post_types = dwpb_post_types_with_feature( 'comments' ) ) {
				$comments->query_vars['post_type'] = $post_types;
			}
		}
	
		return $comments;
	}
	
	/**
	 * Remove post-related dashboard widgets
	 *
	 * @uses dwpb_post_types_with_feature()
	 *
	 * @since 0.1.0
	 */
	function remove_dashboard_widgets() {
		
		// recent comments
		if( apply_filters( 'dwpb_disable_dashboard_recent_comments', true ) && ! dwpb_post_types_with_feature( 'comments' ) )
			remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	
		// incoming links
		if( apply_filters( 'dwpb_disable_dashboard_incoming_links', true ) )
			remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
	
		// quick press
		if( apply_filters( 'dwpb_disable_dashboard_quick_press', true ) )
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'normal' );
	
		// recent drafts
		if( apply_filters( 'dwpb_disable_dashboard_recent_drafts', true ) )
			remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'normal' );
	
		// activity
		if( apply_filters( 'dwpb_disable_dashboard_activity', true ) )
			remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
	}
	
	/**
	 * Set Page for Posts options: 'show_on_front', 'page_for_posts', 'page_on_front'
	 * 
	 * If the 'show_on_front' option is set to 'posts', then set it to 'page'
	 * and also set the page
	 * 
	 * @since 0.2.0
	 */
	public function reading_settings() {
		if( get_option( 'show_on_front' ) == 'post' ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_for_posts', apply_filters( 'dwpb_page_for_posts', 0 ) );
			update_option( 'page_on_front', apply_filters( 'dwpb_page_on_front', 0 ) );
		}
	}
	
	/**
	 * Kill the Press This functionality
	 * 
	 * @since 0.2.0
	 */
	public function disable_press_this() {
		wp_die( '"Press This" functionality has been disabled.' );
	}
	
	/**
	 * Remove post related widgets
	 * 
	 * @since 0.2.0
	 */
	public function remove_widgets() {
		
		// Remove Recent Posts
		unregister_widget( 'WP_Widget_Recent_Posts' );
	
		// Remove Categories Widget
		if( ! dwpb_post_types_with_tax( 'category' ) )
			unregister_widget( 'WP_Widget_Categories' );
	
		// Remove Recent Comments Widget if posts are the only type with comments
		if( ! dwpb_post_types_with_feature( 'comments' ) )
			unregister_widget( 'WP_Widget_Recent_Comments' );
	
		// Remove Tag Cloud
		if( ! dwpb_post_types_with_tax( 'post_tag' ) )
			unregister_widget( 'WP_Widget_Tag_Cloud' );
	
		// Remove RSS Widget
		unregister_widget( 'WP_Widget_RSS' );
	
		// Remove Archive Widget
		unregister_widget( 'WP_Widget_Archives' );
	
		// Remove Calendar Widget
		unregister_widget( 'WP_Widget_Calendar' );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    0.4.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Disable_Blog_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Disable_Blog_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/disable-blog-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Add various links to plugin page
	 *
	 * @since  0.2.0
	 *
	 * @param  $links
	 * @param  $file
	 *
	 * @return strings plugin links
	 */
	function plugin_links( $links, $file ) {
	    static $this_plugin;

		/** Capability Check */
		if( ! current_user_can( 'install_plugins' ) ) 
			return $links;

		if( !$this_plugin ) {
			$this_plugin = plugin_basename(__FILE__);
		}

		if( $file == $this_plugin ) {
			$links[] = '<a href="http://wordpress.org/support/plugin/disable-blog" title="' . __( 'Support', DWPB_DOMAIN ) . '">' . __( 'Support', DWPB_DOMAIN ) . '</a>';

			$links[] = '<a href="http://jdn.im/donate" title="' . __( 'Donate', DWPB_DOMAIN ) . '">' . __( 'Donate', DWPB_DOMAIN ) . '</a>';
		}
	
		return $links;
	}

}
