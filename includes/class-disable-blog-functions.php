<?php
/**
 * Contains common functions used by other classes.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.5.0
 * @package    Disable_Blog
 * @subpackage Disable_Blog_Functions
 */

/**
 * Main class for functions used by other classes.
 *
 * @since      0.5.0
 * @package    Disable_Blog
 * @subpackage Disable_Blog_Functions
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */
class Disable_Blog_Functions {

	/**
	 * Redirect function, checks that a redirect looks safe and then runs it.
	 *
	 * @since 0.5.0
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

			// Filter the safe redirect to avoid redirectin non-admin urls to the dashboard
			// if the fallback is used by the core wp_redirect.
			add_filter( 'wp_safe_redirect_fallback', array( $this, 'wp_safe_redirect_fallback' ), 9, 1 );
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
	 * Filter the safe redirect fallback to prevent front-end users
	 * in public redirects from being redirected to the admin url
	 * which is the WP core default for safe redirects.
	 *
	 * @since 0.5.0
	 * @param string $url    the fallback url.
	 * @return string
	 */
	public function wp_safe_redirect_fallback( $url ) {

		if ( ! is_admin() ) {
			return home_url();
		}

		return $url;

	}

	/**
	 * Check what post types are supporting author archives.
	 *
	 * @since 0.5.0
	 * @return bool|array $post_types Either an array of post types or false.
	 */
	public function author_archive_post_types() {

		/**
		 * The post types supported on author archives.
		 *
		 * By default only posts are shown on author archives, if other post types are to appear
		 * on the author archives, pass them with this filter.
		 *
		 * @since 0.5.0
		 * @param array|bool $post_types an array of post type slugs for author archives, false to disable.
		 */
		$post_types = apply_filters( 'dwpb_author_archive_post_types', array() );

		if ( ! empty( $post_types ) ) {
			return $post_types;
		}

		// Return false if there are no supported post types.
		return false;

	}

	/**
	 * Check if we are disabling author archives.
	 *
	 * Will only return true if the filter is actively set to true
	 * AND there are valid post types to show on the author archives.
	 *
	 * @since 0.5.0
	 * @return bool
	 */
	public function disable_author_archives() {

		/**
		 * Disable the author archives.
		 *
		 * Because many plugins and themes use author archives for profile pages,
		 * the ability to disable the author archive defaults to false.
		 *
		 * @since 0.5.0
		 * @param bool $bool True to disable author archives.
		 * @return bool
		 */
		return (bool) apply_filters( 'dwpb_disable_author_archives', false );

	}

}
