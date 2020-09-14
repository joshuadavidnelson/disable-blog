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
	 * @since  0.2.0
	 * @access private
	 * @var    string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since  0.2.0
	 * @access private
	 * @var    string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 0.2.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Redirect single post pages
	 *
	 * @uses dwpb_post_types_with_tax()
	 *
	 * @since 0.2.0
	 * @since 0.4.9 added sitemap checks to avoid redirects on new sitemaps in WP v5.5.
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect
	 *
	 * @return void
	 */
	public function redirect_posts() {

		// Don't redirect on admin or sitemap, and only if there is a homepage to redirect to.
		$sitemap            = get_query_var( 'sitemap', false );
		$sitemap_styelsheet = get_query_var( 'sitemap-stylesheet', false );
		if ( is_admin()
			|| ! get_option( 'page_on_front' )
			|| ! empty( $sitemap )
			|| ! empty( $sitemap_styelsheet ) ) {
			return;
		}

		// Get the front page id and url.
		$page_id = get_option( 'page_on_front' );
		$url     = get_permalink( $page_id );

		// Run the redirects.
		global $post;

		if ( $post instanceof WP_Post && is_singular( 'post' ) ) {

			global $post;

			/**
			 * The redirect url used at any single post page.
			 *
			 * @since 0.4.0
			 *
			 * @param string $url the url to redirct to.
			 */
			$redirect_url = apply_filters( 'dwpb_redirect_posts', $url, $post );

			/**
			 * The redirect url used for a specific post id.
			 *
			 * @since 0.4.0
			 *
			 * @param string $url the url to redirct to.
			 */
			$redirect_url = apply_filters( "dwpb_redirect_post_{$post->ID}", $redirect_url, $post );

		} elseif ( is_tag() && ! dwpb_post_types_with_tax( 'post_tag' ) ) {

			/**
			 * The redirect url used at tag archives.
			 *
			 * @since 0.4.0
			 *
			 * @param string $url the url to redirct to.
			 */
			$redirect_url = apply_filters( 'dwpb_redirect_post_tag_archive', $url );

		} elseif ( is_category() && ! dwpb_post_types_with_tax( 'category' ) ) {

			/**
			 * The redirect url used at category archives
			 *
			 * @since 0.4.0
			 *
			 * @param string $url the url to redirct to.
			 */
			$redirect_url = apply_filters( 'dwpb_redirect_category_archive', $url );

		} elseif ( is_home() ) {

			/**
			 * The redirect url used at the blog page.
			 *
			 * @since 0.4.0
			 *
			 * @param string $url the url to redirct to.
			 */
			$redirect_url = apply_filters( 'dwpb_redirect_blog_page', $url );

		} elseif ( is_date() ) {

			/**
			 * The redirect url used at date archives.
			 *
			 * @since 0.4.0
			 *
			 * @param string $url the url to redirct to.
			 */
			$redirect_url = apply_filters( 'dwpb_redirect_date_archive', $url );

		} else {

			$redirect_url = false;

		}

		// Get the current url and compare to the redirect, if they are the same, bail to avoid a loop
		// If there is no redirect url, then also bail.
		global $wp;
		$current_url = home_url( add_query_arg( array(), $wp->request ) );
		if ( $redirect_url === $current_url || ! $redirect_url ) {
			return;
		}

		/**
		 * Filter to toggle the plugin's front-end redirection.
		 *
		 * @since 0.2.0
		 * @since 0.4.0 added the current_url param.
		 *
		 * @param bool   $bool         True to enable, false to disable.
		 * @param string $redirect_url The url being used for the redirect.
		 * @param string $current_url  The current url.
		 */
		if ( apply_filters( 'dwpb_redirect_front_end', true, $redirect_url, $current_url ) ) {
			wp_safe_redirect( esc_url( $redirect_url ), 301 );
			exit();
		}

	}

	/**
	 * Modify query.
	 *
	 * Remove 'post' post type from any searches and archives.
	 *
	 * @uses $this->remove_post_from_array_in_query
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect
	 * @link http://stackoverflow.com/questions/7225070/php-array-delete-by-value-not-key#7225113
	 *
	 * @since 0.2.0
	 * @since 0.4.0 added remove_post_from_array_in_query function
	 * @since 0.4.9 remove 'post' from all archives.
	 * @since 0.4.10 update to just remove 'post' from built-in taxonomy archives,
	 * @param object $query the query object.
	 * @return void
	 */
	public function modify_query( $query ) {

		// Bail if we're in the admin or not on the main query.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Let's see if there are any post types supporting build-in taxonomies.
		$tag_post_types      = dwpb_post_types_with_tax( 'post_tag' );
		$category_post_types = dwpb_post_types_with_tax( 'category' );

		// Remove existing posts from built-in taxonomy archives, if they are supported by another post type.
		if ( $query->is_tag() && $tag_post_types ) {

			$this->set_post_types_in_query( $query, $tag_post_types, 'dwpb_tag_post_types' );

		} elseif ( $query->is_category() && $category_post_types ) {

			$this->set_post_types_in_query( $query, $category_post_types, 'dwpb_category_post_types' );

		}

	}

	/**
	 * Set post types for tag and category archive queries, excluding 'post' as the default type.
	 *
	 * Used in $this->modify_query to remove 'post' type from built-in archive queries.
	 *
	 * @since 0.4.0
	 *
	 * @param object $query       the main query object.
	 * @param array  $post_types  the array of post types.
	 * @param string $filter the  filter to be applied.
	 *
	 * @return bool
	 */
	public function set_post_types_in_query( $query, $post_types = array(), $filter = '' ) {

		/**
		 * If there is a filter name passed, then a filter is applied on the array and query.
		 *
		 * Used for 'dwpb_tag_post_types' and 'dwpb_category_post_types' filters.
		 *
		 * @see Disable_Blog_Public->modify_query
		 *
		 * @since 0.4.0
		 * @since 0.4.10 fix bug in 0.4.9 causing cpt weirdness, now always using the filter.
		 *
		 * @param array $array
		 * @param object $query
		 */
		$set_to = apply_filters( $filter, $post_types, $query );
		if ( ! empty( $set_to ) && method_exists( $query, 'set' ) ) {
			$query->set( 'post_type', $set_to );
			return true;
		}

		return false;

	}

	/**
	 * Disable Blog feeds.
	 *
	 * @since 0.1.0
	 * @since 0.4.0 add $is_comment_feed variable to feeds and check $is_comment_feed prior to redirect.
	 *
	 * @param bool $is_comment_feed true if a comment feed.
	 *
	 * @return void
	 */
	public function disable_feed( $is_comment_feed ) {

		// If this is a comment feed and comments are supported by other post types, bail.
		if ( $is_comment_feed && dwpb_post_types_with_feature( 'comments' ) ) {
			return;
		}

		// Option to override this via filter and check to confirm post type.
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
			$redirect_url = apply_filters( 'dwpb_redirect_feeds', home_url(), $post, $is_comment_feed );

			/**
			 * Filter to toggle on a message instead of a redirect.
			 *
			 * Defaults to false, so a redirect is the expacted default behavior.
			 *
			 * @since 0.4.0
			 * @since 0.4.9 updated variables passed to match other feed filters,
			 *              previously only $is_comment_feed was passed and now
			 *              the order is: bool, $post, $is_comment_feed.
			 *              Note that if you used this filter before
			 *              and relied on the $is_comment_feed, you'll need to update.
			 *
			 * @param bool $bool True to use a message, false to redirect.
			 * @param object $post Global post object.
			 * @param bool $is_comment_feed True if the feed is a comment feed.
			 */
			if ( apply_filters( 'dwpb_feed_message', false, $post, $is_comment_feed ) ) {

				// translators: the placeholser is the URL of our website.
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
				$message      = apply_filters( 'dwpb_feed_die_message', $message );
				$allowed_html = array(
					'a' => array(
						'href' => array(),
						'name' => array(),
						'id'   => array(),
					),
				);
				wp_die( wp_kses( $message, $allowed_html ) );

			} else { // Default option: redirect to homepage.

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
	 * @param bool $bool true to show the posts feed link.
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
	 * @param bool $bool true to show the comments feed link.
	 *
	 * @return bool
	 */
	public function feed_links_show_comments_feed( $bool ) {

		// If 'post' type is the only type supporting comments, then disable the comment feed link.
		if ( ! dwpb_post_types_with_feature( 'comments' ) ) {
			$bool = false;
		}

		return $bool;

	}

	/**
	 * Remove feed urls from head.
	 *
	 * @since 0.4.9
	 *
	 * @return void
	 */
	public function header_feeds() {

		// Various feed links.
		$feed = array(
			'feed_links'       => 2,
			'feed_links_extra' => 3,
			'rsd_link'         => 10,
			'wlwmanifest_link' => 10,
		);

		// Remove from head.
		foreach ( $feed as $function => $priority ) {
			remove_action( 'wp_head', $function, $priority );
		}

	}

	/**
	 * Unset all post-related xmlrpc methods.
	 *
	 * @see wp-includes/class-wp-xmlrpc-server.php
	 *
	 * @since 0.4.9
	 *
	 * @param array $methods the arrayve of xmlrpc methods.
	 *
	 * @return array
	 */
	public function xmlrpc_methods( $methods ) {

		// The methods to remove.
		$methods_to_remove = array(
			'wp.getUsersBlogs',
			'wp.newPost',
			'wp.editPost',
			'wp.deletePost',
			'wp.getPost',
			'wp.getPosts',
			'blogger.getPost',
			'blogger.getRecentPosts',
			'blogger.newPost',
			'blogger.editPost',
			'blogger.deletePost',
			'metaWeblog.newPost',
			'metaWeblog.editPost',
			'metaWeblog.getPost',
			'metaWeblog.getRecentPosts',
			'metaWeblog.deletePost',
			'mt.getRecentPostTitles',
			'mt.getTrackbackPings',
			'mt.publishPost',
			'pingback.ping',
			'pingback.extensions.getPingbacks',
			'system.multicall',
			'system.listMethods',
			'system.getCapabilities',
			'demo.sayHello',
			'demo.addTwoNumbers',
		);

		// Remove category / post tag terms, if not supported by another post type.
		$taxonomy_methods = array();
		if ( ! dwpb_post_types_with_tax( 'category' ) ) {
			$taxonomy_methods = array(
				'wp.newCategory',
				'wp.deleteeCategory',
				'mt.getCategoryList',
				'wp.suggestCategories',
				'mt.getPostCategories',
				'mt.setPostCategories',
				'metaWeblog.getCategories',
			);
		}
		if ( ! dwpb_post_types_with_tax( 'post_tag' ) ) {
			$taxonomy_methods[] = 'wp.getTags';
		}

		$methods_to_remove = array_merge( $methods_to_remove, $taxonomy_methods );

		if ( is_array( $methods_to_remove ) ) {
			foreach ( $methods_to_remove as $method ) {
				if ( isset( $methods[ $method ] ) ) {
					unset( $methods[ $method ] );
				}
			}
		}

		return $methods;

	}

	/**
	 * Remove 'post' post type from sitemaps.
	 *
	 * @since 0.4.9
	 * @param array $post_types an array of post type strings supported in sitemaps.
	 * @return array
	 */
	public function wp_sitemaps_post_types( $post_types ) {

		if ( isset( $post_types['post'] ) ) {
			unset( $post_types['post'] );
		}

		return $post_types;

	}

	/**
	 * Conditionally remove built-in taxonomies from sitemaps, if they are not being used by a custom post type.
	 *
	 * @since 0.4.9
	 * @uses dwpb_post_types_with_tax()
	 * @param array $taxonomies an array of taxonomy strings supported in sitemaps.
	 * @return array
	 */
	public function wp_sitemaps_taxonomies( $taxonomies ) {

		$built_in_taxonomies = array(
			'post_tag',
			'category',
		);
		foreach ( $built_in_taxonomies as $tax ) {
			if ( isset( $taxonomies[ $tax ] ) && ! dwpb_post_types_with_tax( $tax ) ) {
				unset( $taxonomies[ $tax ] );
			}
		}

		return $taxonomies;

	}

}
