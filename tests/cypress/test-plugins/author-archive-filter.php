<?php
/**
 * Plugin Name: Author Archive Filter
 * Description: Deactivate the author archives via the filter in Disable Blog.
 * Version:     1.0.0
 * Author:      Joshua David Nelson
 * License:     GPLv2 or later
 */

add_filter( 'dwpb_disable_author_archives', '__return_true' );
