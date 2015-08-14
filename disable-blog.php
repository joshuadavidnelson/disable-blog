<?php
/**
 * Plugin Name: Disable Blog
 * Plugin URI: http://joshuadnelson.com
 * Description: A plugin that disables or hides all blog-related elements of your WordPress site.
 * Version: 0.3.1
 * Author: Joshua Nelson
 * Author URI: http://joshuadnelson.com
 * GitHub Plugin URI: https://github.com/joshuadavidnelson/disable-wordpress-blog
 * GitHub Branch: master
 * License: GPL v2.0
 *
 * @package 	Disable_Blog
 * @category 	Core
 * @author 		Joshua David Nelson
 * @version 	0.3.1
 * @license 	http://www.gnu.org/licenses/gpl-2.0.html GPLv2.0+
 */

/**
 * Exit if accessed directly.
 *
 * Prevent direct access to this file. 
 *
 * @since 0.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Plugin Class
 *
 * @since 0.1.0
 */
if ( ! class_exists( 'Disable_Blog' ) ) {
	class Disable_Blog {
		/** Singleton */

		/**
		 * @var Disable_Blog The one true Disable_Blog
		 * @since 0.3.0
		 */
		private static $instance;

		/**
		 * Main Disable_Blog Instance
		 *
		 * Insures that only one instance of Disable_Blog exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 0.3.0
		 * @static
		 * @staticvar array $instance
		 * @uses Disable_Blog::setup_constants() Setup the constants needed
		 * @uses Disable_Blog::includes() Include the required files
		 * @uses Disable_Blog::load_textdomain() load the language files
		 * @see EEC()
		 * @return The one true Disable_Blog
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Disable_Blog ) ) {
				self::$instance = new Disable_Blog;
				self::$instance->setup_constants();
				self::$instance->init();
				
			}
			return self::$instance;
		}

		/**
		 * Throw error on object clone
		 *
		 * The whole idea of the singleton design pattern is that there is a single
		 * object therefore, we don't want the object to be cloned.
		 *
		 * @since 0.3.0
		 * @access protected
		 * @return void
		 */
		public function __clone() {
			// Cloning instances of the class is forbidden
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'eec' ), '0.3.0' );
		}

		/**
		 * Disable unserializing of the class
		 *
		 * @since 0.3.0
		 * @access protected
		 * @return void
		 */
		public function __wakeup() {
			// Unserializing instances of the class is forbidden
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'eec' ), '0.3.0' );
		}
	
		/**
		 * Define Constants
		 *
		 * @since 0.3.0
		 */
		private function setup_constants() {
			// For includes and whatnot
			if( !defined( 'DWPB_DIR' ) )
				define( 'DWPB_DIR', dirname( __FILE__ ) );

			// For calling scripts and so forth
			if( !defined( 'DWPB_URL' ) )
				define( 'DWPB_URL', plugins_url( '/' , __FILE__ ) );

			// For internationalization
			if( !defined( 'DWPB_DOMAIN' ) )
				define( 'DWPB_DOMAIN', 'disable-wordpress-blog' );
	
			// To keep track of versions, useful if you need to make updates specific to versions
			define( 'DWPB_VERSION', '0.3.1' );
		}
		
		
		/**
		 * Make it so!
		 *
		 * @since 0.1.0
		 */
		public function init() {
		
			// Hooks are useful, here's one
			do_action( 'dwpb_init' );
		
			// Plugin Links
			add_filter( 'plugin_row_meta', array( $this, 'plugin_links' ), 10, 2 );
		
			// Hide Posts Page from Admin Menu
			add_action( 'admin_menu', array( $this, 'remove_menu_pages' ) );
		
			// Disable Feed
			add_action( 'do_feed', array( $this, 'disable_feed' ), 1 );
			add_action( 'do_feed_rdf', array( $this, 'disable_feed' ), 1 );
			add_action( 'do_feed_rss', array( $this, 'disable_feed' ), 1 );
			add_action( 'do_feed_rss2', array( $this, 'disable_feed' ), 1 );
			add_action( 'do_feed_atom', array( $this, 'disable_feed' ), 1 );
		
			// Redirect Admin Page
			add_action( 'admin_init', array( $this, 'redirect_admin_pages' ) );
		
			// Redirect Single Posts
			add_action( 'template_redirect', array( $this, 'redirect_posts' ) );
		
			// Modify Query
			add_action( 'pre_get_posts', array( $this, 'modify_query' ) );
		
			// Remove Admin Bar Links
			add_action( 'wp_before_admin_bar_render', array( $this, 'remove_admin_bar_links' ) );
		
			// Filter Comments off Admin Page
			add_action( 'pre_get_comments', array( $this, 'comment_filter' ), 10, 1 );
		
			// Remove Dashboard Widgets
			add_action( 'admin_init', array( $this, 'remove_dashboard_widgets' ) );
		
			// Hide items with CSS
			add_action( 'admin_head', array( $this, 'admin_styles' ) );
		
			// Force Reading Settings
			add_action( 'admin_init', array( $this, 'reading_settings' ) );
		
			// Remove Post via Email Settings
			add_filter( 'enable_post_by_email_configuration', '__return_false' );
		
			// Disable Press This Function
			add_action( 'load-press-this.php', array( $this, 'disable_press_this' ) );
		
			// Remove Post Related Widgets
			add_action( 'widgets_init', array( $this, 'remove_widgets' ) );
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
		 * Disable Blog feed
		 *
		 * @since 0.1.0
		 */
		public function disable_feed() {
			global $post;
			if( apply_filters( 'dwpb_disable_feed', true, $post ) ) {
				if( $post->post_type == 'post' ) {
					$url = home_url();
					if( apply_filters( 'dwpb_feed_message', false, $post ) ) {
						$message = apply_filters( 'dwpb_feed_die_message', __( 'No feed available, please visit our <a href="'. $url .'">homepage</a>!', DWPB_DOMAIN ) );
						wp_die( $message );
					} else {
						$redirect_url = apply_filters( 'dwpb_redirect_feeds', $url );
						wp_redirect( $redirect_url, 301 );
					}
				}
			}
		}
	
		/**
		 * Redirect blog-related admin pages
		 *
		 * @since 0.1.0
		 */
		public function redirect_admin_pages() {
			global $pagenow;
		
			if( !isset( $pagenow ) ) {
				return;
			}
		
			// Redirect Edit Post to Edit Page
			if( $pagenow == 'edit.php' && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) && apply_filters( 'dwpb_redirect_admin_edit_post', true ) ) {
				$url = admin_url( '/edit.php?post_type=page' );
				$redirect_url = apply_filters( 'dwpb_redirect_edit', $url );
				wp_redirect( $redirect_url, 301 );
				exit;
			}
		
			// Redirect New Post to New Page
			if( $pagenow == 'post-new.php' && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) && apply_filters( 'dwpb_redirect_admin_post_new', true ) ) {
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
			if( $pagenow == 'edit-tags.php' && ( isset( $_GET['taxonomy'] ) && ! $this->post_types_with_tax( $_GET['taxonomy'] ) ) && apply_filters( 'dwpb_redirect_admin_edit_tags', true ) ) {
				$url = admin_url( '/index.php' );
				$redirect_url = apply_filters( 'dwpb_redirect_edit_tax', $url );
				wp_redirect( $redirect_url, 301 );
				exit;
			} 
		
			// Redirect posts-only comment queries to comments
			if( $pagenow == 'edit-comments.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' && apply_filters( 'dwpb_redirect_admin_edit_comments', true ) ) {
				$url = admin_url( '/edit-comments.php' );
				$redirect_url = apply_filters( 'dwpb_redirect_edit_comments', $url );
				wp_redirect( $redirect_url, 301 );
				exit;
			}
		
			// Redirect writing options to general options
			if( $pagenow == 'options-writing.php' && apply_filters( 'dwpb_redirect_admin_options_writing', true ) ) {
				$url = admin_url( '/options-general.php' );
				$redirect_url = apply_filters( 'dwpb_redirect_options_writing', $url );
				wp_redirect( $redirect_url, 301 );
				exit;
			}
		}
	
		/**
		 * Redirect single post pages
		 * 
		 * @since 0.2.0
		 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect
		 */
		public function redirect_posts() {
			if( is_admin() || !get_option( 'page_on_front' ) )
				return;
			
			$page_id = get_option( 'page_on_front' );
			$url = get_permalink( $page_id );
			if( is_singular( 'post' ) ) {
			
				global $post;
				$redirect_url = apply_filters( "dwpb_redirect_posts", $url, $post );
				$redirect_url = apply_filters( "dwpb_redirect_post_{$post->ID}", $redirect_url, $post );
			
			} elseif( is_tag() && ! $this->post_types_with_tax( 'post_tag' ) ) {
			
				$redirect_url = apply_filters( 'dwpb_redirect_post_tag_archive', $url );
			
			} elseif( is_category() && ! $this->post_types_with_tax( 'category' ) ) {
			
				$redirect_url = apply_filters( 'dwpb_redirect_category_archive', $url );
			
			} elseif( is_post_type_archive( 'post' ) ) {
			
				$redirect_url = apply_filters( 'dwpb_redirect_post_archive', $url );
			
			} elseif( is_home() ) {
				
				$redirect_url = apply_filters( 'dwpb_redirect_blog_page', $url );
			
			} elseif( is_date() ) {
			
				$redirect_url = apply_filters( 'dwpb_redirect_date_archive', $url );
			
			}
			
			// TODO: create a check to verify the redirect url does not match the current page, if it does, then bounce
			
			if( isset( $redirect_url ) && apply_filters( 'dwpb_redirect_front_end', true, $redirect_url ) ) {
				wp_redirect( esc_url( $redirect_url ), 301 );
				exit();
			}
		}
	
		/**
		 * Modify query
		 * 
		 * @since 0.2.0
		 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect
		 * @link http://stackoverflow.com/questions/7225070/php-array-delete-by-value-not-key#7225113
		 */
		public function modify_query( $query ) {
			if( is_admin() || ! $query->is_main_query() )
				return;
		
			// Remove 'post' post_type from search results, replace with page
			if( $query->is_search() ) {
				$in_search_post_types = get_post_types( array( 'exclude_from_search' => false ) );
				if( is_array( $in_search_post_types ) && in_array( 'post', $in_search_post_types ) ) {
					unset( $in_search_post_types[ 'post' ] );
					$set_to = apply_filters( 'dwpb_search_post_types', $in_search_post_types, $query );
					if( ! empty( $set_to ) ) {
						$query->set( 'post_type', $set_to );
					}
				}
			}
		
			// Remove Posts from Author Page
			if( $query->is_author() ) {
				$author_post_types = get_post_types( array( 'publicly_queryable' => true, 'exclude_from_search' => false ) );
				if( is_array( $author_post_types ) && in_array( 'post', $author_post_types ) ) {
					unset( $author_post_types[ 'post' ] );
					$set_to = apply_filters( 'dwpb_author_post_types', $author_post_types, $query );
					if( ! empty( $set_to ) ) {
						$query->set( 'post_type', $set_to );
					}
				}
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
			if( ! $this->post_types_with_feature( 'comments' ) )
			    $wp_admin_bar->remove_menu( 'comments' );
		
			// Remove New Post from Content
			$wp_admin_bar->remove_node( 'new-post' );
		}

		/**
		 * Hide all comments from 'post' post type
		 * 
		 * @since 0.1.0
		 * @param  (wp_query object) $comments
		 */
		public function comment_filter( $comments ){
			global $pagenow;
		
			if( !isset( $pagenow ) )
				return $comments;
		
			// Filter out comments from post
			if( is_admin() && $pagenow == 'edit-comments.php' ) {
				if( $post_types = $this->post_types_with_feature( 'comments' ) ) {
					$comments->query_vars['post_type'] = $post_types;
				}
			}
		
			return $comments;
		}
	
		/**
		 * Remove post-related dashboard widgets
		 *
		 * @since 0.1.0
		 */
		function remove_dashboard_widgets() {
			// recent comments
			if( apply_filters( 'dwpb_disable_dashboard_recent_comments', true ) && ! $this->post_types_with_feature( 'comments' ) )
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
		 * Admin styles
		 * 
		 * Hides post and comment count on activity dashboard widget.
		 * 
		 * @since 0.1.0
		 */
		public function admin_styles() { ?>
			<style>
				#dashboard_right_now .post-count,
				#dashboard_right_now .comment-count,
				.nav-menus-php label[for="add-post-hide"],
				.control-section.add-post,
				.options-reading-php table.form-table tr,
				.welcome-icon.welcome-write-blog,
				.users-php .column-posts {
				    display: none;
				}
				<?php if( ! $this->post_types_with_feature( 'comments' ) ) { echo 'a.welcome-icon.welcome-comments {display: none;}'; } ?>
				.options-reading-php table.form-table tr:first-child,
				.options-reading-php table.form-table tr.option-site-visibility {
					display: block;
				}
			</style>
			<?php
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
		 * Remove post related widgets
		 * 
		 * @since 0.2.0
		 */
		public function remove_widgets() {
			// Remove Recent Posts
			unregister_widget( 'WP_Widget_Recent_Posts' );
		
			// Remove Categories Widget
			if( ! $this->post_types_with_tax( 'category' ) )
				unregister_widget( 'WP_Widget_Categories' );
		
			// Remove Recent Comments Widget if posts are the only type with comments
			if( ! $this->post_types_with_feature( 'comments' ) )
				unregister_widget( 'WP_Widget_Recent_Comments' );
		
			// Remove Tag Cloud
			if( ! $this->post_types_with_tax( 'post_tag' ) )
				unregister_widget( 'WP_Widget_Tag_Cloud' );
		
			// Remove RSS Widget
			unregister_widget( 'WP_Widget_RSS' );
		
			// Remove Archive Widget
			unregister_widget( 'WP_Widget_Archives' );
		
			// Remove Calendar Widget
			unregister_widget( 'WP_Widget_Calendar' );
		}
	
		/**
		 * Get all the post types that support a featured (like 'comments')
		 * 
		 * @since 0.1.0
		 * @return array ( $post_types | bolean )
		 * @link http://codex.wordpress.org/Function_Reference/register_post_type#Arguments
		 */
		public function post_types_with_feature( $feature ) {
			$post_types = get_post_types( array(), 'names' );
		
			$post_types_with_feature = array();
			foreach( $post_types as $post_type ) {
				if( post_type_supports( $post_type, $feature ) && $post_type != 'post' ) {
					$post_types_with_feature[] = $post_type;
				}
			}
		
			// Return the array if there are any, otherwise false
			if( empty( $post_types_with_feature ) ) {
				return apply_filters( "dwpb_post_types_supporting_{$feature}", false );
			} else {
				return apply_filters( "dwpb_post_types_supporting_{$feature}", $post_types_with_feature );
			}
		}
	
		/**
		 * Get post types that have a specific taxonomy
		 *  (a combination of get_post_types and get_object_taxonomies)
		 *
		 * Basically, we need to know if there are post types, other than 'post'
		 * that support the taxonomy.
		 * 
		 * @since 0.2.0
		 * 
		 * @see register_post_types(), get_post_types(), get_object_taxonomies()
		 * @uses get_post_types(), get_object_taxonomies(), apply_filters()
		 * 
		 * @param string $taxonomy Required. The name of the feature to check against post type support.
		 * @param array | string $args Optional. An array of key => value arguments to match against the post type objects. Default empty array.
		 * @param string $output Optional. The type of output to return. Accepts post type 'names' or 'objects'. Default 'names'.
		 * 
		 * @return array | boolean	A list of post type names or objects that have the taxonomy or false if nothing found.
		 */
		public function post_types_with_tax( $taxonomy, $args = array(), $output = 'names' ) {
			$post_types = get_post_types( $args, $output );
	
			// We just need the taxonomy name
			if( is_object( $taxonomy ) ){
				$taxonomy = $taxonomy->name;
		
			// If it's not an object or a string, it won't work, so send it back
			} elseif( !is_string( $taxonomy ) ) {
				return false;
			}
	
			// setup the finished product
			$post_types_with_tax = array();
			foreach( $post_types as $post_type ) {
				// If post types are objects
				if( is_object( $post_type ) ) {
					$type = $post_type->name;
				// If post types are strings
				} elseif( is_string( $post_type ) ) {
					$type = $post_type;
				} else {
					$type = '';
				}
				
				// is the post included in this post type, but not 'post' type.
				if( !empty( $type ) && $type != 'post' ) {
					$taxonomies = get_object_taxonomies( $type, 'names' );
					if( in_array( $taxonomy, $taxonomies ) ) {
						$post_types_with_tax[] = $post_type;
					}
				}
			}
		
			// Ability to override the results
			$override = apply_filters( 'dwpb_taxonomy_support', null, $taxonomy, $post_types, $args, $output );
			if( ! is_null( $override ) ) {
				return $override;
			}
	
			// If there aren't any results, return false
			if( empty( $post_types_with_tax ) ) {
				return false;
			} else {
				return $post_types_with_tax;
			}
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
}

/**
 * The main function responsible for returning the one true Disable_Blog
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $dwpb = DWPB(); ?>
 *
 * @since 0.3.0
 * @return object The one true Disable_Blog Instance
 */
function DWPB() {
	return Disable_Blog::instance();
}

// Get DWPB Running
DWPB();
