<?php
/**
 * A class of common functions.
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.0
 *
 * @package    Disable_Blog
 * @subpackage Disable_Blog/includes
 */

/**
 * Get all the post types that support a featured (like 'comments')
 *
 * @link http://codex.wordpress.org/Function_Reference/register_post_type#Arguments
 *
 * @since 0.1.0
 * @since 0.4.0 pulled out of class, unique function.
 * @since 0.4.11 added $args parameter for passing specific arguments to get_post_types.
 * @param string $feature the feature in question.
 * @param array  $args    the arguments passed to get_post_types.
 * @return array|bool A list of post type names that support the featured or false if nothing found.
 */
function dwpb_post_types_with_feature( $feature, $args = array() ) {

	$post_types = get_post_types( $args, 'names' );

	$post_types_with_feature = array();
	foreach ( $post_types as $post_type ) {
		if ( post_type_supports( $post_type, $feature ) && 'post' !== $post_type ) {
			$post_types_with_feature[] = $post_type;
		}
	}

	// Keep the array if there are any, otherwise make it return false.
	$post_types_with_feature = empty( $post_types_with_feature ) ? false : $post_types_with_feature;

	/**
	 * Filter the returned "post types with feature".
	 *
	 * This function is used to determine if there are any post types with a specific
	 * feature, not including the `post` type. Often this toggles on/off options in
	 * the plugin. For instance, if comments are only support by posts, then they will
	 * be disabled and the options-discussion.php admin page redirected.
	 *
	 * @since 0.4.0
	 * @since 0.4.11 Added the $args paramenter.
	 * @param array|bool $post_types_with_feature an array of post types support this feature or false if none.
	 * @param array      $args                    the arguments passed to get_post_types.
	 * @return array|bool A list of post type names that support the featured or false if nothing found.
	 */
	return apply_filters( "dwpb_post_types_supporting_{$feature}", $post_types_with_feature, $args );

}

/**
 * Get post types that have a specific taxonomy
 *  (a combination of get_post_types and get_object_taxonomies)
 *
 * Basically, we need to know if there are post types, other than 'post'
 * that support the taxonomy.
 *
 * @since 0.2.0
 * @since 0.4.0 pulled out of class, unique function.
 * @see register_post_types(), get_post_types(), get_object_taxonomies()
 * @uses get_post_types(), get_object_taxonomies(), apply_filters()
 * @param string|object $taxonomy Required. The taxonomy object or taxonomy slug.
 * @param array|string  $args     Optional. An array of key => value arguments to match against the post type objects. Default empty array.
 * @param string        $output   Optional. The type of output to return. Accepts post type 'names' or 'objects'. Default 'names'.
 * @return array|bool A list of post type names or objects that have the taxonomy or false if nothing found.
 */
function dwpb_post_types_with_tax( $taxonomy, $args = array(), $output = 'names' ) {

	$post_types = get_post_types( $args, $output );

	// We just need the taxonomy name.
	if ( is_object( $taxonomy ) ) {
		$taxonomy = $taxonomy->name;

		// If it's not an object or a string, it won't work, so send it back.
	} elseif ( ! is_string( $taxonomy ) ) {
		return false;
	}

	// setup the finished product.
	$post_types_with_tax = array();
	foreach ( $post_types as $post_type ) {

		// If post types are objects.
		if ( is_object( $post_type ) ) {
			$type = $post_type->name;
			// If post types are strings.
		} elseif ( is_string( $post_type ) ) {
			$type = $post_type;
		} else {
			$type = '';
		}

		// is the post included in this post type, but not 'post' type.
		if ( ! empty( $type ) && 'post' !== $type ) {
			$taxonomies = get_object_taxonomies( $type, 'names' );
			if ( in_array( $taxonomy, $taxonomies, true ) ) {
				$post_types_with_tax[] = $post_type;
			}
		}
	}

	/**
	 * Filter the returned value of "post types with tax".
	 *
	 * This function is used to determine if there are any post types using a taxonomy,
	 * not including the `post` type. This is used to determine if there are custom
	 * post types using the built-in `post_tag` and `category` taxonomies and toggle
	 * off related features if they are not being used by anything other than built-in posts.
	 *
	 * @since 0.4.0
	 * @param mixed         $null                Null for no override, otherwise pass an array of post type slugs.
	 * @param string|object $taxonomy            The curent taxonomy slug.
	 * @param array|bool    $post_types_with_tax An array of post types use this taxonomy or false if none.
	 * @param array         $args                An array of key => value arguments to match against the post type objects. Default empty array.
	 * @param string        $output              The type of output to return, either 'names' or 'objects'.
	 * @return mixed A list of post type names that use this taxonomy or false if nothing found.
	 */
	$override = apply_filters( 'dwpb_taxonomy_support', null, $taxonomy, $post_types, $args, $output );
	if ( ! is_null( $override ) ) {
		return $override;
	}

	// If there aren't any results, return false.
	if ( empty( $post_types_with_tax ) ) {
		return false;
	} else {
		return $post_types_with_tax;
	}

}

/**
 * Replaces the core REST Availability Site Health check.
 *
 * Used by the site_status_tests filter in class-disable-blog-admin.php.
 *
 * Copied directly from https://developer.wordpress.org/reference/classes/wp_site_health/get_test_rest_availability/ but with the 'post' type updated to 'page' in the rest url.
 *
 * @see https://make.wordpress.org/core/2019/04/25/site-health-check-in-5-2/
 * @since 0.4.11
 * @return array
 */
function dwpb_get_test_rest_availability() {

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
	if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
		$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
	}

	// -- here's the money, change this ti 'page' from 'post'.
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
