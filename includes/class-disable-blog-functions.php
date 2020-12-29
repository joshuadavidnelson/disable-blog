<?php
/**
 * Contains common functions used by other classes.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.12
 * @package    Disable_Blog
 * @subpackage Disable_Blog_Common_Functions
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @since      0.4.12
 * @package    Disable_Blog
 * @subpackage Disable_Blog_Common_Functions
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */
class Disable_Blog_Functions {

	/**
	 * Redirect function, checks that a redirect looks safe and then runs it.
	 *
	 * @since 0.4.11
	 * @since 0.4.12 moved to common functions class.
	 * @param string $redirect_url the url to redirect to.
	 * @return void
	 */
	public function redirect( $redirect_url ) {

		// Get the current url.
		global $wp;
		if ( is_admin() ) {
			$current_url = admin_url( add_query_arg( array(), $wp->request ) );
		} else {
			$current_url = home_url( add_query_arg( array(), $wp->request ) );
		}

		// Compare the current url to the redirect url, if they are the same, bail to avoid a loop.
		// If there is no valid redirect url, then also bail.
		if ( $redirect_url === $current_url || ! esc_url_raw( $redirect_url ) ) {
			return;
		}

		wp_safe_redirect( esc_url_raw( $redirect_url ), 301 );
		exit;

	}

	/**
	 * Check what post types are supporting author archives.
	 *
	 * @since 0.4.11
	 * @return bool|array $post_types Either an array of post types or false.
	 */
	public function author_archive_post_types() {

		/**
		 * The post types supported on author archives.
		 *
		 * By default only posts are shown on author archives, if other post types are to appear
		 * on the author archives, pass them with this filter.
		 *
		 * @since 0.4.11
		 * @param array|bool $post_types an array of post type slugs for author archives, false to disable.
		 */
		$post_types = apply_filters( 'dwpb_author_archive_post_types', array() );

		if ( ! empty( $post_types ) ) {
			return $post_types;
		}

		// Return false if there are no supported post types.
		return false;

	}

}
