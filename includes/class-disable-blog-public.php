<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.2.0
 * @package    Disable_Blog
 * @subpackage Disable_Blog_Public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and contains all the public functions.
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
	 * Object with common utility functions.
	 *
	 * @since  0.5.0
	 * @access private
	 * @var    object
	 */
	private $functions;

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
		$this->functions   = new Disable_Blog_Functions();

	}

	/**
	 * Redirect single post pages
	 *
	 * @uses dwpb_post_types_with_tax()
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect
	 * @since 0.2.0
	 * @since 0.4.9 added sitemap checks to avoid redirects on new sitemaps in WP v5.5.
	 * @since 0.5.0 renamed to redirect_public_pages
	 * @return void
	 */
	public function redirect_public_pages() {

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
		$page_id      = get_option( 'page_on_front' );
		$homepage_url = get_permalink( $page_id );
		$redirect_url = false;

		// The public pages to potentially be redirected.
		global $post;
		$public_redirects = array(
			'post'             => ( $post instanceof WP_Post && is_singular( 'post' ) ),
			'post_tag_archive' => ( is_tag() && ! dwpb_post_types_with_tax( 'post_tag' ) ),
			'category_archive' => ( is_category() && ! dwpb_post_types_with_tax( 'category' ) ),
			'blog_page'        => is_home(),
			'date_archive'     => is_date(),
			'author_archive'   => ( is_author() && true === $this->functions->disable_author_archives() ),
		);

		// cycle through each public page, checking if we need to redirect.
		foreach ( $public_redirects as $filtername => $bool ) {

			// Custom function within this class used to check if the page needs to be redirected.
			$filter = 'dwpb_redirect_' . $filtername;

			// If this is the right page, then setup the redirect url.
			if ( true === $bool ) {

				/**
				 * The redirect url used for this public page.
				 *
				 * Example: use 'dwpb_redirect_post' to change the redirect url used
				 * on a post, or 'dwpb_redirect_post_tag_archive' to redirect tag archives.
				 *
				 * @since 0.4.0
				 * @since 0.5.0 combine filters.
				 * @param string $url the url to redirect to, defaults to homepage.
				 */
				$redirect_url = apply_filters( $filter, $homepage_url );

				break; // no need to keep looping.

			} // end if
		} // end foreach

		// Only continue if we have a redirect url.
		if ( ! $redirect_url ) {
			return;
		}

		/**
		 * Filter to toggle the plugin's front-end redirection.
		 *
		 * @since 0.2.0
		 * @since 0.4.0 added the current_url param.
		 * @since 0.5.0 removed 'redirect_url' && 'current_url' params.
		 * @param bool $bool True to enable, false to disable.
		 */
		if ( apply_filters( 'dwpb_redirect_front_end', true ) ) {

			/**
			 * Global public url redirect filter.
			 *
			 * @since 0.5.0
			 * @param string $redirect_url The redirect url.
			 */
			$redirect_url = apply_filters( 'dwpb_front_end_redirect_url', $redirect_url );

			$this->functions->redirect( $redirect_url );
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
	 * @since 0.5.0 remove 'post' type from author archives.
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
		$author_post_types   = $this->functions->author_archive_post_types();

		// Remove existing posts from built-in taxonomy archives, if they are supported by another post type.
		if ( $query->is_tag() && $tag_post_types ) { // tag archives.

			$this->set_post_types_in_query( $query, $tag_post_types, 'dwpb_tag_post_types' );

		} elseif ( $query->is_category() && $category_post_types ) { // category archives.

			$this->set_post_types_in_query( $query, $category_post_types, 'dwpb_category_post_types' );

		} elseif ( $query->is_author() && ! empty( $author_post_types ) ) { // author archives, if supported, have a filter for setting the post types.

			$this->set_post_types_in_query( $query, $author_post_types );

		}
	}

	/**
	 * Set post types for tag and category archive queries, excluding 'post' as the default type.
	 *
	 * Used in $this->modify_query to remove 'post' type from built-in archive queries.
	 *
	 * @since 0.4.0
	 * @param object $query       the main query object.
	 * @param array  $post_types  the array of post types.
	 * @param string $filter the  filter to be applied.
	 * @return bool
	 */
	public function set_post_types_in_query( $query, $post_types = array(), $filter = '' ) {

		/**
		 * If there is a filter name passed, then a filter is applied on the array and query.
		 *
		 * Used for 'dwpb_tag_post_types' and 'dwpb_category_post_types' filters.
		 * Note that the 'dwpb_author_archive_post_types' filter is passed in another function,
		 * hence the reason $filter can be empty and not passed in this function.
		 *
		 * @see Disable_Blog_Public->modify_query
		 * @since 0.4.0
		 * @since 0.4.10 fix bug in 0.4.9 causing cpt weirdness, now always using the filter.
		 * @since 0.5.0 made the filter part of this function optional, since the author
		 *               post type filter is located in the functions class.
		 * @param array  $post_types An array of post type slugs.
		 * @param object $query      The query object being modified.
		 * @return array
		 */
		if ( ! empty( $filter ) ) {
			$set_to = apply_filters( $filter, $post_types, $query );
		} else {
			$set_to = $post_types;
		}
		if ( ! empty( $set_to ) && method_exists( $query, 'set' ) && is_array( $set_to ) ) {
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
	 * @param bool $is_comment_feed true if a comment feed.
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
		 * @param bool $bool True to cancel the feed, assuming it's a post feed.
		 * @param object $post Global post object.
		 * @param bool $is_comment_feed True if the feed is a comment feed.
		 */
		if ( apply_filters( 'dwpb_disable_feed', true, $post, $is_comment_feed ) && isset( $post->post_type ) && 'post' === $post->post_type ) {

			/**
			 * Filter the feed redirect url.
			 *
			 * @since 0.4.0
			 * @param string $url            The redirect url (defaults to homepage)
			 * @param object $post           The global post object.
			 * @param bool   $is_comment_feed True if the feed is a comment feed.
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
			 * @param bool   $bool            True to use a message, false to redirect.
			 * @param object $post            Global post object.
			 * @param bool   $is_comment_feed True if the feed is a comment feed.
			 */
			if ( apply_filters( 'dwpb_feed_message', false, $post, $is_comment_feed ) ) {

				// translators: This message appears when the feed is disabled instead of redirect, it should point to the homepage.
				$message = sprintf( '%s: <a href="%s">%s</a>', __( 'No feed available, please visit our homepage:', 'disable-blog' ), esc_url_raw( $redirect_url ), esc_url_raw( $redirect_url ) );

				/**
				 * Filter the feed die message.
				 *
				 * If the `dwpb_feed_message` is set to true, use this filter to set a custom message.
				 *
				 * @since 0.4.0
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

				$this->functions->redirect( $redirect_url );

			}
		}
	}

	/**
	 * Turn off the feed link.
	 *
	 * Only works for WordPress >= 4.4.0.
	 *
	 * @since 0.4.0
	 * @param bool $bool true to show the posts feed link.
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
	 * @param bool $bool true to show the comments feed link.
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
	 * @since 0.4.9
	 * @param array $methods the arrayve of xmlrpc methods.
	 * @return array
	 */
	public function xmlrpc_methods( $methods ) {

		$methods_to_remove = $this->get_disabled_xmlrpc_methods();

		if ( ! empty( $methods_to_remove ) && is_array( $methods_to_remove ) ) {
			foreach ( $methods_to_remove as $method ) {
				if ( isset( $methods[ $method ] ) ) {
					unset( $methods[ $method ] );
				}
			}
		}

		return $methods;

	}

	/**
	 * Get the XML-RPC methods to disable.
	 *
	 * @since 0.5.0
	 * @return array|bool
	 */
	private function get_disabled_xmlrpc_methods() {

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

		/**
		 * Filter the methods being disabled by the plugin.
		 *
		 * Return false to disable this functionality entirely and keep all methods in place.
		 *
		 * @since 0.5.0
		 * @param array $methods_to_remove an array of all the XMLRPC methods to disable.
		 * @return array|bool
		 */
		$methods_to_remove = apply_filters( 'dwpb_disabled_xmlrpc_methods', $methods_to_remove );

		// filter any invalid entries out before returning the array.
		return is_array( $methods_to_remove ) ? array_filter( $methods_to_remove, 'is_string' ) : false; // phpcs:ignore

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

	/**
	 * Remove author sitemaps.
	 *
	 * @since 0.5.0
	 * @link https://developer.wordpress.org/reference/hooks/wp_sitemaps_add_provider/
	 * @param object $provider Instance of a WP_Sitemaps_Provider.
	 * @param string $name     Name of the sitemap provider.
	 * @return object|bool Instance of a WP_Sitemaps_Provider or false.
	 */
	public function wp_author_sitemaps( $provider, $name ) {

		// If there are no author archives, then don't show the sitemap.
		$disable_author_archives = $this->functions->disable_author_archives();
		if ( true === $disable_author_archives ) {

			$disable_sitemap = true;

		} else { // Otherwise, we may show it?

			// Check if we have any post types supporting author archives.
			$author_archives_supported = $this->functions->author_archive_post_types();

			// Only show the sitemap if there are post types support on the archives.
			$disable_sitemap = empty( $author_archives_supported );

		}

		/**
		 * Turn off user/author sitemaps.
		 *
		 * @since 0.5.0
		 * @param bool $bool True to disable, defaults to true.
		 * @return bool
		 */
		if ( 'users' === $name && apply_filters( 'dwpb_disable_user_sitemap', $disable_sitemap ) ) {
			return false;
		}

		return $provider;

	}

}
