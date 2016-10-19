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
	 * Remove comment and pingback support for posts.
	 *
	 * @since 0.4.0
	 */
	public function remove_post_comment_support() {
		if( post_type_supports( 'post', 'comments' ) && apply_filters( 'dwpb_remove_post_comment_support', true ) ) {
			remove_post_type_support( 'post', 'comments' );
		}

		// Remove
		if( post_type_supports( 'post', 'trackbacks' ) && apply_filters( 'dwpb_remove_post_trackback_support', true ) ) {
			remove_post_type_support( 'post', 'trackbacks' );
		}
	}

	/**
	 * Filter the comment counts to remove comments to 'post' post type.
	 *
	 * @since 0.4.0
	 * @since 0.4.3 Moved everything into the post id check and reset the cache.
	 *
	 * @param object $comments
	 * @param int $post_id
	 *
	 * @return array $comments
	 */
	public function filter_wp_count_comments( $comments, $post_id ) {

		// if this is grabbing all the comments, filter out the 'post' comments.
		if( 0 == $post_id ) {
			$comments = $this->get_comment_counts();
			
			$comments['moderated'] = $comments['awaiting_moderation'];
			unset( $comments['awaiting_moderation'] );
			
			$comments = (object) $comments;
			wp_cache_set( "comments-0", $comments, 'counts' );
		}

		return $comments;
	}
	
	/**
	 * Turn the comments object back into an array if WooCommerce is active.
	 *
	 * This is only necessary for version of WooCommerce prior to 2.6.3, where it failed
	 * to check/convert the $comment object into an array.
	 *
	 * @since 0.4.3
	 *
	 * @param object $comments
	 * @param int $post_id
	 *
	 * @return array $comments
	 */
	public function filter_woocommerce_comment_count( $comments, $post_id ) {
		
		if( 0 == $post_id && class_exists( 'WC_Comments' ) && function_exists( 'WC' ) && version_compare( WC()->version, '2.6.2', '<=' ) ) {
			$comments = (array) $comments;
		}
		
		return $comments;
	}

	/**
	 * Alter the comment counts on the admin comment table to remove comments associated with posts.
	 *
	 * @since 0.4.0
	 *
	 * @param array $views
	 *
	 * @return array $views
	 */
	public function filter_admin_table_comment_count( $views ) {
	    global $current_screen;

	    if( 'edit-comments' == $current_screen->id ) {

			$updated_counts = $this->get_comment_counts();
			foreach( $views as $view => $text ) {
				if( isset( $updated_counts[ $view ] ) )
					$views[ $view ] = preg_replace( "/\([^)]+\)/", '(<span class="' . $view . '-count">' . $updated_counts[ $view ] . '</span>)', $views[ $view ] );
			}
	    }
	    return $views;
	}

	/**
	 * Retreive the comment counts without the 'post' comments.
	 *
	 * @since 0.4.0
	 * @since 0.4.3 Removed Unused "count" function.
	 *
	 * @see get_comment_count()
	 *
	 * @return array $comment_counts
	 */
	public function get_comment_counts() {

		global $wpdb;

		// Grab the comments that are not associated with 'post' post_type
	    $totals = (array) $wpdb->get_results("
	        SELECT comment_approved, COUNT( * ) AS total
	        FROM {$wpdb->comments}
		    WHERE comment_post_ID in (
		         SELECT ID
		         FROM {$wpdb->posts}
		         WHERE post_type != 'post'
		         AND post_status = 'publish')
	        GROUP BY comment_approved
	    ", ARRAY_A);

		$comment_count = array(
			'moderated' 		  => 0,
	        'approved'            => 0,
	        'awaiting_moderation' => 0,
	        'spam'                => 0,
	        'trash'               => 0,
	        'post-trashed'        => 0,
	        'total_comments'      => 0,
	        'all'                 => 0,
		);

	    foreach ( $totals as $row ) {
	        switch ( $row['comment_approved'] ) {
	            case 'trash':
	                $comment_count['trash'] = $row['total'];
	                break;
	            case 'post-trashed':
	                $comment_count['post-trashed'] = $row['total'];
	                break;
	            case 'spam':
	                $comment_count['spam'] = $row['total'];
	                $comment_count['total_comments'] += $row['total'];
	                break;
	            case '1':
	                $comment_count['approved'] = $row['total'];
	                $comment_count['total_comments'] += $row['total'];
	                $comment_count['all'] += $row['total'];
	                break;
	            case '0':
	                $comment_count['awaiting_moderation'] = $row['total'];
					$comment_count['moderated'] = $comment_count['awaiting_moderation'];
	                $comment_count['total_comments'] += $row['total'];
	                $comment_count['all'] += $row['total'];
	                break;
	            default:
	                break;
	        }
	    }

		return $comment_count;
	}

	/**
	 * Clear out the status of all post comments.
	 *
	 * @since 0.4.0
	 *
	 * @param boolean $open
	 * @param int $post_id
	 *
	 * @return boolean
	 */
	public function filter_comment_status( $open, $post_id ) {
		$post_type = get_post_type( $post_id );
		return ( 'post' == $post_type ) ? false : $open;
	}

	/**
	 * Clear comments from 'post' post type.
	 *
	 * @since 0.4.0
	 *
	 * @param boolean $comments
	 * @param string $post_id
	 *
	 * @return boolean
	 */
	public function filter_existing_comments( $comments, $post_id ) {
		$post_type = get_post_type( $post_id );
		return ( 'post' == $post_type ) ? array() : $comments;
	}

	/**
	 * Remove the X-Pingback HTTP header.
	 *
	 * @since 0.4.0
	 *
	 * @param array $headers
	 *
	 * @return array $headers
	 */
	public function filter_wp_headers( $headers ) {
		if( apply_filters( 'dwpb_remove_pingback_header', true ) && isset( $headers['X-Pingback'] ) )
			unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Remove Post Related Menus
	 *
	 * @uses dwpb_post_types_with_tax()
	 * @uses dwpb_post_types_with_feature()
	 *
	 * @link http://wordpress.stackexchange.com/questions/57464/remove-posts-from-admin-but-show-a-custom-post
	 *
	 * @since 0.1.0
	 * @since 0.4.0 added tools and discussion subpages
	 */
	public function remove_menu_pages() {

		// Remove Top Level Menu Pages
		$pages = apply_filters( 'dwpb_menu_pages_to_remove', array( 'edit.php' ) );
		foreach( $pages as $page ) {
			remove_menu_page( $page );
		}

		// Submenu Pages
		$remove_subpages = array(
			'options-general.php' => 'options-writing.php',
			'tools.php' => 'tools.php',
		);

		// If there are no other post types supporting comments, remove the discussion page
		if( ! dwpb_post_types_with_feature( 'comments' ) ) {
			$remove_subpages[ 'options-general.php' ] = 'options-discussion.php'; // Settings > Discussion
		}

		// Remove Admin Menu Subpages
		$subpages = apply_filters( 'dwpb_menu_subpages_to_remove', $remove_subpages );
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
	 * @since 0.4.0 added single post edit screen redirect
	 */
	public function redirect_admin_pages() {
		global $pagenow;

		if( !isset( $pagenow ) ) {
			return;
		}

		// setup false redirect url value for final check
		$redirect_url = false;

		//Redirect Edit Single Post to Dashboard.
		if( 'post.php' == $pagenow && ( isset( $_GET['post'] ) && 'post' == get_post_type( $_GET['post'] ) ) && apply_filters( 'dwpb_redirect_admin_edit_single_post', true ) ) {
			$url = admin_url( '/index.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_single_post_edit', $url );
		}

		// Redirect Edit Posts Screen to Edit Page
		if( 'edit.php' == $pagenow && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) && apply_filters( 'dwpb_redirect_admin_edit_post', true ) ) {
			$url = admin_url( '/edit.php?post_type=page' );
			$redirect_url = apply_filters( 'dwpb_redirect_edit', $url );
		}

		// Redirect New Post to New Page
		if( 'post-new.php' == $pagenow && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) && apply_filters( 'dwpb_redirect_admin_post_new', true ) ) {
			$url = admin_url('/post-new.php?post_type=page' );
			$redirect_url = apply_filters( 'dwpb_redirect_post_new', $url );
		}

		// Redirect at edit tags screen
		// If this is a post type other than 'post' that supports categories or tags,
		// then bail. Otherwise if it is a taxonomy only used by 'post'
		// or if this is either the edit-tags page and a taxonomy is not set
		// and the built-in default 'post_tags' is not supported by other post types
		// then redirect!
		if( ( 'edit-tags.php' == $pagenow || 'term.php' == $pagenow ) && ( isset( $_GET['taxonomy'] ) && ! dwpb_post_types_with_tax( $_GET['taxonomy'] ) ) && apply_filters( 'dwpb_redirect_admin_edit_tags', true ) ) {
			$url = admin_url( '/index.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_edit_tax', $url );
		}

		// Redirect posts-only comment queries to comments
		if( 'edit-comments.php' == $pagenow && isset( $_GET['post_type'] ) && 'post' == $_GET['post_type'] && apply_filters( 'dwpb_redirect_admin_edit_comments', true ) ) {
			$url = admin_url( '/edit-comments.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_edit_comments', $url );
		}

		// Redirect disccusion options page if only supported by 'post' type
		if( 'options-discussion.php' == $pagenow && ! dwpb_post_types_with_feature( 'comments' ) && apply_filters( 'dwpb_redirect_admin_options_discussion', true ) ) {
			$url = admin_url( '/index.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_options_discussion', $url );
		}

		// Redirect writing options to general options
		if( 'options-writing.php' == $pagenow && apply_filters( 'dwpb_redirect_admin_options_writing', true ) ) {
			$url = admin_url( '/options-general.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_options_writing', $url );
		}

		// Redirect available tools page
		if( 'tools.php' == $pagenow && !isset( $_GET['page'] ) && apply_filters( 'dwpb_redirect_admin_options_writing', true ) ) {
		 	$url = admin_url( '/index.php' );
		 	$redirect_url = apply_filters( 'dwpb_redirect_options_writing', $url );
		}

		// If we have a redirect url, do it
		if( $redirect_url ) {
			wp_redirect( esc_url_raw( $redirect_url ), 301 );
			exit;
		}
	}

	/**
	 * Remove blog-related admin bar links
	 *
	 * @uses dwpb_post_types_with_feature()
	 *
	 * @link http://www.paulund.co.uk/how-to-remove-links-from-wordpress-admin-bar
	 *
	 * @since 0.1.0
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
	public function comment_filter( $comments ) {
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
	 *
	 */
	public function reading_settings() {
		if( 'posts' == get_option( 'show_on_front' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_for_posts', apply_filters( 'dwpb_page_for_posts', 0 ) );
			update_option( 'page_on_front', apply_filters( 'dwpb_page_on_front', 1 ) );
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
	 * @since 0.4.0 simplified unregistering and added dwpb_unregister_widgets filter.
	 */
	public function remove_widgets() {

		// Unregister widgets that don't require a check
        $widgets = array(
			'WP_Widget_Recent_Comments', // Recent Comments
			'WP_Widget_Tag_Cloud', // Tag Cloud
			'WP_Widget_Categories', // Categories
            'WP_Widget_Archives', // Archives
            'WP_Widget_Calendar', // Calendar
            'WP_Widget_Links', // Links
            'WP_Widget_Recent_Posts', // Recent Posts
            'WP_Widget_RSS', // RSS
            'WP_Widget_Tag_Cloud' // Tag Cloud
        );
        foreach( $widgets as $widget ) {
			if( apply_filters( "dwpb_unregister_widgets", true, $widget ) )
	            unregister_widget( $widget );
        }

	}

	/**
	 * Filter the widget removal & check for reasons to not remove specific widgets.
	 *
	 * @since 0.4.0
	 *
	 * @param boolean $boolean
	 * @param string $widget
	 *
	 * @return boolean
	 */
	public function filter_widget_removal( $boolean, $widget ) {

		// Remove Categories Widget
		if( 'WP_Widget_Categories' == $widget && dwpb_post_types_with_tax( 'category' ) )
			$boolean = false;

		// Remove Recent Comments Widget if posts are the only type with comments
		if( 'WP_Widget_Recent_Comments' == $widget && dwpb_post_types_with_feature( 'comments' ) )
			$boolean = false;

		// Remove Tag Cloud
		if( 'WP_Widget_Tag_Cloud' == $widget && dwpb_post_types_with_tax( 'post_tag' ) )
			$boolean = false;

		return $boolean;
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
}
