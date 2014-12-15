<?php
/**
 * Plugin Name: Disable WordPress Blog
 * Plugin URI: http://joshuadnelson.com
 * Description: A plugin that disables or hides all blog-related elements of your WordPress site.
 * Version: 0.1.0
 * Author: Joshua Nelson
 * Author URI: http://joshuadnelson.com
 * GitHub Plugin URI: https://github.com/joshuadavidnelson/disable-wordpress-blog
 * GitHub Branch: master
 * License: GPL v2.0
 */

/**
 * Prevent direct access to this file.
 *
 * @since 0.1.0
 */
if( !defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

/**
 * Define Constants
 *
 * @since 0.1.0
 */
if( !defined( 'DWPB_DIR' ) )
	define( 'DWPB_DIR', dirname( __FILE__ ) );

if( !defined( 'DWPB_URL' ) )
	define( 'DWPB_URL', plugins_url( '/' , __FILE__ ) );

define( 'DWPB_VERSION', '0.1.0' );

/**
 * Main Plugin Class
 *
 * @since 0.1.0
 */
global $_disable_wordpress_blog;
$_disable_wordpress_blog = new Disable_WordPress_Blog;
class Disable_WordPress_Blog {

	/**
	 * Build the class
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		
		// Plugin Base
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}
	
	/**
	 * Make it so!
	 *
	 * @since 0.1.0
	 */
	public function init() {
		
		// Hide Posts Page from Admin Menu
		add_action( 'admin_menu', array( $this, 'remove_menu_pages' ) );
		
		// Disable Feed
		add_action('do_feed', array( $this, 'disable_feed' ), 1);
		add_action('do_feed_rdf', array( $this, 'disable_feed' ), 1);
		add_action('do_feed_rss', array( $this, 'disable_feed' ), 1);
		add_action('do_feed_rss2', array( $this, 'disable_feed' ), 1);
		add_action('do_feed_atom', array( $this, 'disable_feed' ), 1);
		
		// Redirection Admin Page
		add_action( 'admin_init', array( $this, 'redirect_admin_pages' ) );
		
		// Remove Admin Bar Links
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_admin_bar_links' ) );
		
		// Filter Comments off Admin Page
		add_action( 'pre_get_comments', array( $this, 'comment_filter' ), 10, 1 );
		
		// Remove Dashboard Widgets
		add_action( 'admin_init', array( $this, 'remove_dashboard_widgets' ) );
		
		// Hide items with CSS
		add_action( 'admin_head', array( $this, 'admin_styles' ) );
	}
	
	/**
	 * Remove Post Related Menus
	 *
	 * @since 0.1.0
	 * @link http://wordpress.stackexchange.com/questions/57464/remove-posts-from-admin-but-show-a-custom-post
	 */
	public function remove_menu_pages() {
		remove_menu_page( 'edit.php' );
	}
	
	/**
	 * Disable Blog feed
	 *
	 * @since 0.1.0
	 * @link http://wpengineer.com/287/disable-wordpress-feed/
	 */
	public function disable_feed() {
		global $post;
		if( $post->post_type == 'post' )
			wp_die( __('No feed available,please visit our <a href="'. get_bloginfo( 'url' ) .'">homepage</a>!') );
	}
	
	/**
	 * Redirect blog-related admin pages
	 *
	 * @since 0.1.0
	 * @link http://wordpress.stackexchange.com/questions/52114/admin-page-redirect
	 */
	public function redirect_admin_pages(){
	    global $pagenow;
		
		if( !isset( $pagenow ) )
			return;
		
	    // Redirect Edit Post to Edit Page
	    if( $pagenow == 'edit.php' && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) ){
			wp_redirect( admin_url('/edit.php?post_type=page' ), 301 );
			exit;
	    }
		
		// Redirect New Post to New Page
		if( $pagenow == 'post-new.php' && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) ){
			wp_redirect( admin_url('/post-new.php?post_type=page' ), 301 );
			exit;
		}
		
		// Redirect at edit tags screen
		if( $pagenow == 'edit-tags.php' && isset( $_GET['taxonomy'] ) && ( $_GET['taxonomy'] == 'taxonomy' || $_GET['taxonomy'] == 'category' ) ) {
			
			// Make sure this taxonomy is only used on 'post' post type
			$post_only_tax = false;
			$taxonomy = $_GET['taxonomy'];
			$post_types = get_post_types( array(), 'objects' );
			foreach( $post_types as $post_type ) {
				if( $post_type->name == 'post' )
					continue;
				if( in_array( $taxonomy, $post_type->taxonomies ) )
					$post_only_tax = true;
			}
			
			// If this is a post type other than 'post' that supports categories or tags,
			// then bail. Otherwise it is a taxonomy only used by 'post'
			if( ! $post_only_tax ) {
				wp_redirect( admin_url('/index.php' ), 301 );
				exit;
			}
		}
		
		// Redirect posts-only comment queries to comments
		if( $pagenow == 'edit-comments.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ){
			wp_redirect( admin_url('/edit-comments.php' ), 301 );
			exit;
		}
	}
	
	/**
	 * Remove blog-related admin bar links
	 *
	 * @since 0.1.0
	 * @link http://www.paulund.co.uk/how-to-remove-links-from-wordpress-admin-bar
	 */
	public function remove_admin_bar_links() {
	    global $wp_admin_bar;
		// If only posts support comments, then remove comment from admin bar
		if( !$this->post_types_with_comments() )
		    $wp_admin_bar->remove_menu('comments');
		
		// Remove New Post from Content
	    $wp_admin_bar->remove_node( 'new-post' );
	}

	/**
	 * Hide all comments on posts
	 * 
	 * @since 0.1.0
	 * @param  (wp_query object) $query
	 */
	public function comment_filter( $comments ){
	    global $pagenow;
		if( !isset( $pagenow ) )
			return $comments;
		
		// Filter out comments from post
	    if( is_admin() && $pagenow == 'edit-comments.php' ) {
			if( $post_types = $this->post_types_with_comments() ) {
		        $comments->query_vars['post_type'] = $post_types;
			} else {
				// redirect to dashboard?
			}
	    }
		return $comments;
	}
	
	/**
	 * Get all the post types that support comments
	 * 
	 * @since 0.1.0
	 * @return array ( $post_types | bolean )
	 */
	public function post_types_with_comments() {
		$post_types = get_post_types( array(), 'names' );
		
		$post_types_with_comments = array();
		foreach( $post_types as $post_type ) {
			if( post_type_supports( $post_type, 'comments' ) && $post_type != 'post' ) {
				$post_types_with_comments[] = $post_type;
			}
		}
		
		// Return the array if there are any, otherwise false
		if( empty( $post_types_with_comments ) ) {
			return apply_filters( 'dwpb_post_types_supporting_comments', false );
		} else {
			return apply_filters( 'dwpb_post_types_supporting_comments', $post_types_with_comments );
		}
	}
	
	/**
	 * Remove post-related dashboard widgets
	 *
	 * @since 0.1.0
	 * @link http://www.deluxeblogtips.com/2011/01/remove-dashboard-widgets-in-wordpress.html
	 */
	function remove_dashboard_widgets() {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' ); // recent comments
		remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );  // incoming links
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'normal' );  // quick press
		remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'normal' );  // recent drafts
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );  // recent drafts
		
	}
	
	/**
	 * Admin styles
	 * 
	 * Hides post and comment count on activity dashboard widget.
	 * 
	 * @since 0.1.0
	 */
	public function admin_styles() { ?>
		<style>
			#dashboard_right_now .post-count,
			#dashboard_right_now .comment-count {
				display: none;
			}
		</script>
		<?php
	}
}
