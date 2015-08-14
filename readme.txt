=== Disable Blog ===
Contributors: joshuadnelson
Donate link: http://jdn.im/donate
Tags: blog, disable settings
Requires at least: 3.1.0
Tested up to: 4.3
Stable tag: 0.3.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A plugin to disable the blog functionality of WordPress (by hiding, removing, and redirecting).

== Description ==

A plugin to disable the blog functionality of WordPress (by hiding, removing, and redirecting). Useful when you want a WordPress site to remain static and hide blog-related elements from admin users.

= This is a beta version, some aspects may still be in need of refining =

Does the following:

* Removes 'Posts' Admin Menu
* Removes 'post' post type from most queries
* Disables the Feed for Posts
* Redirects 'New Post' and 'Edit Post' admin pages to 'New Page' and 'Edit Page' admin pages
* Redirects 'Comments' admin page with query variable post_type=post to main comments page
* Redirects Single Posts, Post Archives, Tag & Category archives to home page (the latter two are only redirected if 'post' post type is the only post type associated with it)
* Filters out the 'post' post type fromm 'Comments' admin page
* Removes Post from '+New' admin bar menu
* Removes post-related dashboard widgets
* Hides number of posts and comment count on Activity dashboard widget
* Removes 'Writing' Options from Settings Menu
* Redirects 'Writing' Options to General Options
* Hides 'Posts' options on 'Menus' admin page
* Removes Post Related Widgets
* Disables "Press This" functionality
* Disables "Post By Email" functionality
* Forces Reading Settings: show_on_front, pages_for_posts, and posts_on_front, if they are not already set
* Hides other post-related reading options, except Search Engine Visibilty
* Removes post from author archive query

Note that this plugin will not delete anything - existing posts, comments, categories and tags will remain in your database.

If Settings > Reading > Front Page Displays is not set to show on a page, then that setting will be forced by this plugin (includes three interrlated seetings: show_on_front, pages_for_posts, and posts_on_front).

= View on GitHub =
[View this plugin on GitHub](https://github.com/joshuadavidnelson/disable-wordpress-blog)

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates


== Changelog ==

= 0.3.3 =
* Weird issue with svn, same as 0.3.2

= 0.3.2 =
* Fix potential loop issue with `home_url` in redirection function
* Fix custom taxonomy save redirect (used to redirect to dashboard, now it saves correctly)

= 0.3.1 =
* Add WordPress readme.txt

= 0.3.0 =
* Singleton Class
* Clean up documentation
* Add filters

= 0.2.0 =
* Remove 'post' post type from most queries
* Change disable feed functionality to a redirect instead of die message
* Refine admin redirects
* Add redirects for Single Posts, Post Archives, Tag & Category archives to home page (the latter two are only redirected if 'post' post type is the only post type associated with it)
* Filter out the 'post' post type from 'Comments' admin page
* Remove Post from '+New' admin bar menu
* Hide number of posts and comment count on Activity dashboard widget
* Remove 'Writing' Options from Settings Menu
* Redirect 'Writing' Options to General Options
* Hide 'Posts' options on 'Menus' admin page
* Remove Post Related Widgets
* Disable "Press This" functionality
* Disable "Post By Email" functionality
* Force Reading Settings: show_on_front, pages_for_posts, and posts_on_front, if they are not already set
* Hide other post-related reading options, except Search Engine Visibility

== Upgrade Notice ==

= 0.3.3 =
* Fix potential loop issue with `home_url` in redirection function
* Fix custom taxonomy save redirect (used to redirect to dashboard, now it saves correctly)