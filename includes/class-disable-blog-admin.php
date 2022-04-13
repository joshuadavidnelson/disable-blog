<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.0
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog_Admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and contains all the admin functions.
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
	 * @since 0.4.0
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->functions   = new Disable_Blog_Functions();

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
	 * @uses dwpb_post_types_with_tax()
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
	 * @since 0.1.0
	 * @since 0.4.0 added single post edit screen redirect
	 * @since 0.5.0 condensed page rediects into a foreach loop with common structure
	 *               for filters and functions used to check redirect conditions.
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
		$dashboard_url = admin_url( 'index.php' );
		$redirect_url  = false;

		// The admin page slugs to potentially be redirected.
		$admin_redirects = array(
			'post',
			'edit',
			'post-new',
			'edit-tags',
			'term',
			'edit-comments',
			'options-discussion',
			'options-writing',
			'options-tools',
		);

		// cycle through each admin page, checking if we need to redirect.
		foreach ( $admin_redirects as $pagename ) {

			// Filter names are all underscores.
			$filternme = str_replace( '-', '_', $pagename );

			// Custom function within this class used to check if the page needs to be redirected.
			$function = 'redirect_admin_' . $filternme;

			// build the filter.
			$filter = 'dwpb_' . $function;

			// If this is the right page, then setup the redirect url.
			// make sure we're on that page right now.
			// make sure the function used to check/provide the url is callable.
			if ( $this->is_admin_page( $pagename )
				&& is_callable( array( $this, $function ) ) ) {

				// Check the function for redirect clearance, or custom url.
				$redirect = $this->$function();

				// Set a redirect url variable to check against.
				$potential_redirect_url = esc_url_raw( $redirect );

				// If it's set to `true` then redirect to the dashboard,
				// if it's set to a url, redirect to that url.
				if ( true === $redirect || ! empty( $potential_redirect_url ) ) {

					// Either this is a custom redirect url or 'true', which defaults the url to the dashboard.
					if ( ! empty( $potential_redirect_url ) ) {
						$url = $potential_redirect_url;
					} else {
						$url = $dashboard_url;
					}

					/**
					 * The redirect url used for this admin page.
					 *
					 * Example: use 'dwpb_redirect_post' to change the redirect url
					 * used for the post.php page.
					 *
					 * @since 0.4.0
					 * @since 0.5.0 combine common filters.
					 * @param string $url the url to redirct to, defaults to dashboard.
					 */
					$redirect_url = apply_filters( $filter, $url );

					break; // no need to keep looping.

				}
			}
		}

		/**
		 * Global admin url redirect filter.
		 *
		 * @since 0.5.0
		 * @param string $redirect_url The redirect url.
		 */
		$redirect_url = apply_filters( 'dwpb_admin_redirect_url', $redirect_url );

		/**
		 * Redirect blog related admin pages.
		 *
		 * @since 0.4.0
		 * @since 0.5.0 removed 3rd `$current_url` param.
		 * @param bool   $bool         True to enable, default is true.
		 * @param string $redirect_url The url to being used in the redirect.
		 */
		if ( $redirect_url && apply_filters( 'dwpb_redirect_admin', true, $redirect_url ) ) {
			$this->functions->redirect( $redirect_url );
		}

	}

	/**
	 * The admin redirect arguments checked to redirect the post.php screen.
	 *
	 * @since 0.5.0
	 * @return bool
	 */
	public function redirect_admin_post() {

		// @codingStandardsIgnoreStart - phpcs wants to sanitize this, but it's not necessary.
		return ( isset( $_GET['post'] ) && 'post' == get_post_type( $_GET['post'] ) );
		// @codingStandardsIgnoreEnd

	}

	/**
	 * The admin redirect arguments checked to redirect the edit.php screen.
	 *
	 * @since 0.5.0
	 * @return bool|string
	 */
	public function redirect_admin_edit() {

		// @codingStandardsIgnoreStart - phpcs wants to sanitize this, but it's not necessary.
		if ( ! isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) {
		// @codingStandardsIgnoreEnd

			return admin_url( 'edit.php?post_type=page' );

		}

		return false;

	}

	/**
	 * The admin redirect arguments checked to redirect the post-new.php screen.
	 *
	 * @since 0.5.0
	 * @return bool|string
	 */
	public function redirect_admin_post_new() {

		// @codingStandardsIgnoreStart - phpcs wants to sanitize this, but it's not necessary.
		if ( ( ! isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) ) {
		// @codingStandardsIgnoreEnd

			return admin_url( 'post-new.php?post_type=page' );

		}

		return false;

	}

	/**
	 * The admin redirect arguments checked to redirect the term.php screen.
	 *
	 * @since 0.5.0
	 * @return bool|string
	 */
	public function reidrect_admin_term() {

		// @codingStandardsIgnoreStart - phpcs wants to sanitize this, but it's not necessary.
		return ( isset( $_GET['taxonomy'] ) && ! dwpb_post_types_with_tax( $_GET['taxonomy'] ) );
		// @codingStandardsIgnoreEnd

	}

	/**
	 * The admin redirect arguments checked to redirect the edit-tags.php screen.
	 *
	 * @since 0.5.0
	 * @return bool|string
	 */
	public function redirect_admin_edit_tags() {

		// @codingStandardsIgnoreStart - phpcs wants to sanitize this, but it's not necessary.
		return ( isset( $_GET['taxonomy'] ) && ! dwpb_post_types_with_tax( $_GET['taxonomy'] ) );
		// @codingStandardsIgnoreEnd

	}

	/**
	 * The admin redirect arguments checked to redirect the edit-comments.php screen.
	 *
	 * @uses dwpb_post_types_with_feature()
	 * @since 0.5.0
	 * @return bool
	 */
	public function redirect_admin_edit_comments() {

		/**
		 * Will only work comments are only supported by 'post' type,
		 * note that pages and attachments support comments by default.
		 */
		return ! dwpb_post_types_with_feature( 'comments' );

	}

	/**
	 * The admin redirect arguments checked to redirect the options-discussion.php screen.
	 *
	 * The same checks are performed by the edit-comments check, so this is a wrapper function.
	 *
	 * @since 0.5.0
	 * @return bool
	 */
	public function redirect_admin_options_discussion() {

		return $this->redirect_admin_edit_comments();

	}

	/**
	 * The admin redirect arguments checked to redirect the options-writing.php screen.
	 *
	 * @since 0.5.0
	 * @return bool|string
	 */
	public function redirect_admin_options_writing() {

		// Redirect writing options to general options.
		if ( $this->remove_writing_options() ) {

			return admin_url( 'options-general.php' );

		}

		return false;

	}

	/**
	 * The admin redirect arguments checked to redirect the options-tools.php screen.
	 *
	 * @since 0.5.0
	 * @return bool
	 */
	public function redirect_admin_options_tools() {

		/**
		 * The isset( $_GET['page'] ) check is to confirm the page
		 * isn't a 3rd party plugin's option page built into the tools page.
		 */
		// @codingStandardsIgnoreStart - phpcs wants to nounce this, but that's not needed.
		return ! isset( $_GET['page'] );
		// @codingStandardsIgnoreEnd

	}

	/**
	 * Remove the X-Pingback HTTP header.
	 *
	 * @since 0.4.0
	 * @param array $headers the pingback headers.
	 * @return array
	 */
	public function filter_wp_headers( $headers ) {

		/**
		 * Toggle the disable pinback header feature.
		 *
		 * @since 0.4.0
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
	 * @link http://wordpress.stackexchange.com/questions/57464/remove-posts-from-admin-but-show-a-custom-post
	 * @since 0.1.0
	 * @since 0.4.0 added tools and discussion subpages.
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
		 * @param array $remove_pages Array of page strings.
		 */
		$pages = apply_filters( 'dwpb_menu_pages_to_remove', $remove_pages );
		foreach ( $pages as $page ) {
			remove_menu_page( $page );
		}

		// Submenu Pages.
		$remove_subpages = array(
			'tools.php'           => array( 'tools.php' ),
			'options-general.php' => array(),
		);

		// Remove the writings page, if the filter tells us so.
		if ( $this->remove_writing_options() ) {
			$remove_subpages['options-general.php'][] = 'options-writing.php';
		}

		// If there are no other post types supporting comments, remove the discussion page.
		if ( ! dwpb_post_types_with_feature( 'comments' ) ) {
			$remove_subpages['options-general.php'][] = 'options-discussion.php'; // Settings > Discussion.
		}

		/**
		 * Admin subpages to be removed.
		 *
		 * @since 0.4.0
		 * @since 0.5.0 in order to account for mulitple subpages with a common parent
		 *               the `subpages` are now in arrays
		 * @param array $remove_subpages Array of page => subpages where subpages is an array of strings.
		 */
		$subpages = apply_filters( 'dwpb_menu_subpages_to_remove', $remove_subpages );
		foreach ( $subpages as $page => $subpages ) {
			if ( is_array( $subpages ) && ! empty( $subpages ) ) {
				foreach ( $subpages as $subpage ) {
					remove_submenu_page( $page, $subpage );
				}
			} elseif ( is_string( $subpages ) ) { // for backwards compatibility.
				remove_submenu_page( $page, $subpages );
			}
		}

	}

	/**
	 * Filter the body classes for admin screens to toggle on plugin specific styles.
	 *
	 * @since 0.4.7
	 * @param string $classes the admin body classes, which is a string *not* an array.
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
	 * @return bool
	 */
	public function remove_writing_options() {

		/**
		 * Toggle the options-writing page on/off.
		 *
		 * Defaults to false because other plugins often extend this page.
		 * Setting this to true will create a redirect for the page
		 * and remove it from the admin menu.
		 *
		 * See: https://wordpress.org/support/topic/disabling-writing-settings-panel-is-a-problem/
		 *
		 * @since 0.4.5
		 * @since 0.5.0 renamed from `dwpb_redirect_admin_options_writing`
		 *               to `dwpb_remove_options_writing`. The old filter name
		 *               is not used by the admin redirect function to filter
		 *               the redirect url used for this page.
		 * @param bool $bool Defaults to false, keeping the writing page visible.
		 */
		return apply_filters( 'dwpb_remove_options_writing', false );

	}

	/**
	 * Remove blog-related admin bar links
	 *
	 * @uses dwpb_post_types_with_feature()
	 * @link http://www.paulund.co.uk/how-to-remove-links-from-wordpress-admin-bar
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 * @since 0.4.1 dry out the code with a foreach loop
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

			printf( '<div class="%s"><p>%s</p></div>', 'notice notice-error', wp_kses_post( $message ) );

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
	 * @return bool
	 */
	public function has_front_page() {

		return 'page' === get_option( 'show_on_front' ) && absint( get_option( 'page_on_front' ) );

	}

	/**
	 * Kill the Press This functionality
	 *
	 * @since 0.2.0
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
	 * @uses dwpb_post_types_with_feature()
	 * @since 0.4.0
	 * @param bool   $bool   true to show.
	 * @param string $widget the widget name.
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
	 * @return void
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../assets/css/disable-blog-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the scripts used in the admin area.
	 *
	 * @since  0.5.0
	 * @return void
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../assets/js/disable-blog-admin.js', array(), $this->version, true );

		// Localize some information on the page.
		global $pagenow;
		$js_vars = array(
			'page'                => str_replace( '.php', '', $pagenow ),
			'categoriesSupported' => (bool) dwpb_post_types_with_tax( 'category' ),
			'tagsSupported'       => (bool) dwpb_post_types_with_tax( 'post_tag' ),
			'commentsSupported'   => (bool) dwpb_post_types_with_feature( 'comments' ),
		);
		wp_localize_script( $this->plugin_name, 'dwpb', $js_vars );

	}

	/**
	 * Check that we're on a specific admin page.
	 *
	 * @since 0.4.8
	 * @param string $page the page slug.
	 * @return boolean
	 */
	public function is_admin_page( $page ) {

		global $pagenow;

		return is_admin() && isset( $pagenow ) && is_string( $pagenow ) && $page . '.php' === $pagenow;

	}

	/**
	 * Filter the comment counts to remove comments to 'post' post type.
	 *
	 * @since 0.4.0
	 * @since 0.4.3 Moved everything into the post id check and updated caching functions to match wp_get_comments.
	 *
	 * @see wp_get_comments()
	 * @param object $comments the comment count object.
	 * @param int    $post_id  the post id.
	 * @return object
	 */
	public function filter_wp_count_comments( $comments, $post_id ) {

		// if this is grabbing all the comments, filter out the 'post' comments.
		if ( 0 === $post_id ) {

			// Keep caching in place, note that on activation the plugin clears this cache.
			// see class-disable-blog-activator.php.
			$count = wp_cache_get( 'comments-0', 'counts' );
			if ( false !== $count ) {
				return $count;
			}

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
	 * @param object $comments the array of comments.
	 * @param int    $post_id  the post id.
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
	 * @param array $views all the views.
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
	 * @see get_comment_count()
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

		return array_map( 'intval', $comment_count );

	}

	/**
	 * Clear out the status of all post comments.
	 *
	 * @since 0.4.0
	 * @param bool $open    true if comments are open.
	 * @param int  $post_id the post id.
	 * @return bool
	 */
	public function filter_comment_status( $open, $post_id ) {

		return ( 'post' === get_post_type( $post_id ) ) ? false : $open;

	}

	/**
	 * Clear comments from 'post' post type.
	 *
	 * @since 0.4.0
	 * @param array $comments the array of comments.
	 * @param int   $post_id  the post id.
	 * @return array
	 */
	public function filter_existing_comments( $comments, $post_id ) {

		return ( 'post' === get_post_type( $post_id ) ) ? array() : $comments;

	}

	/**
	 * Filters the default post display states used in the posts list table.
	 *
	 * @since 0.5.0
	 * @param string[] $post_states An array of post display states.
	 * @param WP_Post  $post        The current post object.
	 * @return array
	 */
	public function page_post_states( $post_states, $post ) {

		if ( $this->has_front_page() && absint( get_option( 'page_for_posts' ) ) === $post->ID ) {
			// translators: This string is used to indicate that the blog page is redirected to the homepage.
			$post_states['dwpb-redirected'] = __( 'Redirected to the homepage', 'disable-blog' );
		}

		return $post_states;
	}

	/**
	 * Removes the Site Health check for the post REST API.
	 *
	 * @uses dwpb_get_test_rest_availability()
	 * @since 0.5.0
	 * @param array $tests the tests, of course.
	 * @return array
	 */
	public function site_status_tests( $tests ) {

		if ( isset( $tests['direct']['rest_availability'] ) && is_callable( array( $this, 'get_test_rest_availability' ) ) ) {
			$tests['direct']['rest_availability']['test'] = array( $this, 'get_test_rest_availability' );
		}

		return $tests;

	}

	/**
	 * Replaces the core REST Availability Site Health check.
	 *
	 * Used by the site_status_tests filter in class-disable-blog-admin.php.
	 *
	 * Copied directly from https://developer.wordpress.org/reference/classes/wp_site_health/get_test_rest_availability/ but with the 'post' type updated to 'page' in the rest url.
	 *
	 * @see https://make.wordpress.org/core/2019/04/25/site-health-check-in-5-2/
	 * @since 0.5.0
	 * @return array
	 */
	public function get_test_rest_availability() {

		$result = array(
			'label'       => __( 'The REST API is available' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'The REST API is one way WordPress, and other applications, communicate with the server. One example is the block editor screen, which relies on this to display, and save, your posts and pages.' )
			),
			'actions'     => '',
			'test'        => 'rest_availability',
		);

		$cookies = wp_unslash( $_COOKIE );
		$timeout = 10;
		$headers = array(
			'Cache-Control' => 'no-cache',
			'X-WP-Nonce'    => wp_create_nonce( 'wp_rest' ),
		);

		/** This filter is documented in wp-includes/class-wp-http-streams.php */
		$sslverify = apply_filters( 'https_local_ssl_verify', false );

		// Include Basic auth in loopback requests.
		// @codingStandardsIgnoreStart
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		}
		// @codingStandardsIgnoreEnd

		// -- here's the money, change this from 'post' to  'page'.
		$url = rest_url( 'wp/v2/types/page' );

		// The context for this is editing with the new block editor.
		$url = add_query_arg(
			array(
				'context' => 'edit',
			),
			$url
		);

		$r = wp_remote_get( $url, compact( 'cookies', 'headers', 'timeout', 'sslverify' ) );

		if ( is_wp_error( $r ) ) {
			$result['status'] = 'critical';

			$result['label'] = __( 'The REST API encountered an error' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					'%s<br>%s',
					__( 'The REST API request failed due to an error.' ),
					sprintf(
						/* translators: 1: The WordPress error message. 2: The WordPress error code. */
						__( 'Error: %1$s (%2$s)' ),
						$r->get_error_message(),
						$r->get_error_code()
					)
				)
			);
		} elseif ( 200 !== wp_remote_retrieve_response_code( $r ) ) {
			$result['status'] = 'recommended';

			$result['label'] = __( 'The REST API encountered an unexpected result' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: 1: The HTTP error code. 2: The HTTP error message. */
					__( 'The REST API call gave the following unexpected result: (%1$d) %2$s.' ),
					wp_remote_retrieve_response_code( $r ),
					esc_html( wp_remote_retrieve_body( $r ) )
				)
			);
		} else {
			$json = json_decode( wp_remote_retrieve_body( $r ), true );

			if ( false !== $json && ! isset( $json['capabilities'] ) ) {
				$result['status'] = 'recommended';

				$result['label'] = __( 'The REST API did not behave correctly' );

				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %s: The name of the query parameter being tested. */
						__( 'The REST API did not process the %s query parameter correctly.' ),
						'<code>context</code>'
					)
				);
			}
		}

		return $result;

	}

	/**
	 * Filter the taxonomy count on post_tag and category screens.
	 *
	 * Used on the post_tag and category screens for custom post types.
	 *
	 * @since 0.5.0
	 * @param array  $actions an array of actions.
	 * @param object $tag     the current taxonomy object.
	 * @return array
	 */
	public function filter_taxonomy_count( $actions, $tag ) {

		if ( isset( $tag->taxonomy, $tag->count, $tag->term_id )
			&& ( 'post_tag' === $tag->taxonomy || 'category' === $tag->taxonomy ) ) {
			$screen     = get_current_screen();
			$count      = $this->get_term_post_count_by_type( $tag->term_id, $tag->taxonomy, $screen->post_type );
			$tag->count = $count;
		}

		return $actions;

	}

	/**
	 * Return the post count for a term based by post type.
	 *
	 * @since 0.5.0
	 * @param int    $term_id   the current term id.
	 * @param string $taxonomy  the taxonomy slug.
	 * @param string $post_type the post type slug.
	 * @return int
	 */
	public function get_term_post_count_by_type( $term_id, $taxonomy, $post_type ) {

		$args  = array(
			'fields'                 => 'ids',
			'posts_per_page'         => 500,
			'post_type'              => $post_type,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'id',
					'terms'    => intval( $term_id ),
				),
			),
		);
		$query = new WP_Query( $args );

		if ( count( $query->posts ) > 0 ) {
			return count( $query->posts );
		} else {
			return 0;
		}
	}

	/**
	 * Remove posts column form user table.
	 *
	 * @since 0.5.0
	 * @param array $columns the column slugs => title array.
	 * @return array
	 */
	public function manage_users_columns( $columns ) {

		/**
		 * Disable the user post column.
		 *
		 * @since 0.5.0
		 * @param bool $bool True to remove the column, defaults to true.
		 * @return bool
		 */
		$disable_user_posts_column = apply_filters( 'dpwb_disable_user_post_column', true );

		if ( isset( $columns['posts'] ) && true === (bool) $disable_user_posts_column ) {
			unset( $columns['posts'] );
		}

		// Get the current screen.
		$screen = get_current_screen();

		// cycle through the post types to be displayed.
		$post_types = $this->user_column_post_types();
		foreach ( $post_types as $post_type ) {
			/**
			 * Create a new column for 'pages' similar to the orginal 'post' column.
			 *
			 * @since 0.5.0
			 * @param bool $bool True to remove the column, defaults to true.
			 * @return bool
			 */
			if ( apply_filters( "dpwb_create_user_{$post_type}_column", true )
				// Taken from core functions for users page, don't display the posts column on site-users-network core page.
				// see wp-admin/includes/class-wp-users-list-table.php.
				&& isset( $screen->id ) && 'site-users-network' !== $screen->id ) {

				$post_type_obj = get_post_type_object( $post_type );
				if ( isset( $post_type_obj->labels->name ) ) {
					$columns[ $post_type ] = $post_type_obj->labels->name;
				}
			}
		}

		return $columns;

	}

	/**
	 * Mange the new custom user columns.
	 *
	 * @since 0.5.0
	 * @see wp-admin/includes/class-wp-users-list-table.php.
	 * @param string $output      the column output.
	 * @param string $column_name the current column slug.
	 * @param int    $user_id     the current user's id.
	 * @return string
	 */
	public function manage_users_custom_column( $output, $column_name, $user_id ) {

		$post_types = $this->user_column_post_types();
		foreach ( $post_types as $post_type ) {
			// Create new column output, mimicking core's 'post' column but for the 'page' and supported custom post types.
			if ( $post_type === $column_name ) {

				// make sure we can grab valid post type labels before continuing.
				$post_type_obj = get_post_type_object( $post_type );
				if ( ! isset( $post_type_obj->labels->name, $post_type_obj->labels->singular_name ) ) {
					continue;
				}

				// Get the couunts.
				$page_counts = count_many_users_posts( array( $user_id ), $post_type );
				$page_count  = absint( $page_counts[ $user_id ] );

				// Note that we have to explicitly add the query variable for post type in order
				// to avoid it being stripped by the admin redirect on the edit.php page.
				// @codingStandardsIgnoreStart - phpcs doesn't like the mismatched placeholders, but it works.
				$output = sprintf(
					'<a href="%s" class="edit"><span aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
					"edit.php?post_type={$post_type}&author={$user_id}",
					$page_count,
					sprintf(
						// translators: %1$s: Number of pieces of content by this author. %2$s and %3$s are the singular and plural names, respectively, for the content type. For example: "1 page by this author" and "2 pages by this author".
						_n( '%1$s %2$s by this author', '%1$s %3$s by this author', $page_count, 'disable-blog' ),
						number_format_i18n( $page_count ),
						$post_type_obj->labels->singular_name,
						$post_type_obj->labels->name
					)
				);
				// @codingStandardsIgnoreEnd
			}
		}

		return $output;

	}

	/**
	 * Grab the post types that
	 *
	 * @since 0.5.0
	 * @return array
	 */
	private function user_column_post_types() {

		// Include any post types using author archives.
		$post_types = $this->functions->author_archive_post_types();

		// The author_archive_post_type function returns false if empy, but we need an array.
		$post_types = empty( $post_types ) ? array() : $post_types;

		// Also include pages, which is not in the author archive by default,
		// however we run array_unique in case the 'page' post type has been added
		// to the author archives.
		$post_types = array_unique( array_merge( array( 'page' ), $post_types ) );

		/**
		 * Filter the post types that appear in the user table.
		 *
		 * @since 0.5.0
		 * @param array $post_types an array of post type slugs.
		 * @return array
		 */
		return apply_filters( 'dwpb_admin_user_post_types', $post_types );

	}

	/**
	 * Remove the user's "view" link if we are not supporting author archives.
	 *
	 * @since 0.5.0
	 * @param array  $actions     an array of actions in a key => output format.
	 * @param object $user_object the current user, WP_User object.
	 * @return array
	 */
	public function user_row_actions( $actions, $user_object ) {

		if ( true === $this->functions->disable_author_archives()
			&& isset( $actions['view'] ) ) {
				unset( $actions['view'] );
		}

		return $actions;

	}

	/**
	 * Alter the customizer view to mimic the reading settings.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function customizer_styles() {

		if ( $this->has_front_page() ) {
			?>
			<style>
				#customize-theme-controls #customize-control-genesis_trackbacks_posts,
				#customize-theme-controls #customize-control-genesis_comments_posts,
				#customize-theme-controls #customize-control-show_on_front,
				#customize-theme-controls #customize-control-page_for_posts {
					display: none !important;
				}
			</style>
			<?php
		}

	}

	/**
	 * Remove the default posts page notice and replace it with a new one.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function update_posts_page_notice() {

		if ( (int) get_option( 'page_for_posts' ) === get_the_ID() ) {
			remove_action( 'edit_form_after_title', '_wp_posts_page_notice' );
			add_action( 'edit_form_after_title', array( $this, 'posts_page_notice' ) );
		}

	}

	/**
	 * Replaces the default blog page posts notice.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function posts_page_notice() {

		// translators: this notice informs the user why the blog page editor is disabled and that it is redirected to the homepage.
		echo '<div class="notice notice-warning inline"><p>' . __( 'You are currently editing the page that shows your latest posts, which is redirected to the homepage because the blog is disabled.', 'disable-blog' ) . '</p></div>'; // phpcs:ignore

	}
}
