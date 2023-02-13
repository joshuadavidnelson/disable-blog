<?php
/**
 * Implement admin settings via the plugin's hooks and filters.
 *
 * @package    Disable_Blog
 * @subpackage Settings/Settings_Hooks
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
 * Main class, contains the settings related filters and functions.
 *
 * @since 0.6.0
 */
class Disable_Blog_Settings_Hooks {

	/**
	 * The plugin settings value.
	 *
	 * @since 0.6.0
	 * @var array
	 */
	protected $options;

	/**
	 * The plugin settings default values.
	 *
	 * @since 0.6.0
	 * @var array
	 */
	protected $defaults;

	/**
	 * Build the class variables.
	 *
	 * @since 0.6.0
	 * @param array $options  The current option values.
	 * @param array $defaults The default option values.
	 */
	public function __construct( $options, $defaults ) {

		$this->options  = $options;
		$this->defaults = $defaults;

	}

	/**
	 * This function holds all the action and filter calls.
	 *
	 * @since 0.6.0
	 * @return void
	 */
	public function hooks() {

		/**
		 * Option mapping 101: each option is a key in this array, with an array of properties: filter, default, and callback.
		 *
		 * The 'filter' is the plugin filter used to change the plugin's behavoir.
		 * The 'default' is the plugin's default option - what the plugin does by default, with no change.
		 * The 'callback' is the function called when the user settings does ~~not~~ match the default.
		 *
		 * The idea here is that by default options do not require a filter call because it's how the plugin works *by default*
		 * but if the setting is different then call the function to change the behavior of the plugin.
		 *
		 * Because most settings are checkboxes, we can use the built-in '__return_false' or '__return_true' as callbacks if not provided.
		 */
		$option_map = array(
			'disable_blog'            => array(
				'filter' => 'dwpb_disable_blog',
			),
			'disable_author_archive'  => array(
				'filter' => 'dwpb_disable_author_archives',
			),
			'front_end_redirect_id'   => array(
				'filter'   => 'dwpb_front_end_redirect_url',
				'callback' => 'front_end_redirect_url',
			),
			'author_redirect_id'      => array(
				'filter'   => 'dwpb_redirect_author_archive',
				'callback' => 'author_redirect_url',
			),
			'admin_redirect'          => array(
				'filter'   => 'dwpb_admin_redirect_url',
				'callback' => 'admin_redirect_url',
			),
			'disable_writing_options' => array(
				'filter' => 'dwpb_remove_options_writing',
			),
			'reorder_pages'           => array(
				'filter' => 'dwpb_reorder_admin_menu',
			),
			// 'show_settings'        => array(
			// 	'filter' => 'dwpb_show_settings_page',
			// ),
		);

		// Cycle through the options and place filters where needed.
		foreach ( $option_map as $setting_key => $filter_array ) {

			// Make sure all the variables are good to go.
			// If the setting is the default, then we can bail, no need to call the filter.
			if ( ! isset( $this->options[ $setting_key ], $this->defaults[ $setting_key ], $filter_array['filter'] )
				|| $this->defaults[ $setting_key ] === $this->options[ $setting_key ] ) {
					continue;
			}

			// If there isn't a callback set, then assume this is a bool default,
			// set the callback to the opposite __return function as the bool value.
			// If that's not the case, then bail.
			if ( ! isset( $filter_array['callback'] ) ) {
				if ( true === (bool) $this->options[ $setting_key ] ) {
					$filter_array['callback'] = '__return_true';
				} elseif ( false === (bool) $this->options[ $setting_key ] ) {
					$filter_array['callback'] = '__return_false';
				} else {
					continue;
				}
			}

			// Make sure the callback is valid.
			if ( is_callable( $filter_array['callback'] ) ) {

				// Put the filter in place.
				add_filter( $filter_array['filter'], $filter_array['callback'], 9 );

			} elseif ( is_callable( array( $this, $filter_array['callback'] ) ) ) {

				// Put the filter in place.
				add_filter( $filter_array['filter'], array( $this, $filter_array['callback'] ), 9 );

			}
		}

	}

	/**
	 * Filter the front-end redirect url.
	 *
	 * @since 0.6.0
	 * @param string $url the url used for redirects on the front-end.
	 * @return string
	 */
	public function front_end_redirect_url( $url ) {

		if ( is_author() ) {
			$page_id = absint( $this->options['author_redirect_id'] );
		} else {
			$page_id = absint( $this->options['front_end_redirect_id'] );
		}

		// Only filter the url if it's a valid, published page.
		if ( $page_id
			&& 'page' === get_post_type( $page_id )
			&& 'publish' === get_post_status( $page_id ) ) {

			$url = get_permalink( $page_id );

		}

		return $url;

	}

	/**
	 * Filter the front-end redirect url for the author archives.
	 *
	 * @since 0.6.0
	 * @param string $url the url used for redirects on the front-end.
	 * @return string
	 */
	public function author_redirect_url( $url ) {

		$page_id = absint( $this->options['author_redirect_id'] );

		// Only filter the url if it's a valid, published page.
		if ( $page_id
			&& 'page' === get_post_type( $page_id )
			&& 'publish' === get_post_status( $page_id ) ) {

			$url = get_permalink( $page_id );

		}

		return $url;

	}

	/**
	 * Filter the admin redirect url for the author archives.
	 *
	 * @since 0.6.0
	 * @param string $url the url used for redirects on the admin.
	 * @return string
	 */
	public function admin_redirect_url( $url ) {

		$page = absint( $this->options['admin_redirect'] );

		if ( 'dashboard' === $page ) {

			$url = admin_url();

		} elseif ( 'home' === $page ) {

			$url = home_url();

		} elseif ( 'pages' === $page ) {

			$url = admin_url( 'edit.php?post_type=page' );

		}

		return $url;

	}

	/**
	 * Filter the author archives
	 *
	 * @since 0.6.0
	 * @param bool $bool The bool option being filtered.
	 * @return bool
	 */
	public function return_option( $bool ) {

		return absint( $this->current_option );

	}

}
