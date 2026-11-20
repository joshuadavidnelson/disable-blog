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
 * @since 0.5.0
 */
class Disable_Blog_Functions {

	/**
	 * How many callbacks run with the comment rule switched off.
	 *
	 * A count rather than a flag, so a nested call does not switch the rule
	 * back on early.
	 *
	 * @since 0.5.7
	 * @var int
	 */
	private static $unfiltered = 0;

	/**
	 * The queries core is still to run for the replies and reactions of notes
	 * on posts being trashed or deleted, as the note's ID and the comment type
	 * each query asks for.
	 *
	 * Core runs some of them after the last hook it fires for the note, so
	 * each entry is removed by the query it expects rather than by a hook.
	 *
	 * @since 0.5.7
	 * @var array<int, string[]>
	 */
	private static $cascade_queries = array();

	/**
	 * Run a callback with the comment rule switched off.
	 *
	 * @since 0.5.7
	 * @param callable     $callback Callback to run.
	 * @param array<mixed> $args     Arguments to pass it.
	 * @return mixed What the callback returns.
	 */
	public static function run_unfiltered( $callback, $args = array() ) {

		++self::$unfiltered;

		try {
			return call_user_func_array( $callback, $args );
		} finally {
			--self::$unfiltered;
		}
	}

	/**
	 * Expect the queries core runs for the replies and reactions it trashes
	 * or deletes with a note on a post.
	 *
	 * Core queries for a note's reactions when it trashes or deletes the note
	 * (WordPress 7.2 and later), and for a top-level note's replies once it
	 * has trashed the note. Both queries ask for IDs only, by a single
	 * `parent` and `type`. When core deletes a note in place of trashing it,
	 * it moves the replies up a level before querying for them, so that query
	 * finds nothing either way and is not expected.
	 *
	 * @since 0.5.7
	 * @param int|string $comment_id Comment ID.
	 * @param string     $hook       `trash_comment` or `delete_comment`.
	 * @return void
	 */
	public function track_comment_cascade( $comment_id, $hook ) {

		$comment_id = (int) $comment_id;

		if ( ! $this->is_post_comment( $comment_id ) ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof WP_Comment || 'note' !== $comment->comment_type ) {
			return;
		}

		$types = array();

		// A 7.2 prerelease cascades reactions too.
		if ( version_compare( get_bloginfo( 'version' ), '7.2-alpha', '>=' ) ) {
			$types[] = 'reaction';
		}

		if ( 'trash_comment' === $hook && 0 === (int) $comment->comment_parent ) {
			$types[] = 'note';
		}

		if ( ! empty( $types ) ) {
			self::$cascade_queries[ $comment_id ] = array_merge( self::$cascade_queries[ $comment_id ] ?? array(), $types );
		}
	}

	/**
	 * Mark a comment query as one core runs for a note's replies or
	 * reactions, and stop expecting it.
	 *
	 * @since 0.5.7
	 * @param WP_Comment_Query $query Comment query.
	 * @return bool Whether the query was expected.
	 */
	public function claim_cascade_query( $query ) {

		if ( empty( self::$cascade_queries ) || ! $query instanceof WP_Comment_Query ) {
			return false;
		}

		$vars = $query->query_vars;

		if ( ! isset( $vars['fields'], $vars['parent'], $vars['type'] )
			|| 'ids' !== $vars['fields']
			|| ! is_numeric( $vars['parent'] )
			|| ! is_string( $vars['type'] )
			|| ! empty( $vars['parent__in'] )
		) {
			return false;
		}

		$parent = (int) $vars['parent'];
		$key    = isset( self::$cascade_queries[ $parent ] ) ? array_search( $vars['type'], self::$cascade_queries[ $parent ], true ) : false;

		if ( false === $key ) {
			return false;
		}

		unset( self::$cascade_queries[ $parent ][ $key ] );

		if ( empty( self::$cascade_queries[ $parent ] ) ) {
			unset( self::$cascade_queries[ $parent ] );
		}

		$query->query_vars['dwpb_cascade'] = true;

		return true;
	}

	/**
	 * Whether a comment query is one core runs for a note's replies or
	 * reactions.
	 *
	 * @since 0.5.7
	 * @see Disable_Blog_Functions::claim_cascade_query()
	 * @param WP_Comment_Query $query Comment query.
	 * @return bool
	 */
	public function is_cascade_query( $query ) {

		return $query instanceof WP_Comment_Query && ! empty( $query->query_vars['dwpb_cascade'] );
	}

	/**
	 * Whether a comment query runs without the comment rule while the rule is
	 * otherwise on, so its results must not share a cache entry with
	 * filtered ones.
	 *
	 * @since 0.5.7
	 * @param WP_Comment_Query $query Comment query.
	 * @return bool
	 */
	public function is_exempt_comment_query( $query ) {

		return self::$unfiltered > 0 || $this->is_cascade_query( $query );
	}

	/**
	 * Redirect function, checks that a redirect looks safe and then runs it.
	 *
	 * @since 0.5.0
	 * @since 0.5.7 the front-end loop guard compares against the full request
	 *              URI, query string included.
	 * @param string $redirect_url the url to redirect to.
	 * @return void
	 */
	public function redirect( $redirect_url ) {

		// Get the current url.
		global $wp;
		if ( is_admin() ) {
			$current_url = admin_url( add_query_arg( array(), $wp->request ) );
		} else {

			// $wp->request is path-only, so a redirect differing only by query
			// string would otherwise look like a loop.
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$current_url = home_url( $request_uri );

			// Filter the safe redirect to avoid redirecting non-admin urls to the dashboard
			// if the fallback is used by the core wp_redirect.
			add_filter( 'wp_safe_redirect_fallback', array( $this, 'wp_safe_redirect_fallback' ), 9, 1 );
		}

		// Bail to avoid a loop if the current and redirect urls match, or if there's no valid redirect url.
		if ( $redirect_url === $current_url || ! esc_url_raw( $redirect_url ) ) {
			return;
		}

		/**
		 * Should we pass url query string in the redirect?
		 *
		 * Default is false.
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

		// Only allow specific query vars.
		$allowed_query_vars = $this->get_allowed_query_vars();
		if ( ! empty( $allowed_query_vars ) ) {

			// Only keep the allowed query vars, if there are any.
			// Sanitizing the keys and filtering out any empty values.
			// Let add_query_arg handle the sanitization of the values.
			$valid_query_var_keys = array_flip( $allowed_query_vars );
			$query_vars           = array_filter( array_intersect_key( $query_vars, $valid_query_var_keys ) );

			// if we have any query variables, add it to the url.
			if ( ! empty( $query_vars ) ) {
				$url = add_query_arg( $query_vars, $url );
			}
		}

		return $url;
	}

	/**
	 * Get the allowed query vars.
	 *
	 * @since 0.5.5
	 * @return array
	 */
	private function get_allowed_query_vars() {

		/**
		 * Filter for allowed query string variables.
		 *
		 * @since 0.5.0
		 * @param array $allowed_query_vars an array of the allowed query variable keys.
		 * @return array
		 */
		$allowed_query_vars = (array) apply_filters( 'dwpb_allowed_query_vars', array() );

		// Sanitizing the keys and filtering out any empty values.
		return array_filter( array_map( 'sanitize_key', $allowed_query_vars ) );
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
	 * @param string $url the fallback url.
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

	/**
	 * Toggle the disable feed via this filter.
	 *
	 * @since 0.5.1
	 * @param object $post            Global post object.
	 * @param bool   $is_comment_feed True if the feed is a comment feed.
	 */
	public function disable_feeds( $post, $is_comment_feed = false ) {

		/**
		 * Toggle the disable feed via this filter.
		 *
		 * @since 0.4.0
		 * @param bool $bool True to cancel the feed, assuming it's a post feed.
		 * @param object $post Global post object.
		 * @param bool $is_comment_feed True if the feed is a comment feed.
		 */
		return (bool) apply_filters( 'dwpb_disable_feed', true, $post, $is_comment_feed );
	}

	/**
	 * Whether the plugin narrows comment queries, counts and comment requests.
	 *
	 * Checked inside each callback rather than when hooks are registered, so it
	 * holds when Disable Comments loads after this plugin.
	 *
	 * @since 0.5.7
	 * @return bool
	 */
	public function should_filter_comments() {

		if ( self::$unfiltered > 0 ) {
			return false;
		}

		$integrations = new Disable_Blog_Integrations();

		if ( $integrations->is_disable_comments_active() ) {
			return false;
		}

		/**
		 * Toggle the plugin's narrowing of comments.
		 *
		 * When true, no comment of any type whose parent is a post is returned by
		 * a comment query, the comments feed, REST or XML-RPC, and discussion
		 * comments (comments, pingbacks and trackbacks) are returned only for post
		 * types that support comments. Return false to leave comments to core.
		 *
		 * Comment queries are cached by their arguments, not by this rule. With a
		 * persistent object cache, a change made only through this filter or
		 * `dwpb_post_types_supporting_comments` applies once the comment cache
		 * next changes, for example when a comment is added or edited.
		 *
		 * @since 0.5.7
		 * @param bool $filter True to narrow comments, false to leave them alone.
		 */
		return (bool) apply_filters( 'dwpb_filter_comment_queries', true );
	}

	/**
	 * The SQL condition a comment must meet to be returned.
	 *
	 * A comment of any type on a post fails it. A discussion comment passes
	 * only on a post type that supports comments. A comment whose parent post
	 * does not exist, including `comment_post_ID = 0`, passes. Columns are
	 * qualified with the table names, since the query it joins may already
	 * join the posts table without an alias.
	 *
	 * @since 0.5.7
	 * @return string
	 */
	public function comment_rule_sql() {

		global $wpdb;

		$post_types = $this->post_types_supporting_comments();

		$supported = '';
		if ( ! empty( $post_types ) ) {
			$supported = " OR {$wpdb->comments}.comment_post_ID IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( "
				. implode( ', ', array_fill( 0, count( $post_types ), '%s' ) ) . ' ) )';
		}

		return $wpdb->prepare(
			"{$wpdb->comments}.comment_post_ID NOT IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type = %s )
			AND ( {$wpdb->comments}.comment_type NOT IN ( '', 'comment', 'pingback', 'trackback' )
				OR {$wpdb->comments}.comment_post_ID NOT IN ( SELECT ID FROM {$wpdb->posts} ){$supported} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $supported holds table names and one %s per post type, bound below.
			array_merge( array( 'post' ), $post_types )
		);
	}

	/**
	 * Post types other than `post` whose discussion comments are returned.
	 *
	 * Built on each call rather than read from `dwpb_post_types_with_feature()`,
	 * whose result is kept in the object cache and, with a persistent cache,
	 * would outlive a change to the registered post types.
	 *
	 * @since 0.5.7
	 * @return string[]
	 */
	private function post_types_supporting_comments() {

		$post_types = array_values( array_diff( get_post_types_by_support( 'comments' ), array( 'post' ) ) );

		/** This filter is documented in includes/functions.php */
		$post_types = apply_filters( 'dwpb_post_types_supporting_comments', empty( $post_types ) ? false : $post_types, array() );

		return is_array( $post_types ) ? array_values( array_filter( $post_types, 'is_string' ) ) : array();
	}

	/**
	 * Whether a comment's parent is a post.
	 *
	 * @since 0.5.7
	 * @param int $comment_id Comment ID.
	 * @return bool
	 */
	public function is_post_comment( $comment_id ) {

		// get_comment() falls back to the global comment for an empty ID.
		if ( (int) $comment_id <= 0 ) {
			return false;
		}

		$comment = get_comment( (int) $comment_id );

		// get_post_type() falls back to the global post for an empty ID.
		if ( ! $comment instanceof WP_Comment || (int) $comment->comment_post_ID <= 0 ) {
			return false;
		}

		return 'post' === get_post_type( (int) $comment->comment_post_ID );
	}
}
