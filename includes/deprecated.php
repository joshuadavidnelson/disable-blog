<?php
/**
 * Deprecated filters.
 *
 * Filters here for backwards compatibillity, being phased out of use.
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.11
 * @package    Disable_Blog
 * @subpackage Disable_Blog/includes
 */

add_filter( 'dwpb_redirect_admin_post', 'deprecated_redirect_admin_post_filter', 9, 1 );
/**
 * Original filter to toggle the admin post.php screen redirect.
 *
 * @since 0.4.11
 * @param bool $bool true to redirect, false to not.
 * @return bool
 */
function deprecated_redirect_admin_post_filter( $bool ) {

	return apply_filters_deprecated( 'dwpb_redirect_admin_edit_single_post', array( $bool ), '0.4.11', 'dwpb_redirect_admin_post', 'This filter will be removed in version 0.5.0' );

}

add_filter( 'dwpb_redirect_post', 'deprecated_redirect_post_filter', 9, 1 );
/**
 * Original filter for the admin redirect url.
 *
 * @since 0.4.11
 * @param string $url the redirect's destination url.
 * @return string
 */
function deprecated_redirect_post_filter( $url ) {

	return apply_filters_deprecated( 'dwpb_redirect_single_post_edit', array( $url ), '0.4.11', 'dwpb_redirect_post', 'This filter will be removed in version 0.5.0' );

}

add_filter( 'dwpb_redirect_admin_edit', 'deprecated_redirect_admin_edit_filter', 9, 1 );
/**
 * Original filter for toggling the admin edit.php redirect.
 *
 * @since 0.4.11
 * @param bool $bool true to redirect, false to not.
 * @return bool
 */
function deprecated_redirect_admin_edit_filter( $bool ) {

	return apply_filters_deprecated( 'dwpb_redirect_admin_edit_post', array( $bool ), '0.4.11', 'dwpb_redirect_admin_edit', 'This filter will be removed in version 0.5.0' );

}
