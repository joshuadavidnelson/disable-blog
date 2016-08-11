=== Disable Blog ===
Contributors: joshuadnelson
Donate link: https://joshuadnelson.com/donate/
Tags: remove blog, disable blog, disable settings, disable blogging, disable feeds, posts, feeds
Requires at least: 3.1.0
Tested up to: 4.5.3
Stable tag: 0.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A plugin to disable the blog functionality of WordPress (by hiding, removing, and redirecting).

== Description ==

This plugin to disables the blog functionality of WordPress, mostly by hiding admin pages and settings and redirecting pages on both the front-end and admin side. This can be useful when you want a WordPress site to remain static and hide blog-related elements from admin users to avoid confusion.

**Important**: If Settings > Reading > "Front Page Displays" is not set to show on a page, then this plugin will update it to that option, but **you still need to select a page to act as the front page**. Not doing so will mean that your post page can still be visible on the front-end of the site.

**Comments**: Comments remain enabled, unless the 'post' type is the only type supporting comments (pages also typical support comments so they don't disappear in most cases). If you're looking to disable them completely, check out the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin (by others).

**Categories & Tags**: These are hidden and redirected, unless they are supported by a custom post type.

**Custom Post Types**: For the most part this plugin shouldn't bother any custom post types. If you are using a custom post type that supports the built-in `category` and/or `post_tag` taxonomies, they will be visible and accessible through that post type.

**Database**: This plugin will not delete anything in your database. If you don't want your posts, comments, categories, or tags, delete them prior to activation (or deactivate this plugin, delete them, and re-active). The only exception to the database modifications is related to the way your site displays it's front page, as mentioned above.

**Support**: This plugin is maintain for free but **Please reach out** and I will assist you as soon as possible. You can visit the [support forums](https://pippinsplugins.com) or the [issue](https://github.com/joshuadavidnelson/disable-blog/issues) section of the [GitHub repository](https://github.com/joshuadavidnelson/disable-blog).

= View on GitHub & Contribute =
[View this plugin on GitHub](https://github.com/joshuadavidnelson/disable-blog) to see a comprehensive list of this plugin's functionality and the To-Do list of items yet to be included, as well as log any issues (or visit the WP support forums linked above).

Please feel free to contribute!

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates

== Frequently Asked Questions ==

1. **Why Not Disable Comments Entirely?** This could be done, but other post types (like Pages) may have comment support. If you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin.

2. **I want to delete my posts and comments.** Deactivate the plugin, delete your posts (which will delete related comments), and delete any tags or categories you might want to remove as well. Then reactivate the plugin to hide everything.

== Changelog ==

= 0.4.1 =
* Fix unintended redirect for custom admin pages under tools.php. Props to @greatislander for the catch.

= 0.4.0 =
A bunch of stuff:

* Refactor code to match WP Plugin Boilerplate structure, including:
 * Move hooks and filters into loader class.
 * Separate Admin and Public hooks.
 * Add support for internationalization.
* Expanded inline documentation.
* Add another failsafe for potential redirect loops.
* Disable comments feed only if 'post' is only type shown.
* Hide/redirect discussion options page if 'post' is the only post type supporting it (typically supported by pages).
* Filter comment counts to remove comments associated with 'post' post type.
* Add $is_comment_feed variable to disable feed filters.
* Remove feed link from front end (for WP >= 4.4.0), remove comment feed link if 'post' is the only post type supporting comments.
* Hide options in Reading Settings page related to posts (shows front page and search engine options only now), previously it was hiding everything on this page (bugfix!).
* Fix show_on_front pages: now, if it's set to 'posts' it will set the blog page to value 0 (not a valid option) and set the front page to value 1.
* Add uninstall.php to remove plugin version saved in options table on uninstall.

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

= 0.4.1 =
* Fix unintended redirect for custom admin pages under tools.php. Props to @greatislander for the catch.

= 0.4.0 =
A bunch of updates and fixes.

= 0.3.3 =
bugfixes
