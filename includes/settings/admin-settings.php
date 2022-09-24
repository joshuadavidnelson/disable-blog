<?php
/**
 * Admin Settings.
 *
 * @package    Disable_Blog
 * @subpackage Settings/Admin_Settings
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 **/

/**
 * Prevent direct access to this file.
 *
 * @since 0.2
 **/
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

/**
 * Admin settings.
 *
 * @since 0.6.0
 */
class Disable_Blog_Admin_Settings {

	/**
	 * Add the hooks and filters.
	 */
	public function hooks() {

		// Filter the settings.
		add_filter( 'wpsf_register_settings_disable-blog', array( $this, 'admin_settings' ) );

		// Filter admin menu location.
		add_filter( 'wpsf_menu_position_disable-blog', array( $this, 'menu_location' ) );

	}

	/**
	 * Filter the framework default admin menu item position.
	 *
	 * @param int $position The default position.
	 * @return int
	 */
	public function menu_location( $position ) {
		return 1;
	}

	/**
	 * Admin settings.
	 *
	 * @since 0.6.0
	 * @param array $wpsf_settings The WP Settings Framework array.
	 * @return array
	 */
	public function admin_settings( $wpsf_settings ) {

		// Get the current theme for version information.
		$current_theme = wp_get_theme();
		$pages         = $this->get_post_type_options( 'page', array(), false );
		$page_on_front = ( 'page' === get_option( 'show_on_front' ) ) ? absint( get_option( 'page_on_front' ) ) : 0;
		$posts_page    = get_option( 'page_for_posts' );

		// Update the settings array,
		// note that the "homepage" is the default, so we want to remove
		// the page id as the key in case it changes, while also not providing
		// the page set as the homepage as a separate option.
		if ( $page_on_front && isset( $pages[ $page_on_front ] ) ) {

			// Capture the page name for safekeeping.
			$page_name = $pages[ $page_on_front ];

			// Unset the numerical value for the page.
			unset( $pages[ $page_on_front ] );

			// Merge the default "home" option with the array using the homepage name.
			$redirect_options = array_replace(
				array( 'home' => __( 'Homepage', 'disable-blog' ) ),
				$pages
			);

			// Remove the blog page from the options, can't redirect to that!
			if ( isset( $redirect_options[ $posts_page ] ) ) {
				unset( $redirect_options[ $posts_page ] );
			}
		} else {
			$redirect_options = array();
		}

		// Settings.
		$wpsf_settings['sections'] = array(
			array(
				'tab_id'        => 'general',
				'section_id'    => 'blog',
				'section_title' => __( 'Blog settings', 'disable-blog' ),
				'section_order' => 10,
				'fields'        => array(
					array(
						'id'    => 'disable_blog',
						'title' => __( 'Disable Blog', 'disable-blog' ),
						'desc'  => __( 'Disable the WordPress blog and remove the "post" content type from all things.', 'disable-blog' ),
						'type'  => 'toggle',
					),
					array(
						'id'      => 'front_end_redirect_id',
						'title'   => __( 'Redirect public urls to', 'disable-blog' ),
						'desc'    => __( 'Select where to redirect all disabled front-end urls. The default is to redirect to the homepage, set in Settings > Reading.', 'disable-blog' ),
						'type'    => 'select',
						'choices' => $redirect_options,
						'default' => 'home',
						'show_if' => array(
							array(
								'field' => 'disable_blog',
								'value' => array( true ),
							),
						),
					),
					array(
						'id'      => 'admin_redirect',
						'title'   => __( 'Redirect admin urls to', 'disable-blog' ),
						'desc'    => __( 'Select where to redirect all disabled admin links. The default is to redirect to the dashboard.', 'disable-blog' ),
						'type'    => 'select',
						'choices' => array(
							'dashboard' => __( 'Dashboard', 'disable-blog' ),
							'home'      => __( 'Homepage', 'disable-blog' ),
							'pages'     => __( 'Pages screen', 'disable-blog' ),
						//	'custom'    => __( 'Custom url', 'disable-blog' ),
						),
						'default' => 'dashboard',
						'show_if' => array(
							array(
								'field' => 'disable_blog',
								'value' => array( true ),
							),
						),
					),
					array(
						'id'      => 'disable_writing_options',
						'title'   => __( 'Disable Writing Options', 'disable-blog' ),
						'desc'    => __( 'Remove the Settings > Writing page from the menu and redirect the link to the dashboard. This page is not disabled by default because other plugins, like Classic Editor, extend this page.', 'disable-blog' ),
						'type'    => 'toggle',
						'show_if' => array(
							array(
								'field' => 'disable_blog',
								'value' => array( true ),
							),
						),
					),
				),
			),
			array(
				'tab_id'        => 'general',
				'section_id'    => 'authors',
				'section_title' => __( 'Author Archive Pages', 'disable-blog' ),
				'section_order' => 10,
				'fields'        => array(
					array(
						'id'    => 'disable_author_archive',
						'title' => __( 'Disable Author Archives', 'disable-blog' ),
						'desc'  => __( 'Disable author archives, user sitemaps, prevents user enumeration, and redirects author archive urls.', 'disable-blog' ),
						'type'  => 'toggle',
					),
					array(
						'id'      => 'author_redirect_id',
						'title'   => __( 'Redirect author archives to', 'disable-blog' ),
						'desc'    => __( 'If disabling author archives, use this option to select where to redirect the author archive urls. The default is to redirect to the homepage, set in Settings > Reading.', 'disable-blog' ),
						'type'    => 'select',
						'choices' => $redirect_options,
						'default' => 'home',
						'show_if' => array(
							array(
								'field' => 'disable_author_archive',
								'value' => array( true ),
							),
						),
					),
				),
			),
						'type'  => 'toggle',
		);

		return $wpsf_settings;

	}

	/**
	 * Get a list of choices for select, multicheck, or radio options from post types.
	 *
	 * @since 0.6.0
	 * @param string  $post_type The post type slug.
	 * @param array   $args      Custom arguments to pass into the query.
	 * @param boolean $none      True to include a null, "select a {post_type}" option.
	 * @return array $options
	 */
	public function get_post_type_options( $post_type, $args = array(), $none = true ) {

		$defaults = array(
			'post_type'              => $post_type,
			'post_status'            => array( 'publish' ),
			'suppress_filters'       => false,
			'posts_per_page'         => 100,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
		);
		$args     = wp_parse_args( $args, $defaults );

		$the_query = new \WP_Query( $args );
		$options   = array();

		// The Loop.
		if ( $the_query->have_posts() ) {
			if ( $none ) {
				$post_type_obj = get_post_type_object( $post_type );
				if ( isset( $post_type_obj->labels->singular_name ) ) {
					// translators: %s is the singular name of the post type being selected.
					$select_text = sprintf( __( 'Select a %s', 'disable-blog' ), $post_type_obj->labels->singular_name );
					$options     = array( '' => $select_text );
				}
			}
			foreach ( $the_query->posts as $post_id ) {
				$options[ $post_id ] = get_the_title( $post_id );
			}
		}

		return $options;

	}
}

$_dwpb_admin_settings = new Disable_Blog_Admin_Settings();
$_dwpb_admin_settings->hooks();
