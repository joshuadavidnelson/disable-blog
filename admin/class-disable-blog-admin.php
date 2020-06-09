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
	 *
	 * @param	string    $plugin_name       The name of this plugin.
	 * @param	string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}
	
	/**
	 * Check that we're on a specific admin page.
	 * 
	 * @since 0.4.8
	 *
	 * @param  string  $page
	 * 
	 * @return boolean
	 */
	private function is_admin_page( $page ) {

		global $pagenow;
		return ( is_admin() && isset( $pagenow ) && is_string( $pagenow ) && $page . '.php' == $pagenow );

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
	 * @param bool $open
	 * @param int $post_id
	 *
	 * @return bool
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
	 * @param bool $comments
	 * @param int $post_id
	 *
	 * @return bool
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

        /**
         * Toggle the disable pinback header feature.
         * 
         * @since 0.4.0
         * 
         * @param bool $bool True to disable the header, false to keep it.
         */
		if ( apply_filters( 'dwpb_remove_pingback_header', true ) && isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}

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

        /**
         * Top level admin pages to remove.
         * 
         * @since 0.4.0
         * 
         * @param array $remove_subpages Array of page => subpage.
         */
		$pages = apply_filters( 'dwpb_menu_pages_to_remove', array( 'edit.php' ) );
		foreach( $pages as $page ) {
			remove_menu_page( $page );
		}

		// Submenu Pages
		$remove_subpages = array(
			'tools.php' => 'tools.php',
		);
		
		// Remove the writings page, if the filter tells us so.
		if( $this->remove_writing_options() )
			$remove_subpages['options-general.php'] = 'options-writing.php';

		// If there are no other post types supporting comments, remove the discussion page
		if( ! dwpb_post_types_with_feature( 'comments' ) ) {
			$remove_subpages[ 'options-general.php' ] = 'options-discussion.php'; // Settings > Discussion
		}

		/**
		 * Admin subpages to be removed.
		 * 
		 * @since 0.4.0
		 * 
		 * @param array $remove_subpages Array of page => subpage.
		 */
		$subpages = apply_filters( 'dwpb_menu_subpages_to_remove', $remove_subpages );
		foreach( $subpages as $page => $subpage ) {
			remove_submenu_page( $page, $subpage );
		}

    }
    
    /**
     * Filter the body classes for admin screens to toggle on plugin specific styles.
     *
     * @param array $classes
     * 
     * @return array
     */
    function admin_body_class( $classes ) {

        if( $this->has_front_page() )
            $classes .= ' disabled-blog';

        return $classes;

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

		if( ! isset( $pagenow ) ) {
			return;
		}

		$screen = get_current_screen();

		// on multisite: Do not redirect if we are on a network page
		if( is_multisite() && is_callable( array( $screen, 'in_admin' ) ) && $screen->in_admin('network') ) {
            return;
        }

        // setup false redirect url value for final check
        $redirect_url = false;
        
        // Redirect Edit Single Post to Dashboard.
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
        if( 'options-writing.php' == $pagenow && $this->remove_writing_options() ) {
            $url = admin_url( '/options-general.php' );
            $redirect_url = apply_filters( 'dwpb_redirect_options_writing', $url );
        }

        // Redirect available tools page
        if( 'tools.php' == $pagenow && !isset( $_GET['page'] ) && apply_filters( 'dwpb_redirect_admin_options_tools', true ) ) {
            $url = admin_url( '/index.php' );
            $redirect_url = apply_filters( 'dwpb_redirect_options_tools', $url );
        }

        // If we have a redirect url, do it
        if( $redirect_url ) {
            wp_safe_redirect( esc_url_raw( $redirect_url ), 301 );
            exit;
        }
		
	}
	
	/**
	 * Filter for removing the writing options page.
	 *
	 * @since 0.4.5
	 */
	function remove_writing_options() {

        /**
         * Toggle the options-writing page redirect.
         * 
         * @since 0.4.5
         * 
         * @param bool $bool Defaults to false, keeping the writing page visible.
         */
		return apply_filters( 'dwpb_redirect_admin_options_writing', false );
		
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
	 * @since 0.4.8 updated use with is_admin_page function
	 *
	 * @param  (wp_query object) $comments
	 */
	public function comment_filter( $comments ) {

		// Filter out comments from post
		if( $this->is_admin_page( 'edit-comments' )
			&& ( $post_types = dwpb_post_types_with_feature( 'comments' ) ) ) {
				$comments->query_vars['post_type'] = $post_types;
		}

		return $comments;
		
	}

	/**
	 * Remove post-related dashboard widgets
	 *
	 * @uses dwpb_post_types_with_feature()
	 *
	 * @since 0.1.0
	 * @since 0.4.1 dry out the code with a foreach loop
	 */
	function remove_dashboard_widgets() {

		// Remove post-specific widgets only, others obscured/modified elsewhere as necessary 
		$metabox = array(
			'dashboard_quick_press' => 'side', // Quick Press
			'dashboard_recent_drafts' => 'side', // Recent Drafts
			'dashboard_incoming_links' => 'normal', // Incoming Links
			'dashboard_activity' => 'normal' // Activity
		);
		foreach ( $metabox as $metabox_id => $context ) {

			/**
			 * Filter to change the dashboard widgets beinre removed.
			 *
			 * Filter name baed on the name of the widget above,
			 * For instance: `dwpb_disable_dashboard_quick_press` for the Quick Press widget.
			 *
			 * @since 0.4.1
			 *
			 * @param bool $bool True to remove the dashboard widget.
			 */
			if ( apply_filters( "dwpb_disable_{$metabox_id}", true ) ) {
				remove_meta_box( $metabox_id, 'dashboard', $context );
			}

		}

	}

	/**
	 * Throw an admin notice if settings are misconfigured.
	 *
	 * If there is not a homepage correctly set, then redirects don't work.
	 * The intention with this notice is to highlight what is needed for the plugin to function properly.
	 *
	 * @since 0.2.0 
	 * @since 0.4.7 Changed this to an error notice function.
	 */
	public function admin_notices() {

		// only throwing this notice in the edit.php, plugins.php, and options-reading.php admin pages
		$current_screen = get_current_screen();
		$screens = array(
			'plugins',
			'options-reading',
			'edit',
		);
		if( ! ( isset( $current_screen->base ) && in_array( $current_screen->base, $screens ) ) )
			return;

		// Throw a notice if the we don't have a front page
		if( ! $this->has_front_page() ) {

			// The second part of the notice depends on which screen we're on.
			if( 'options-reading' == $current_screen->base ) {

				// translators: Direct the user to set a homepage in the current screen.
				$message_link = ' ' . __( 'Select a page for your homepage below.', 'disable-blog' );

			// If we're not on the Reading Options page, then direct the user there
			} else {

				// translators: Direct the user to the Reading Settings admin page.
				$reading_options_page = get_admin_url( null, 'options-reading.php' );
				$message_link = ' ' . sprintf( __( 'Change in <a href="%s">Reading Settings</a>.', 'disable-blog' ), $reading_options_page );

			}

			// translators: Prompt to configure the site for static homepage and posts page.
			$message = __( 'Disable Blog is not fully active until a static page is selected for the site\'s homepage.', 'disable-blog' ) . $message_link;

			printf( '<div class="%s"><p>%s</p></div>', 'notice notice-error', $message );

		// If we have a front page set, but no posts page or they are the same
		// Then let the user know the expected behavior of these two.
		} elseif( 'options-reading' == $current_screen->base 
					&& ( ! get_option( 'page_for_posts' ) || get_option( 'page_for_posts' ) == get_option( 'page_on_front' ) ) ) {

			// translators: Tell the user the plugin needs a static homepage and the posts page will be redirected.
			$message = __( 'Disable Blog requires a static homepage and will redirect the "posts page" to the homepage.', 'disable-blog' );

			printf( '<div class="%s"><p>%s</p></div>', 'notice notice-warning', $message );

		}

	}

	/**
	 * Check that the site has a front page set in the Settings > Reading.
	 * 
	 * @since 0.4.7
	 *
	 * @return bool
	 */
	function has_front_page() {

		return ( 'page' == get_option( 'show_on_front' ) && absint( get_option( 'page_on_front' ) ) );

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

		// Unregister blog-related widgets
		$widgets = array(
			'WP_Widget_Recent_Comments', // Recent Comments
			'WP_Widget_Tag_Cloud', // Tag Cloud
			'WP_Widget_Categories', // Categories
			'WP_Widget_Archives', // Archives
			'WP_Widget_Calendar', // Calendar
			'WP_Widget_Links', // Links
			'WP_Widget_Recent_Posts', // Recent Posts
			'WP_Widget_RSS', // RSS
			'WP_Widget_Tag_Cloud', // Tag Cloud
		);
		foreach ( $widgets as $widget ) {

			/**
			 * The ability to stop the widget unregsiter.
			 *
			 * @since 0.4.0
			 *
			 * @param bool   $bool   True to unregister the widget.
			 * @param string $widget The name of the widget to be unregistered.
			 */
			if ( apply_filters( 'dwpb_unregister_widgets', true, $widget ) ) {
				unregister_widget( $widget );
			}

		}

	}

	/**
	 * Filter the widget removal & check for reasons to not remove specific widgets.
	 * 
	 * If there are post types using the comments or built-in taxonomies outside of the default 'post'
	 *    then we stop the plugin from removing the widget.
	 *
	 * @since 0.4.0
	 * @since 0.4.8 added check on RSS feed support for the RSS widget.
	 *
	 * @param bool $bool
	 * @param string $widget
	 *
	 * @return bool
	 */
	public function filter_widget_removal( $bool, $widget ) {

		// Remove Categories Widget
		if( 'WP_Widget_Categories' == $widget && dwpb_post_types_with_tax( 'category' ) )
			$bool = false;

		// Remove Recent Comments Widget if posts are the only type with comments
		if( 'WP_Widget_Recent_Comments' == $widget && dwpb_post_types_with_feature( 'comments' ) )
			$bool = false;

		// Remove Tag Cloud unless there are other post types using it
		if( 'WP_Widget_Tag_Cloud' == $widget && dwpb_post_types_with_tax( 'post_tag' ) )
			$bool = false;

		return $bool;
		
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 0.4.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/disable-blog-admin.css', array(), $this->version, 'all' );

	}

}