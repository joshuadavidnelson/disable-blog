<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.3
 * @package    Disable_Blog
 * @subpackage Disable_Blog\Includes
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since 0.4.3
 */
class Disable_Blog_Deactivator {

	/**
	 * The $_REQUEST during plugin deactivation.
	 *
	 * @since  0.5.1
	 * @access private
	 * @var    array $request The $_REQUEST array during plugin deactivation.
	 */
	private static $request = array();

	/**
	 * The $_REQUEST['plugin'] during plugin deactivation.
	 *
	 * @since  0.5.1
	 * @access private
	 * @var    string $plugin The $_REQUEST['plugin'] value during plugin deactivation.
	 */
	private static $plugin = 'disable-blog';

	/**
	 * Deactivate the plugin.
	 *
	 * Checks if the plugin was (safely) deactivated.
	 * Place to add any custom action during plugin deactivation.
	 *
	 * @since 0.4.3
	 * @since 0.4.9 add flush rewrite rules.
	 * @since 0.5.1
	 */
	public static function deactivate() {

		if ( false === self::get_request()
			|| false === self::validate_request( self::$plugin )
			|| false === self::check_caps()
		) {
			if ( isset( $_REQUEST['plugin'] ) ) {
				if ( ! check_admin_referer( 'deactivate-plugin_' . self::$request['plugin'] ) ) {
					exit;
				}
			} elseif ( isset( $_REQUEST['checked'] ) ) {
				if ( ! check_admin_referer( 'bulk-plugins' ) ) {
					exit;
				}
			}
		}

		/**
		 * The plugin is now safely deactivated.
		 */

		wp_cache_delete( 'comments-0', 'counts' );
		delete_transient( 'wc_count_comments' );
		flush_rewrite_rules();

	}

	/**
	 * Get the request.
	 *
	 * Gets the $_REQUEST array and checks if necessary keys are set.
	 * Populates self::request with necessary and sanitized values.
	 *
	 * @since 0.5.1
	 * @return bool|array false or self::$request array.
	 */
	private static function get_request() {

		if ( ! empty( $_REQUEST )
			&& isset( $_REQUEST['_wpnonce'] )
			&& isset( $_REQUEST['action'] )
		) {
			if ( isset( $_REQUEST['plugin'] ) ) {
				if ( false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'deactivate-plugin_' . sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) ) ) {

					self::$request['plugin'] = sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) );
					self::$request['action'] = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );

					return self::$request;

				}
			} elseif ( isset( $_REQUEST['checked'] ) ) {
				if ( false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-plugins' ) ) {

					self::$request['action'] = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
					self::$request['plugins'] = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['checked'] ) );

					return self::$request;

				}
			}
		}

		return false;

	}

	/**
	 * Validate the Request data.
	 *
	 * Validates the $_REQUESTed data is matching this plugin and action.
	 *
	 * @since 0.5.1
	 * @param string $plugin The Plugin folder/name.php.
	 * @return bool false if either plugin or action does not match, else true.
	 */
	private static function validate_request( $plugin ) {

		if ( isset( self::$request['plugin'] )
			&& $plugin === self::$request['plugin']
			&& 'deactivate' === self::$request['action']
		) {

			return true;

		} elseif ( isset( self::$request['plugins'] )
			&& 'deactivate-selected' === self::$request['action']
			&& in_array( $plugin, self::$request['plugins'] )
		) {
			return true;
		}

		return false;

	}

	/**
	 * Check Capabilities.
	 *
	 * We want no one else but users with activate_plugins or above to be able to active this plugin.
	 *
	 * @since 0.5.1
	 * @return bool false if no caps, else true.
	 */
	private static function check_caps() {

		if ( current_user_can( 'activate_plugins' ) ) {
			return true;
		}

		return false;

	}

}
