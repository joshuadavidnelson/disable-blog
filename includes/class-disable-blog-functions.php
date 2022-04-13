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

		/**
		 * Should we pass url query string in the redirect?
		 *
		 * Deault is false.
		 *
		 * @since 0.5.0
		 * @param bool $bool true to allow query strings on redirect.
		 * @return bool
		 */
		if ( apply_filters( 'dwpb_pass_query_string_on_redirect', false ) ) {
			$redirect_url = $this->parse_query_string( $redirect_url );
		}

		wp_safe_redirect( esc_url_raw( $redirect_url ), $this->get_redirect_status_code( $current_url, $redirect_url ) );
		exit;

	}

	/**
	 * Parse the current query string and add it, clean and filter, to the url.
	 *
	 * @since 0.5.0
	 * @param string $url the url any query string will be added to.
	 * @return string
	 */
	private function parse_query_string( $url ) {

		if ( ! isset( $_SERVER['QUERY_STRING'] ) || empty( $_SERVER['QUERY_STRING'] ) ) {
			return $url;
		}

		// Setup an array of the current query string variables.
		$query_vars = array();
		wp_parse_str( $_SERVER['QUERY_STRING'], $query_vars ); // phpcs:ignore

		/**
		 * Filter for allowed queary string variables.
		 *
		 * @since 0.5.0
		 * @param array $allowed_query_vars an array of the allowed query variable keys.
		 * @return array
		 */
		$allowed_query_vars = apply_filters( 'dwpb_allowed_query_vars', array() );
		if ( ! empty( $allowed_query_vars ) && is_array( $allowed_query_vars ) ) {
			$allowed_query_vars = array_filter( $allowed_query_vars, 'esc_html' );
			$query_vars         = array_intersect_key( $query_vars, array_flip( $allowed_query_vars ) );
		}

		// Escaping and sanitization are important.
		$query_vars = array_filter( $query_vars, 'esc_html' );
		$query_vars = array_filter( $query_vars, 'esc_html', ARRAY_FILTER_USE_KEY );

		// if we have any query variables, add it to the url.
		if ( ! empty( $query_vars ) && is_array( $query_vars ) ) {
			$url = add_query_arg( $query_vars, $url );
		}

		return $url;

	}

	/**
	 * Return the status code used in the main redirect function.
	 *
	 * @since 0.5.0
	 * @param string $current_url the url being redirected FROM.
	 * @param string $redirect_url the url being redirected TO.
	 * @return int
	 */
	private function get_redirect_status_code( $current_url, $redirect_url ) {

		/**
		 * Filter the status code, must be a valid number to pass, defaults to 301.
		 *
		 * @since 0.5.0
		 * @param int $status_code the status code returned with the redirect.
		 * @param string $current_url the url being redirected FROM.
		 * @param string $redirect_url the url being redirected TO.
		 * @return int $status_code
		 */
		$status_code = apply_filters( 'dwpb_redirect_status_code', 301, $current_url, $redirect_url );

		// Make sure we have a valid redirect status code, if not we set to 301.
		// helps to stop a wp_die in wp_redirect if the filtered value returns something invalid.
		if ( ! absint( $status_code ) || 300 > $status_code || 399 < $status_code ) {
			return 301;
		}

		return absint( $status_code );

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
