<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.2.0
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
	 * @since    0.2.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.2.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.2.0
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

		if( is_admin() || ! get_option( 'page_on_front' ) )
			return;

		// Get the front page id and url
		$page_id = get_option( 'page_on_front' );
		$url = get_permalink( $page_id );

		// Run the redirects
		global $post;

		if( $post instanceof WP_Post && is_singular( 'post' ) ) {

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
		$current_url = esc_url( $protocol . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] );
		if( $redirect_url == $current_url || ! $redirect_url ) {
			return;
		}

		/**
		 * Filter to toggle the plugin's front-end redirection.
		 *
		 * @since 0.2.0
		 * @since 0.4.0 added the current_url param.
		 *
		 * @param bool  $bool True to enable, false to disable.
		 * @param string $redirect_url The url being used for the redirect.
		 * @param string $current_url The current url.
		 */
		if ( apply_filters( 'dwpb_redirect_front_end', true, $redirect_url, $current_url ) ) {
			wp_safe_redirect( esc_url( $redirect_url ), 301 );
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
	 * @return bool
	 */
	public function remove_post_from_array_in_query( $query, $array, $filter = '' ) {

		if ( is_array( $array ) && in_array( 'post', $array, true ) ) {
			unset( $array['post'] );

			/**
			 * If there is a filter name passed, then a filter is applied on the array and query.
			 *
			 * Used for 'dwpb_search_post_types' and 'dwpb_author_post_types' filters.
			 *
			 * @see Disable_Blog_Public->modify_query
			 *
			 * @since 0.4.0
			 *
			 * @param array $array
			 * @param object $query
			 */
			$set_to = empty( $filter ) ? $array : apply_filters( $filter, $array, $query );
			if ( ! empty( $set_to ) && method_exists( $query, 'set' ) ) {
				$query->set( 'post_type', $set_to );
				return true;
			}

		} // end if

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
		if ( $query->is_search() ) {
			$in_search_post_types = get_post_types( array(
				'exclude_from_search' => false,
			) );
			$this->remove_post_from_array_in_query( $query, $in_search_post_types, 'dwpb_search_post_types' );
		}

		// Remove Posts from Author Page
		if ( $query->is_author() ) {
			$author_post_types = get_post_types( array(
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
			) );
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

		/**
		 * Toggle the disable feed via this filter.
		 *
		 * @since 0.4.0
		 *
		 * @param bool $bool True to cancel the feed, assuming it's a post feed.
		 * @param object $post Global post object.
		 * @param bool $is_comment_feed True if the feed is a comment feed.
		 */
		if ( apply_filters( 'dwpb_disable_feed', true, $post, $is_comment_feed ) && isset( $post->post_type ) && 'post' === $post->post_type ) {

			/**
			 * Filter the feed redirect url.
			 *
			 * @since 0.4.0
			 *
			 * @param string $url The redirect url (defaults to homepage)
			 * @param bool $is_comment_feed True if the feed is a comment feed.
			 */
			$redirect_url = apply_filters( 'dwpb_redirect_feeds', home_url(), $is_comment_feed );

			/**
			 * Filter to toggle on a message instead of a redirect.
			 *
			 * Defaults to false, so a redirect is the expacted default behavior.
			 *
			 * @since 0.4.0
			 *
			 * @param bool $bool True to use a message, false to redirect.
			 * @param object $post Global post object.
			 * @param bool $is_comment_feed True if the feed is a comment feed.
			 */
			if ( apply_filters( 'dwpb_feed_message', false, $post, $is_comment_feed ) ) {

				// translators: the placeholser is the URL of our website
				$message = sprintf( __( 'No feed available, please visit our <a href="%s">homepage</a>!', 'disable-blog' ), esc_url_raw( $redirect_url ) );

				/**
				 * Filter the feed die message.
				 *
				 * If the `dwpb_feed_message` is set to true, use this filter to set a custom message.
				 *
				 * @since 0.4.0
				 *
				 * @param string $message
				 */
				$message = apply_filters( 'dwpb_feed_die_message', $message );
				$allowed_html = array(
					'a' => array(
						'href' => array(),
						'name' => array(),
						'id'   => array(),
					)
				);
				$safe_message = wp_kses( $message, $allowed_html );
				wp_die( $safe_message );

			// Default option: redirect to homepage
			} else {

				wp_safe_redirect( esc_url_raw( $redirect_url ), 301 );
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
	 * @param string $bool 
	 *
	 * @return bool
	 */
	public function feed_links_show_posts_feed( $bool ) {
		return false;
	}

	/**
	 * Turn off the comment's feed link.
	 *
	 * Only works for WordPress >= 4.4.0.
	 *
	 * @since 0.4.0
	 *
	 * @param string $bool 
	 *
	 * @return bool
	 */
	public function feed_links_show_comments_feed( $bool ) {

		// If 'post' type is the only type supporting comments, then disable the comment feed link
		if( ! dwpb_post_types_with_feature( 'comments' ) )
			$bool = false;

		return $bool;

	}

	/**
	 * Remove 'post' type from the REST API results
	 *
	 * Requires the REST API plugin be enabled.
	 *
	 * @since 0.4.2
	 *
	 * @return bool
	 */
	public function modify_rest_api() {

		/**
		 * Filter to toggle the disable rest API.
		 *
		 * @since 0.4.2
		 *
		 * @param bool $bool True to modify API, false to cancel.
		 */
		if ( true !== apply_filters( 'dwpb_disable_rest_api', true ) ) {
			return false;
		}

		global $wp_post_types;
		$post_type_name = 'post';

		if( isset( $wp_post_types[ $post_type_name ] ) ) {
			$wp_post_types[$post_type_name]->show_in_rest = false;
			return true;
		}

		return false;
	}
}
