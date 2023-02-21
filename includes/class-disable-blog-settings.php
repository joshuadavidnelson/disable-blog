<?php
/**
 * Admin Settings
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog_Settings
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2022, Joshua David Nelson
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link       http://joshuadnelson.com/scripts-to-footer-plugin
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
 * Main Settings Class
 *
 * @since 0.6.0
 */
class Disable_Blog_Settings {

	/**
	 * Holds the values of the settings/options array.
	 *
	 * @since 0.6.0
	 * @access private
	 * @var array
	 */
	private $options;

	/**
	 * The default values for the option array.
	 *
	 * @since 0.6.0
	 * @access private
	 * @var array
	 */
	private $defaults;

	/**
	 * The WP Settings Framework options groupd slug.
	 *
	 * @since 0.6.0
	 * @access public
	 * @var string
	 */
	public $options_group;

	/**
	 * The path to this file.
	 *
	 * @since 0.6.0
	 * @access private
	 * @var string
	 */
	private $path;

	/**
	 * An instance of the WP Settings Framework class.
	 *
	 * @since 0.6.0
	 * @access private
	 * @var object WordPressSettingsFramework
	 */
	private $wpsf;

	/**
	 * Start up
	 *
	 * @since 0.6.0
	 */
	public function __construct() {

		// Options group slug.
		$this->option_group         = 'disable-blog';
		$this->network_option_group = 'disable-blog-network';

		// Set default variable.
		$this->site_option_defaults = array(
			'disable_blog'            => 0,
			'front_end_redirect_id'   => 'home',
			'disable_author_archive'  => 0,
			'author_redirect'         => 'home',
			'admin_redirect_id'       => 'dashboard',
			'disable_writing_options' => 0,
			'reorder_pages'           => 0,
			'show_settings'           => 1,
		);

		// path to this file.
		$this->path = plugin_dir_path( __FILE__ );

		// Include and create a new WordPressSettingsFramework.
		require_once $this->path . 'settings/framework/wp-settings-framework.php';

		// Setup the site settings page.
		$this->site_settings = new WordPressSettingsFramework( $this->path . 'settings/site-settings.php', $this->option_group );

		// Setup the network settings page.
		if ( is_multisite() ) {
			$this->network_settings = new WordPressSettingsFramework( $this->path . 'settings/network-settings.php', $this->network_option_group );
		}

	}

	/**
	 * Add settings page.
	 */
	public function add_settings_page() {

		$this->site_settings->add_settings_page(
			array(
				'parent_slug' => 'options-general.php',
				'page_title'  => __( 'Blog Settings', 'disable-blog' ),
				'menu_title'  => __( 'Blog', 'disable-blog' ),
			)
		);

	}

	/**
	 * Validate each setting field.
	 *
	 * Same as $sanitize_callback from http://codex.wordpress.org/Function_Reference/register_setting
	 *
	 * @since 0.6.0
	 * @param array $input Contains all settings fields as array keys.
	 */
	public function validate_settings( $input ) {

		$new_input = array();

		if ( isset( $input['redirect_author_archive'] ) ) {
			$new_input['redirect_author_archive'] = (bool) absint( $input['redirect_author_archive'] );
		}

		return $input;

	}

	/**
	 * Initiate settings, like the function name implies.
	 *
	 * @since 0.6.0
	 * @return void
	 */
	public function initiate_settings() {

		$this->options = $this->get_options();

		if ( empty( $this->options ) ) {
			$this->set_defaults();
		}

		require_once $this->path . 'settings/settings-hooks.php';

		$settings_hooks = new Disable_Blog_Settings_Hooks( $this->options, $this->site_option_defaults );
		$settings_hooks->hooks();

	}

	/**
	 * Wrapper function used to get options.
	 *
	 * @since 0.6.0
	 * @return array
	 */
	private function get_options() {
		return get_option( $this->option_group . '_settings' );
	}

	/**
	 * Setup default settings options.
	 *
	 * @since 0.6.0
	 * @return void
	 */
	private function set_defaults() {

		$defaults_set = get_option( 'dwpb_defaults_set', false );

		if ( ! $defaults_set || empty( $this->get_options() ) ) {
			$this->options = $this->site_option_defaults;
			update_option( $this->option_group . '_settings', $this->options, false );
			update_option( 'dwpb_defaults_set', true, false );
		}
	}
}
