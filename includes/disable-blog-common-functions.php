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
 * 
 * @return array ( $post_types | bolean )
 */
function dwpb_post_types_with_feature( $feature ) {
	$post_types = get_post_types( array(), 'names' );

	$post_types_with_feature = array();
	foreach( $post_types as $post_type ) {
		if( post_type_supports( $post_type, $feature ) && $post_type != 'post' ) {
			$post_types_with_feature[] = $post_type;
		}
	}
	
	// Keep the array if there are any, otherwise make it return false
	$post_types_with_feature = empty( $post_types_with_feature ) ? false : $post_types_with_feature;
	
	// Return the value
	return apply_filters( "dwpb_post_types_supporting_{$feature}", $post_types_with_feature );
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
 * 
 * @see register_post_types(), get_post_types(), get_object_taxonomies()
 * @uses get_post_types(), get_object_taxonomies(), apply_filters()
 * 
 * @param string $taxonomy Required. The name of the feature to check against post type support.
 * @param array | string $args Optional. An array of key => value arguments to match against the post type objects. Default empty array.
 * @param string $output Optional. The type of output to return. Accepts post type 'names' or 'objects'. Default 'names'.
 * 
 * @return array | boolean	A list of post type names or objects that have the taxonomy or false if nothing found.
 */
function dwpb_post_types_with_tax( $taxonomy, $args = array(), $output = 'names' ) {
	$post_types = get_post_types( $args, $output );

	// We just need the taxonomy name
	if( is_object( $taxonomy ) ){
		$taxonomy = $taxonomy->name;

	// If it's not an object or a string, it won't work, so send it back
	} elseif( ! is_string( $taxonomy ) ) {
		return false;
	}

	// setup the finished product
	$post_types_with_tax = array();
	foreach( $post_types as $post_type ) {
		// If post types are objects
		if( is_object( $post_type ) ) {
			$type = $post_type->name;
		// If post types are strings
		} elseif( is_string( $post_type ) ) {
			$type = $post_type;
		} else {
			$type = '';
		}
		
		// is the post included in this post type, but not 'post' type.
		if( !empty( $type ) && $type != 'post' ) {
			$taxonomies = get_object_taxonomies( $type, 'names' );
			if( in_array( $taxonomy, $taxonomies ) ) {
				$post_types_with_tax[] = $post_type;
			}
		}
	}

	// Ability to override the results
	$override = apply_filters( 'dwpb_taxonomy_support', null, $taxonomy, $post_types, $args, $output );
	if( ! is_null( $override ) ) {
		return $override;
	}

	// If there aren't any results, return false
	if( empty( $post_types_with_tax ) ) {
		return false;
	} else {
		return $post_types_with_tax;
	}
}