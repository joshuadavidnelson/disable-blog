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
	 * @since  0.4.0
	 * @access private
	 * @var    string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since  0.4.0
	 * @access private
	 * @var    string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 0.4.0
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Disable public arguments of the 'post' post type.
	 *
	 * @since 0.4.2
	 * @since 0.4.9 removed rest api specific filter and updated function
	 *              for disabling all public-facing aspects of the 'post' post type.
	 */
	public function modify_post_type_arguments() {

		global $wp_post_types;

		if ( isset( $wp_post_types['post'] ) ) {
			$arguments_to_remove = array(
				'has_archive',
				'public',
				'publicaly_queryable',
				'rewrite',
				'query_var',
				'show_ui',
				'show_in_admin_bar',
				'show_in_nav_menus',
				'show_in_menu',
				'show_in_rest',
			);

			foreach ( $arguments_to_remove as $arg ) {
				if ( isset( $wp_post_types['post']->$arg ) ) {
					// @codingStandardsIgnoreStart - phpcs doesn't like using variables like this.
					$wp_post_types['post']->$arg = false;
					// @codingStandardsIgnoreEnd
				}
			}

			// exclude from search.
			$wp_post_types['post']->exclude_from_search = true;

			// remove supports.
			$wp_post_types['post']->supports = array();

		}

	}

	/**
	 * Disable public arguments of the 'category' and 'post_tag' taxonomies.
	 *
	 * Only disables these if the 'post' post type is the only post type using them.
	 *
	 * @since 0.4.9
	 *
	 * @uses dwpb_post_types_with_tax()
	 *
	 * @return void
	 */
	public function modify_taxonomies_arguments() {

		global $wp_taxonomies;
		$taxonomies = array( 'category', 'post_tag' );

		foreach ( $taxonomies as $tax ) {
			if ( isset( $wp_taxonomies[ $tax ] ) ) {

				// remove 'post' from object types.
				if ( isset( $wp_taxonomies[ $tax ]->object_type ) ) {
					if ( is_array( $wp_taxonomies[ $tax ]->object_type ) ) {
						$key = array_search( 'post', $wp_taxonomies[ $tax ]->object_type, true );
						if ( false !== $key ) {
							unset( $wp_taxonomies[ $tax ]->object_type[ $key ] );
						}
					}
				}

				// only modify the public arguments if 'post' is the only post type.
				// using this taxonomy.
				if ( ! dwpb_post_types_with_tax( $tax ) ) {

					// public arguments to remove.
					$arguments_to_remove = array(
						'has_archive',
						'public',
						'publicaly_queryable',
						'query_var',
						'show_ui',
						'show_tagcloud',
						'show_in_admin_bar',
						'show_in_quick_edit',
						'show_in_nav_menus',
						'show_admin_column',
						'show_in_menu',
						'show_in_rest',
					);

					foreach ( $arguments_to_remove as $arg ) {
						if ( isset( $wp_taxonomies[ $tax ]->$arg ) ) {
							// @codingStandardsIgnoreStart - phpcs doesn't like using variables like this.
							$wp_taxonomies[ $tax ]->$arg = false;
							// @codingStandardsIgnoreEnd
						}
					}
				}
			}
		}

	}

	/**
	 * Redirect blog-related admin pages
	 *
	 * @uses dwpb_post_types_with_tax()
	 *
	 * @since 0.1.0
	 * @since 0.4.0 added single post edit screen redirect
	 *
	 * @return void
	 */
	public function redirect_admin_pages() {

		global $pagenow;

		if ( ! isset( $pagenow ) ) {
			return;
		}

		$screen = get_current_screen();

		// on multisite: Do not redirect if we are on a network page.
		if ( is_multisite() && is_callable( array( $screen, 'in_admin' ) ) && $screen->in_admin( 'network' ) ) {
			return;
		}

		// setup false redirect url value for default/final check.
		$redirect_url = false;

		/**
		 * Redirect admin page at edit tags screen.
		 *
		 * If this is either the edit-tags page or term page and the taxonomy is
		 * not supported by post types other than `post` then redirect.
		 *
		 * @since 0.4.0
		 *
		 * @param bool $bool True to redirect the edit-tags.php page, default is true.
		 */
		if ( apply_filters( 'dwpb_redirect_admin_edit_tags', true ) ) {
			// @codingStandardsIgnoreStart - phpcs wants to sanitize this, but it's not necessary.
			if ( ( 'edit-tags.php' === $pagenow || 'term.php' === $pagenow ) && ( isset( $_GET['taxonomy'] ) && ! dwpb_post_types_with_tax( $_GET['taxonomy'] ) ) ) {
			// @codingStandardsIgnoreEnd

				/**
				 * The redirect url used at the edit-tags.php and term.php pages.
				 *
				 * @since 0.4.0
				 *
				 * @param string $url the url to redirct to, defaults to dashboard.
				 */
				$redirect_url = apply_filters( 'dwpb_redirect_edit_tax', admin_url( '/index.php' ) );
			}
		}

		/**
		 * Redirect the comments admin page.
		 *
		 * Will only work comments are only supported by 'post' type,
		 * note that pages and attachments support comments by default.
		 *
		 * @since 0.4.0
		 *
		 * @param bool $bool True to redirect the edit-comments.php page, default is true.
		 */
		if ( apply_filters( 'dwpb_redirect_admin_edit_comments', true ) ) {
			if ( 'edit-comments.php' === $pagenow && ! dwpb_post_types_with_feature( 'comments' ) ) {

				/**
				 * The redirect url used at the edit-tags.php and term.php pages.
				 *
				 * @since 0.4.0
				 *
				 * @param string $url the url to redirct to, defaults to dashboard.
				 */
				$redirect_url = apply_filters( 'dwpb_redirect_edit_comments', admin_url( 'index.php' ) );

			}
		}

		/**
		 * Redirect disccusion options page.
		 *
		 * Will only work comments are only supported by 'post' type.
		 *
		 * @since 0.4.0
		 *
		 * @param bool $bool True to redirect the options-discussion.php page, default is true.
		 */
		if ( apply_filters( 'dwpb_redirect_admin_options_discussion', true ) ) {
			if ( 'options-discussion.php' === $pagenow && ! dwpb_post_types_with_feature( 'comments' ) ) {

				/**
				 * The redirect url used at the blog page.
				 *
				 * @since 0.4.0
				 *
				 * @param string $url the url to redirct to, defaults to dashboard.
				 */
				$redirect_url = apply_filters( 'dwpb_redirect_options_discussion', admin_url( '/index.php' ) );
			}
		}

		// Redirect writing options to general options.
		if ( 'options-writing.php' === $pagenow && $this->remove_writing_options() ) {

			/**
			 * The redirect url used at the blog page.
			 *
			 * @since 0.4.0
			 *
			 * @param string $url the url to redirct to, defaults to options-general.php.
			 */
			$redirect_url = apply_filters( 'dwpb_redirect_options_writing', admin_url( '/options-general.php' ) );
		}

		/**
		 * Redirect available tools page.
		 *
		 * @since 0.4.0
		 *
		 * @param bool $bool True to redirect the tools.php page, default is true.
		 */
		if ( apply_filters( 'dwpb_redirect_admin_options_tools', true ) ) {
			// @codingStandardsIgnoreStart - phpcs wants to nounce this, but that's not needed.
			if ( 'tools.php' === $pagenow && ! isset( $_GET['page'] ) ) {
			// @codingStandardsIgnoreEnd

				/**
				 * The redirect url used at the blog page.
				 *
				 * @since 0.4.0
				 *
				 * @param string $url the url to redirct to, defaults to dashboard.
				 */
				$redirect_url = apply_filters( 'dwpb_redirect_options_tools', admin_url( '/index.php' ) );
			}
		}

		// Get the current url and compare to the redirect, if they are the same, bail to avoid a loop
		// If there is no redirect url, then also bail.
		global $wp;
		$current_url = admin_url( add_query_arg( array(), $wp->request ) );
		if ( $redirect_url === $current_url || ! $redirect_url ) {
			return;
		}

		/**
		 * Redirect blog related admin pages.
		 *
		 * @since 0.4.0
		 *
		 * @param bool   $bool         True to enable, default is true.
		 * @param string $redirect_url The url to being used in the redirect.
		 * @param string $current_url  The current url.
		 */
		if ( apply_filters( 'dwpb_redirect_admin', true, $redirect_url, $current_url ) ) {
			wp_safe_redirect( esc_url_raw( $redirect_url ), 301 );
			exit;
		}

	}

	/**
	 * Remove the X-Pingback HTTP header.
	 *
	 * @since 0.4.0
	 *
	 * @param array $headers the pingback headers.
	 * @return array
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
	 *
	 * @return void
	 */
	public function remove_menu_pages() {

		// Main pages to be removed.
		$remove_pages = array( 'edit.php' );

		// If no post type supports comments, then remove the edit-comments.php page from the admin.
		if ( ! dwpb_post_types_with_feature( 'comments' ) ) {
			$remove_pages[] = 'edit-comments.php';
		}

		/**
		 * Top level admin pages to remove.
		 *
		 * @since 0.4.0
		 *
		 * @param array $remove_pages Array of page strings.
		 */
		$pages = apply_filters( 'dwpb_menu_pages_to_remove', $remove_pages );
		foreach ( $pages as $page ) {
			remove_menu_page( $page );
		}

		// Submenu Pages.
		$remove_subpages = array(
			'tools.php' => 'tools.php',
		);

		// Remove the writings page, if the filter tells us so.
		if ( $this->remove_writing_options() ) {
			$remove_subpages['options-general.php'] = 'options-writing.php';
		}

		// If there are no other post types supporting comments, remove the discussion page.
		if ( ! dwpb_post_types_with_feature( 'comments' ) ) {
			$remove_subpages['options-general.php'] = 'options-discussion.php'; // Settings > Discussion.
		}

		/**
		 * Admin subpages to be removed.
		 *
		 * @since 0.4.0
		 *
		 * @param array $remove_subpages Array of page => subpage.
		 */
		$subpages = apply_filters( 'dwpb_menu_subpages_to_remove', $remove_subpages );
		foreach ( $subpages as $page => $subpage ) {
			remove_submenu_page( $page, $subpage );
		}

	}

	/**
	 * Filter the body classes for admin screens to toggle on plugin specific styles.
	 *
	 * @since 0.4.7
	 *
	 * @param string $classes the admin body classes, which is a string *not* an array.
	 *
	 * @return string
	 */
	public function admin_body_class( $classes ) {

		if ( $this->has_front_page() ) {
			$classes .= ' disabled-blog';
		}

		return $classes;

	}

	/**
	 * Filter for removing the writing options page.
	 *
	 * @since 0.4.5
	 *
	 * @return bool
	 */
	public function remove_writing_options() {

		/**
		 * Toggle the options-writing page on/off.
		 *
		 * Defaults to false, true will create a redirect for the page
		 * and remove it from the admin menu.
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
	 *
	 * @return void
	 */
	public function remove_admin_bar_links() {

		global $wp_admin_bar;

		// If only posts support comments, then remove comment from admin bar.
		if ( ! dwpb_post_types_with_feature( 'comments' ) ) {
			$wp_admin_bar->remove_menu( 'comments' );
		}

		// Remove New Post from Content.
		$wp_admin_bar->remove_node( 'new-post' );

	}

	/**
	 * Hide all comments from 'post' post type
	 *
	 * @uses dwpb_post_types_with_feature()
	 *
	 * @since 0.1.0
	 *
	 * @param object $comments the comments query object.
	 * @return object
	 */
	public function comment_filter( $comments ) {

		global $pagenow;

		if ( ! isset( $pagenow ) ) {
			return $comments;
		}

		// Filter out comments from post.
		$post_types = dwpb_post_types_with_feature( 'comments' );
		if ( $post_types && $this->is_admin_page( 'edit-comments' ) ) {
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
	 *
	 * @return void
	 */
	public function remove_dashboard_widgets() {

		// Remove post-specific widgets only, others obscured/modified elsewhere as necessary.
		$metabox = array(
			'dashboard_quick_press'    => 'side', // Quick Press.
			'dashboard_recent_drafts'  => 'side', // Recent Drafts.
			'dashboard_incoming_links' => 'normal', // Incoming Links.
			'dashboard_activity'       => 'normal', // Activity.
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
	 *
	 * @return void
	 */
	public function admin_notices() {

		// only throwing this notice in the edit.php, plugins.php, and options-reading.php admin pages.
		$current_screen = get_current_screen();
		$screens        = array(
			'plugins',
			'options-reading',
			'edit',
		);
		if ( ! ( isset( $current_screen->base ) && in_array( $current_screen->base, $screens, true ) ) ) {
			return;
		}

		// Throw a notice if the we don't have a front page.
		if ( ! $this->has_front_page() ) {

			// The second part of the notice depends on which screen we're on.
			if ( 'options-reading' === $current_screen->base ) {

				// translators: Direct the user to set a homepage in the current screen.
				$message_link = ' ' . __( 'Select a page for your homepage below.', 'disable-blog' );

			} else { // If we're not on the Reading Options page, then direct the user there.

				$reading_options_page = get_admin_url( null, 'options-reading.php' );

				// translators: Direct the user to the Reading Settings admin page.
				$message_link = ' ' . sprintf( __( 'Change in <a href="%s">Reading Settings</a>.', 'disable-blog' ), $reading_options_page );

			}

			// translators: Prompt to configure the site for static homepage and posts page.
			$message = __( 'Disable Blog is not fully active until a static page is selected for the site\'s homepage.', 'disable-blog' ) . $message_link;

			printf( '<div class="%s"><p>%s</p></div>', 'notice notice-error', esc_attr( $message ) );

			// If we have a front page set, but no posts page or they are the same,.
			// Then let the user know the expected behavior of these two.
		} elseif ( 'options-reading' === $current_screen->base
					&& get_option( 'page_for_posts' ) === get_option( 'page_on_front' ) ) {

			// translators: Warning that the homepage and blog page cannot be the same, the post page is redirected to the homepage.
			$message = __( 'Disable Blog requires a homepage that is different from the post page. The "posts page" will be redirected to the homepage.', 'disable-blog' );

			printf( '<div class="%s"><p>%s</p></div>', 'notice notice-error', esc_attr( $message ) );

		}

	}

	/**
	 * Check that the site has a front page set in the Settings > Reading.
	 *
	 * @since 0.4.7
	 *
	 * @return bool
	 */
	public function has_front_page() {

		return 'page' === get_option( 'show_on_front' ) && absint( get_option( 'page_on_front' ) );

	}

	/**
	 * Kill the Press This functionality
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function disable_press_this() {

		wp_die( '"Press This" functionality has been disabled.' );

	}

	/**
	 * Remove post related widgets
	 *
	 * @since 0.2.0
	 * @since 0.4.0 simplified unregistering and added dwpb_unregister_widgets filter.
	 *
	 * @return void
	 */
	public function remove_widgets() {

		// Unregister blog-related widgets.
		$widgets = array(
			'WP_Widget_Recent_Comments', // Recent Comments.
			'WP_Widget_Tag_Cloud', // Tag Cloud.
			'WP_Widget_Categories', // Categories.
			'WP_Widget_Archives', // Archives.
			'WP_Widget_Calendar', // Calendar.
			'WP_Widget_Links', // Links.
			'WP_Widget_Recent_Posts', // Recent Posts.
			'WP_Widget_RSS', // RSS.
			'WP_Widget_Tag_Cloud', // Tag Cloud.
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
	 * then we stop the plugin from removing the widget.
	 *
	 * @since 0.4.0
	 *
	 * @param bool   $bool   true to show.
	 * @param string $widget the widget name.
	 *
	 * @return bool
	 */
	public function filter_widget_removal( $bool, $widget ) {

		// Remove Categories Widget.
		if ( 'WP_Widget_Categories' === $widget && dwpb_post_types_with_tax( 'category' ) ) {
			$bool = false;
		}

		// Remove Recent Comments Widget if posts are the only type with comments.
		if ( 'WP_Widget_Recent_Comments' === $widget && dwpb_post_types_with_feature( 'comments' ) ) {
			$bool = false;
		}

		// Remove Tag Cloud.
		if ( 'WP_Widget_Tag_Cloud' === $widget && dwpb_post_types_with_tax( 'post_tag' ) ) {
			$bool = false;
		}

		return $bool;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since  0.4.0
	 *
	 * @return void
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../css/disable-blog-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Check that we're on a specific admin page.
	 *
	 * @since 0.4.8
	 *
	 * @param string $page the page slug.
	 *
	 * @return boolean
	 */
	private function is_admin_page( $page ) {

		global $pagenow;
		return is_admin() && isset( $pagenow ) && is_string( $pagenow ) && $page . '.php' === $pagenow;

	}

	/**
	 * Filter the comment counts to remove comments to 'post' post type.
	 *
	 * @since 0.4.0
	 * @since 0.4.3 Moved everything into the post id check and reset the cache.
	 *
	 * @param object $comments the comment count object.
	 * @param int    $post_id  the post id.
	 *
	 * @return object
	 */
	public function filter_wp_count_comments( $comments, $post_id ) {

		// if this is grabbing all the comments, filter out the 'post' comments.
		if ( 0 === $post_id ) {
			$comments = $this->get_comment_counts();

			$comments['moderated'] = $comments['awaiting_moderation'];
			unset( $comments['awaiting_moderation'] );

			$comments = (object) $comments;
			wp_cache_set( 'comments-0', $comments, 'counts' );
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
	 * @param object $comments the array of comments.
	 * @param int    $post_id  the post id.
	 *
	 * @return array
	 */
	public function filter_woocommerce_comment_count( $comments, $post_id ) {

		if ( 0 === $post_id && class_exists( 'WC_Comments' ) && function_exists( 'WC' ) && version_compare( WC()->version, '2.6.2', '<=' ) ) {
			$comments = (array) $comments;
		}

		return $comments;

	}

	/**
	 * Alter the comment counts on the admin comment table to remove comments associated with posts.
	 *
	 * @since 0.4.0
	 *
	 * @param array $views all the views.
	 *
	 * @return array
	 */
	public function filter_admin_table_comment_count( $views ) {

		global $current_screen;

		if ( 'edit-comments' === $current_screen->id ) {

			$updated_counts = $this->get_comment_counts();
			foreach ( $views as $view => $text ) {
				if ( isset( $updated_counts[ $view ] ) ) {
					$views[ $view ] = preg_replace( '/\([^)]+\)/', '(<span class="' . $view . '-count">' . $updated_counts[ $view ] . '</span>)', $views[ $view ] );
				}
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
	 * @return array
	 */
	public function get_comment_counts() {

		global $wpdb;

		// Grab the comments that are not associated with 'post' post_type.
		$totals = (array) $wpdb->get_results(
			"SELECT comment_approved, COUNT( * ) AS total
			FROM {$wpdb->comments}
			WHERE comment_post_ID in (
					SELECT ID
					FROM {$wpdb->posts}
					WHERE post_type != 'post'
					AND post_status = 'publish')
			GROUP BY comment_approved",
			ARRAY_A
		);

		$comment_count = array(
			'moderated'           => 0,
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
					$comment_count['spam']            = $row['total'];
					$comment_count['total_comments'] += $row['total'];
					break;
				case '1':
					$comment_count['approved']        = $row['total'];
					$comment_count['total_comments'] += $row['total'];
					$comment_count['all']            += $row['total'];
					break;
				case '0':
					$comment_count['awaiting_moderation'] = $row['total'];
					$comment_count['moderated']           = $comment_count['awaiting_moderation'];
					$comment_count['total_comments']     += $row['total'];
					$comment_count['all']                += $row['total'];
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
	 * @param bool $open    true if comments are open.
	 * @param int  $post_id the post id.
	 *
	 * @return bool
	 */
	public function filter_comment_status( $open, $post_id ) {

		return ( 'post' === get_post_type( $post_id ) ) ? false : $open;

	}

	/**
	 * Clear comments from 'post' post type.
	 *
	 * @since 0.4.0
	 *
	 * @param array $comments the array of comments.
	 * @param int   $post_id  the post id.
	 *
	 * @return array
	 */
	public function filter_existing_comments( $comments, $post_id ) {

		return ( 'post' === get_post_type( $post_id ) ) ? array() : $comments;

	}

}
