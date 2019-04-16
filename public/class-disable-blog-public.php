<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      1.0.0
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog/public
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */
class Disable_Blog_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}
	
	/**
	 * Redirect single post pages
	 *
	 * @uses dwpb_post_types_with_tax()
	 * 
	 * @since 0.2.0
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect
	 */
	public function redirect_posts() {
		if( is_admin() || !get_option( 'page_on_front' ) )
			return;
		
		// Get the front page id and url
		$page_id = get_option( 'page_on_front' );
		$url = get_permalink( $page_id );
		
		// Run the redirects
		if( is_singular( 'post' ) ) {
		
			global $post;
			$redirect_url = apply_filters( "dwpb_redirect_posts", $url, $post );
			$redirect_url = apply_filters( "dwpb_redirect_post_{$post->ID}", $redirect_url, $post );
		
		} elseif( is_tag() && ! dwpb_post_types_with_tax( 'post_tag' ) ) {
		
			$redirect_url = apply_filters( 'dwpb_redirect_post_tag_archive', $url );
		
		} elseif( is_category() && ! dwpb_post_types_with_tax( 'category' ) ) {
		
			$redirect_url = apply_filters( 'dwpb_redirect_category_archive', $url );
		
		} elseif( is_post_type_archive( 'post' ) ) {
		
			$redirect_url = apply_filters( 'dwpb_redirect_post_archive', $url );
		
		} elseif( is_home() ) {
			
			$redirect_url = apply_filters( 'dwpb_redirect_blog_page', $url );
		
		} elseif( is_date() ) {
		
			$redirect_url = apply_filters( 'dwpb_redirect_date_archive', $url );
		
		} else {
			
			$redirect_url = false;
				
		}
		
		// Get the current url and compare to the redirect, if they are the same, bail to avoid a loop
		// If there is no redirect url, then also bail.
		$protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';
		$curent_url = esc_url( $protocol . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] );
		if( $redirect_url == $curent_url || ! $redirect_url )
			return;
		
		if( apply_filters( 'dwpb_redirect_front_end', true, $redirect_url ) ) {
			wp_redirect( esc_url( $redirect_url ), 301 );
			exit();
		}
	}
	
	/**
	 * Remove post type from query array.
	 *
	 * Used in $this->modify_query to remove 'post' type from specific queries.
	 *
	 * @since 0.4.0
	 *
	 * @param object $query 
	 * @param array $array 
	 * @param string $filter 
	 *
	 * @return boolean
	 */
	public function remove_post_from_array_in_query( $query, $array, $filter = '' ) {
		if( is_array( $array ) && in_array( 'post', $array ) ) {
			unset( $array[ 'post' ] );
			$set_to = empty( $filter ) ? $array : apply_filters( $filter, $array, $query );
			if( ! empty( $set_to ) && method_exists( $query, 'set' ) ) {
				$query->set( 'post_type', $set_to );
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Modify query.
	 *
	 * Remove 'post' post type from any searches and quthor pages.
	 *
	 * @uses $this->remove_post_from_array_in_query
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect
	 * @link http://stackoverflow.com/questions/7225070/php-array-delete-by-value-not-key#7225113
	 * 
	 * @since 0.2.0
	 * @since 0.4.0 added remove_post_from_array_in_query function
	 */
	public function modify_query( $query ) {
		
		// Bail if we're in the admin or not on the main query
		if( is_admin() || ! $query->is_main_query() )
			return;
		
		// Remove 'post' post_type from search results, replace with page
		if( $query->is_search() ) {
			$in_search_post_types = get_post_types( array( 'exclude_from_search' => false ) );
			$this->remove_post_from_array_in_query( $query, $in_search_post_types, 'dwpb_search_post_types' );
		}
	
		// Remove Posts from Author Page
		if( $query->is_author() ) {
			$author_post_types = get_post_types( array( 'publicly_queryable' => true, 'exclude_from_search' => false ) );
			$this->remove_post_from_array_in_query( $query, $author_post_types, 'dwpb_author_post_types' );
		}
	}
	
	/**
	 * Disable Blog feeds.
	 *
	 * @since 0.1.0
	 * @since 0.4.0 add $is_comment_feed variable to feeds and check $is_comment_feed prior to redirect.
	 */
	public function disable_feed( $is_comment_feed ) {
		
		// If this is a comment feed and comments are supported by other post types, bail
		if( $is_comment_feed && dwpb_post_types_with_feature( 'comments' ) )
			return;
		
		// Option to override this via filter and check to confirm post type
		global $post;
		if( apply_filters( 'dwpb_disable_feed', true, $post, $is_comment_feed ) && $post->post_type == 'post' ) {
			$redirect_url = apply_filters( 'dwpb_redirect_feeds', home_url(), $is_comment_feed );
			
			// Provide option to show a message with a link instead of redirect
			if( apply_filters( 'dwpb_feed_message', false, $post, $is_comment_feed ) ) {
				$message = sprintf( 'No feed available, please visit our <a href="%s">homepage</a>!', esc_url_raw( $redirect_url ), 'disable-blog' );
				$message = apply_filters( 'dwpb_feed_die_message', $message );
				wp_die( $message );
				
			// Default option: redirect to homepage
			} else {
				wp_redirect( esc_url_raw( $redirect_url ), 301 );
				exit;
			}
			
		}
	}
	
	/**
	 * Turn off the feed link.
	 *
	 * Only works for WordPress >= 4.4.0.
	 *
	 * @since 0.4.0
	 *
	 * @param string $boolean 
	 *
	 * @return boolean
	 */
	public function feed_links_show_posts_feed( $boolean ) {
		return false;
	}
	
	/**
	 * Turn off the comment's feed link.
	 *
	 * Only works for WordPress >= 4.4.0.
	 *
	 * @since 0.4.0
	 *
	 * @param string $boolean 
	 *
	 * @return boolean
	 */
	public function feed_links_show_comments_feed( $boolean ) {
		
		// If 'post' type is the only type supporting comments, then disable the comment feed link
		if( ! dwpb_post_types_with_feature( 'comments' ) )
			$boolean = false;
		
		return $boolean;
	}


	/**
	 * Remove 'post' type from the REST API results
	 *
	 * Requires the REST API plugin be enabled.
	 *
	 * @since 0.4.2
	 *
	 * @return boolean
	 */
	public function modify_rest_api() {
		if( true !== apply_filters( 'dwpb_disable_rest_api', true ) )
			return;
		
		global $wp_post_types;
		$post_type_name = 'post';
		
		if( isset( $wp_post_types[ $post_type_name ] ) ) {
			$wp_post_types[$post_type_name]->show_in_rest = false;
			return true;
		}

		return false;
	}
}
