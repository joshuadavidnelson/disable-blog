<?php
/**
 * Plugin Name: Remove Options Writing
 * Description: Deactivate the Options > Writing via the filter in Disable Blog.
 * Version:     1.0.0
 * Author:      Joshua David Nelson
 * License:     GPLv2 or later
 */

add_filter( 'dwpb_remove_options_writing', '__return_true' );
