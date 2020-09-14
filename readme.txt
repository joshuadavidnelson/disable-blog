=== Disable Blog ===
Contributors: joshuadnelson
Donate link: https://joshuadnelson.com/donate/
Tags: remove blog, disable blog, disable settings, disable blogging, disable feeds, posts, feeds
Requires at least: 3.1.0
Requires PHP: 5.3
Tested up to: 5.5.1
Stable tag: 0.4.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

All the power of WordPress, but without a blog.

== Description ==

Free your WordPress site from the blog! Maintain a static website without "posts." Go blog-less with WordPress.

This plugin disables all blog-related functionality, mostly by hiding admin pages/settings and redirecting urls on both the front-end and admin portions of your site.

**Important**: If Settings > Reading > "Front Page Displays" is not set to show on a page, then this plugin will not function correctly. **You need to select a page to act as the home page**. Not doing so will mean that your post page can still be visible on the front-end of the site. Note that it's not required, but recommended you select a page for the  "posts page" setting, this page will be automatically redirected to the static "home page."

**Site Content & Data**: This plugin will not delete any of your site's data, however it does by default redirect all posts and post comments to the homepage (refer to the documentation on ways to change this behavior).

If you have any posts, comments, categories, and/or tags, delete them prior to activation (or deactivate this plugin, delete them, and re-active). If you don't delete them, they will remain in your database and become accessible if you deactivate this plugin or modify the plugin behavior to show posts.

**Comments**: Comments remain enabled, unless the 'post' type is the only type supporting comments (pages also support comments by default, so the comments section won't disappear in most cases). If you're looking to disable comments completely, check out the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin.

**Categories & Tags**: These are hidden and redirected, unless they are supported by a custom post type.

**Custom Post Types**: This plugin includes extensive support for custom post types and taxonomies. If you are using a custom post type that supports the built-in `category` and/or `post_tag` taxonomies, they will be visible and accessible through that post type.

**Support**: This plugin is maintained for free but **please reach out** and I will assist you as soon as possible. You can visit the [support forums](https://wordpress.org/support/plugin/disable-blog) or the [issue](https://github.com/joshuadavidnelson/disable-blog/issues) section of the [GitHub repository](https://github.com/joshuadavidnelson/disable-blog).

= View on GitHub & Contribute =
[View this plugin on GitHub](https://github.com/joshuadavidnelson/disable-blog) to see a comprehensive list of this plugin's functionality and the To-Do list of items yet to be included, as well as log any issues (or visit the WP support forums linked above).

Please feel free to contribute!

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Why Not Disable Comments Entirely? =

This could be done, but other post types (like Pages) may have comment support. If you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin.

= I want to delete my posts and comments. =

Deactivate the plugin, delete your posts (which will delete related comments), and delete any tags or categories you might want to remove as well. Then reactivate the plugin to hide everything.

= How can I change the plugin's behavior? =

There are numerous filters available to change the way this plugin works. Refer to the [GitHub page](https://github.com/joshuadavidnelson/disable-blog) or reach out on the [support forums](https://wordpress.org/support/plugin/disable-blog) if you have any questions.

== Changelog ==

= 0.4.9 =
- **Notice:** We've added the minimum PHP version requirement of 5.3, which was not explicitly set before now.
- **Big change:** the plugin now changes the `post_type` arguments for posts so they are no longer public and removes all post_type support parameters. This disables the post-related admin redirects, as WordPress will now show users an error page stating "Sorry, you are not allowed to edit posts in this post type." It also pulls posts out of a lot of other locations (menus, etc) and is a much more efficient method of "disabling" the post type. This method is also used on built-in taxonomies, unless another post type supports them. **This change may impact other plugins or themes, be sure to back up your site and, if you can, test these changes prior to updating the plugin on a production site.**
- Disable pingbacks entirely.
- Fix comment redirect/menu functionality, now correctly removes comments and redirects `edit-comments.php` admin page if no other post type support comments (note that WordPress default is for pages and attachments to support comments).
- Disable XMLRPC for posts and tags/categories. Tag/categories remain if another post type supports them.
- Add basic static php tests and update code to pass those test. Huge props to @szepeviktor.
- Initiate plugin via hook into `plugins_loaded`.
- Change the admin notice related to blog and home page settings, only showing notices if no homepage is set or if the blog and homepage are the same page.
- Flush rewrite rules at activation and deactivation.
- Filtering out `post` post types from all archives, previously it was just author archives and search results.
- Removes post, category, and tag options from all menus. Tag/categories remain if another post type supports them.
- Remove header feed urls, unless supported by another post type.
- WordPress 5.5 support:
	- Remove 'post' post type from XML sitemaps.
	- Remove built-in taxonomies from XML sitemaps, if not being used by a custom post type.
	- Fix sitemap redirect issues.
- **Developers:** Filters were removed and altered in this version:
	- The `dwpb_redirect_feeds` filter now has (3) params, to match those in the `dwpb_disable_feed` filter: $bool, $post, $is_comment_feed.
	- The `dwpb_author_post_types` filter is now `dwpb_archive_post_types`, as the query modification now includes all pages passing `is_archive`.
	- Removed filters: `dwpb_disable_rest_api`, `dwpb_remove_post_comment_support`, `dwpb_remove_post_trackback_support`, `dwpb_redirect_admin_edit_single_post`, `dwpb_redirect_single_post_edit`, `dwpb_redirect_admin_edit_post`, `dwpb_redirect_edit`, `dwpb_redirect_admin_post_new`, `dwpb_redirect_post_new` as these are rendered obsolete by above changes.

= 0.4.8.1 =
- Forgot to update the version number in the main plugin file, so this is a version bump only.

= 0.4.8 =
- Fixed typo in variable name for current vs redirect url check.
- Update function names from template to `disable_blog`.
- Add WP.org Badge to readme.md.
- Change the name of the CI workflow to be specific to deployment.
- Some code tidying and inline documentation.

= 0.4.7 =
* Using GitHub actions publish on WP.org from github releases.
* Cleaned up the Reading settings, adding admin notices if front page is not set.
* Add check for Multisite to avoid network page redirects. Closes #17, props to @Mactory.
* Added Contributing and Code of Conduct documentation.
* Check that `is_singular` works prior to running redirects to avoid non-object errors in feeds.

= 0.4.6 =
* Added check on disable feed functionality to confirm post type prior to disabling feed.

= 0.4.5 =
* Remove the functionality hiding the Settings > Writing admin page, allow this option to be re-enabled via the older filter. This page used to be entirely related to posts, but is also used to select the editor type (Gutenberg vs Classic).
* Correct misspelled dwpb_redirect_options_tools filter.

= 0.4.4 =
* Hide the Settings > Writing menu item, which shows up with Disable Comments enabled everywhere. Thanks to @dater for identifying.

= 0.4.3 =
* Fix fatal error conflict with WooCommerce versions older than 2.6.3 (props to @Mahjouba91 for the heads up), no returns an array of comments in the filter for those older WooCommerce versions.
* Add de/activation hooks to clear comment caches
* Cleanup comment count functions.

= 0.4.2 =
* Disable the REST API for 'post' post type. Props to @shawnhooper.

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

= 0.4.9 =
- **Notice:** We've added the minimum PHP version requirement of 5.3, which was not explicitly set before now.
- **Big change:** the plugin now changes the `post_type` arguments for posts so they are no longer public and removes all post_type support parameters. This disables the post-related admin redirects, as WordPress will now show users an error page stating "Sorry, you are not allowed to edit posts in this post type." It also pulls posts out of a lot of other locations (menus, etc) and is a much more efficient method of "disabling" the post type. This method is also used on built-in taxonomies, unless another post type supports them. **This change may impact other plugins or themes, be sure to back up your site and, if you can, test these changes prior to updating the plugin on a production site.**
- Disable pingbacks entirely.
- Fix comment redirect/menu functionality, now correctly removes comments and redirects `edit-comments.php` admin page if no other post type support comments (note that WordPress default is for pages and attachments to support comments).
- Disable XMLRPC for posts and tags/categories. Tag/categories remain if another post type supports them.
- Add basic static php tests and update code to pass those test. Huge props to @szepeviktor.
- Initiate plugin via hook into `plugins_loaded`.
- Change the admin notice related to blog and home page settings, only showing notices if no homepage is set or if the blog and homepage are the same page.
- Flush rewrite rules at activation and deactivation.
- Filtering out `post` post types from all archives, previously it was just author archives and search results.
- Removes post, category, and tag options from all menus. Tag/categories remain if another post type supports them.
- Remove header feed urls, unless supported by another post type.
- WordPress 5.5 support:
	- Remove 'post' post type from XML sitemaps.
	- Remove built-in taxonomies from XML sitemaps, if not being used by a custom post type.
	- Fix sitemap redirect issues.
- **Developers:** Filters were removed and altered in this version:
	- The `dwpb_redirect_feeds` filter now has (3) params, to match those in the `dwpb_disable_feed` filter: $bool, $post, $is_comment_feed.
	- The `dwpb_author_post_types` filter is now `dwpb_archive_post_types`, as the query modification now includes all pages passing `is_archive`.
	- Removed filters: `dwpb_disable_rest_api`, `dwpb_remove_post_comment_support`, `dwpb_remove_post_trackback_support`, `dwpb_redirect_admin_edit_single_post`, `dwpb_redirect_single_post_edit`, `dwpb_redirect_admin_edit_post`, `dwpb_redirect_edit`, `dwpb_redirect_admin_post_new`, `dwpb_redirect_post_new` as these are rendered obsolete by above changes.

= 0.4.8.1 =
- Forgot to update the version number in the main plugin file, so this is a version bump only. See 0.4.8 release notes for changes since 0.4.7.

= 0.4.8 =
- Fixed typo in variable name for current vs redirect url check.
- Update function names from template to `disable_blog`.
- Add WP.org Badge to readme.md.
- Change the name of the CI workflow to be specific to deployment.
- Some code tidying and inline documentation.

= 0.4.7 =
* Using GitHub actions publish on WP.org from github releases.
* Cleaned up the Reading settings, adding admin notices if front page is not set.
* Add check for Multisite to avoid network page redirects. Closes #17, props to @Mactory.
* Added Contributing and Code of Conduct documentation.
* Check that `is_singular` works prior to running redirects to avoid non-object errors in feeds.

= 0.4.6 =
Added check on disable feed functionality to confirm post type prior to disabling feed.

= 0.4.5 =
* Remove the functionality hiding the Settings > Writing admin page, allow this option to be re-enabled via the older filter. This page used to be entirely related to posts, but is also used to select the editor type (Gutenberg vs Classic).

= 0.4.4 =
* Hide the Settings > Writing menu item, which shows up with Disable Comments enabled everywhere. Thanks to @dater for identifying.

= 0.4.3 =
* Fixes compatibility issues with WooCommerce (versions 2.6.3 and older)
* Clean up comment functions and clear comment caches on activation/deactivation

= 0.4.2 =
* Disable the REST API for 'post' post type. Props to @shawnhooper.

= 0.4.1 =
* Fix unintended redirect for custom admin pages under tools.php. Props to @greatislander for the catch.

= 0.4.0 =
A bunch of updates and fixes.

= 0.3.3 =
bugfixes
