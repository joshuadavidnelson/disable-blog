=== Disable Blog ===
Contributors: joshuadnelson
Donate link: https://joshuadnelson.com/donate/
Tags: remove blog, disable blog, disable settings, disable blogging, disable feeds, posts, feeds
Requires at least: 4.0
Requires PHP: 5.6
Tested up to: 5.9.3
Stable tag: 0.5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

All the power of WordPress, without a blog.

== Description ==

Free your WordPress site from posts! Maintain a static website, a blog-less WordPress.

Disable Blog is a comprehensive plugin to disable the built-in blogging functionality on your site. You'll be free to use pages and custom post types without the burden of a blog.

The blog is "disabled" mostly by removing the core 'post' type, hiding blog-related admin pages/settings, and redirecting urls on both the public and admin portions of your site. Specifically does the following:

- Turns the `post` type into a non-public content type, with support for zero post type features. Any attempts to edit or view posts within the admin screen will be met with a WordPress error page or be redirect to the homepage.

- Front-end:
	- Disables the post feed and remoives the feed links from the header (for WP >= 4.4.0) and disables the comment feed/removes comment feed link if 'post' is the only post type supporting comments (note that the default condition pages and attachments support comments).
	- Removes posts from all archive pages.
	- Remove 'post' post type from XML sitemaps and categories/tags from XML sitemaps, if not being used by a custom post type (WP Version 5.5).
	- Disables the REST API for 'post' post type, as well as tags & categories (if not used by another custom post type).
	- Disables XMLRPC for posts, as well as tags & categories (if not used by another custom post type).
	- Disable author archives (redirect to homepage) via `dwpb_disable_author_archives` filter. Add the following to your theme functions.php file or a custom plugin file: `add_filter( 'dwpb_disable_author_archives', '__return_true' );` The plugin by default does not disable author archives because numerous other plugins use author archives for other purposes. (A future settings page will provide more flexibility here). Change the url being used in the redirect with the `dwpb_redirect_author_archive` filter.
	- Removes post sitemaps and, if not supported via the `dwpb_disable_author_archive` filter, user sitemaps. User sitemaps can be toggled back on via that filter or directly passing `false` to the `dwpb_disable_user_sitemap` filter.
	- Redirects (301):
		- All Single Posts & Post Archive urls to the homepage (requires a 'page' as your homepage in Settings > Reading)
		- The blog page to the homepage.
		- All Tag & Category archives to home page, unless they are supported by a custom post type.
		- Date archives to the homepage.
		- Author archives are not redirected by default, but can per the above mentioned `dwpb_disable_author_archives` filter.

- Admin side:
	- Redirects tag and category pages to dashboard, unless used by a custom post type.
	- Redirects post related screens (`post.php`, `post-new.php`, etc) to the `page` version of the same page.
	- If comments are not supported by other post types (by default comments are supported by pages and attachments), it will hide the menu links for and redirect discussion options page and 'Comments' admin page to the dashboard.
	- Filters out the 'post' post type from 'Comments' admin page.
	- Alters the comment count to remove any comments associated with 'post' post type.
	- Optionally remove/redirect the Settings > Writting page via `dwpb_remove_options_writing` filter (default is false).
	- Removes Available Tools from admin menu and redirects page to the dashboard (this admin page contains Press This and Category/Tag converter, both are no longer neededd without a blog).
	- Removes Post from '+New' admin bar menu.
	- Removes 'Posts' Admin Menu.
	- Removes post-related dashboard widgets.
	- Hides number of posts and comment count on Activity dashboard widget.
	- Removes Post Related Widgets.
	- Hide options in Reading Settings page related to posts (shows front page and search engine options), as well as matching section in Cusomizer > Homepage Settings view.
	- Removes 'Post' options on 'Menus' admin page.
	- Filters 'post' post type out of main query.
	- Disables "Press This" functionality.
	- Disables post by email configuration.
	- Hides Category and Tag permalink base options, if they are not supported by a custom post type.
	- Hides "Toggle Comments" link on Welcome screen if comments are only supported for posts.
	- Hides default category and default post format on Writing Options screen.
	- Replace the REST API availability site health check with a duplicate function that uses the `page` type instead of the `post` type (avoids false positive error in Site Health).
	- Replaces the "Posts" column in the user table with "Pages," linked to pages by that author.
	- Remove the "view" link to author archives in the user screen if author archives are not supported.
	- Updates the post tag and category "count" columns to correctly show the number of posts by post type, for use with custom post types supporting built-in taxonomies.

**Important**: If Settings > Reading > "Front Page Displays" is not set to show on a page, then this plugin will not function correctly. **You need to select a page to act as the home page**. Not doing so will mean that your post page can still be visible on the front-end of the site. Note that it's not required, but recommended you select a page for the  "posts page" setting, this page will be automatically redirected to the static "home page."

**Site Content & Data**: This plugin will not delete any of your site's data, however it does by default redirect all posts and post comments to the homepage (refer to the documentation on ways to change this behavior).

If you have any posts, comments, categories, and/or tags, delete them prior to activation (or deactivate this plugin, delete them, and re-active). If you don't delete them, they will remain in your database and become accessible if you deactivate this plugin or modify the plugin behavior to show posts.

**Comments**: Comments remain enabled, unless the 'post' type is the only type supporting comments (pages also support comments by default, so the comments section won't disappear in most cases). If you're looking to disable comments completely, check out the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin.

**Categories & Tags**: These are hidden and redirected, unless they are supported by a custom post type.

**Custom Post Types**: This plugin includes extensive support for custom post types and taxonomies. If you are using a custom post type that supports the built-in `category` and/or `post_tag` taxonomies, they will be visible and accessible through that post type.

**Support**: This plugin is maintained for free but **please reach out** and I will assist you as soon as possible. You can visit the [support forums](https://wordpress.org/support/plugin/disable-blog) or the [issue](https://github.com/joshuadavidnelson/disable-blog/issues) section of the [GitHub repository](https://github.com/joshuadavidnelson/disable-blog).

= View on GitHub & Contribute =
[View this plugin on GitHub](https://github.com/joshuadavidnelson/disable-blog) to contribute as well as log any issues (or visit the WP [support forums](https://wordpress.org/support/plugin/disable-blog)).

Please feel free to contribute!

== Installation ==

This section describes how to install the plugin and get it working.

1. Add the plugin viw Plugins > Add New, or manually upload the `disable-blog` plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Why Not Disable Comments Entirely? =

This could be done, but other post types (like Pages) may have comment support. If you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin.

= I want to delete my posts and comments. =

Deactivate the plugin, delete your posts (which will delete related comments), and delete any tags or categories you might want to remove as well. Then reactivate the plugin to hide everything.

= How can I disable author archives? =

If you're not using the built-in WP author archives (typically at `example.com/author/joshua` urls) and would like to disable them entirely, add the following to your theme functions.php file or a custom plugin file: `add_filter( 'dwpb_disable_author_archives', '__return_true' );`. If author archives are not disabled, the plugin adds functionality to support custom post types on author archives by passing an array of post type slugs to `dwpb_author_archive_post_types` filter - however, theme support is usually needed to disable custom content types correctly.


= How can I change the plugin's behavior? =

There are numerous filters available to change the way this plugin works. Refer to the [GitHub page](https://github.com/joshuadavidnelson/disable-blog) or reach out on the [support forums](https://wordpress.org/support/plugin/disable-blog) if you have any questions.

== Changelog ==

= 0.5.0 =

**New:**
- Add disable author archive functionality via new `dwpb_disable_author_archives` filter. Pass `true` to disable author archives entirely. Default does not disable author archives because numerous other plugins use author archives for other purposes. (A future settings page will provide more flexibility here).
- Add `dwpb_author_archive_post_types` filter to provide author archive support for custom post types. Pass an array of post type slugs to this filter to modify the post types queried on author archives, if not removed by filter above.
- Add `dwpb_xmlrpc_methods_to_remove` filter to extend the methods being disabled by the plugin. Pass `false` to remove the functionality entirely. Closes #50
- Remove user sitemaps unless author archives are supported by custom post types via the filter noted above.
- Replace the "Posts" column on the user admin screen by a "Pages" column, also adds similar columns for custom post types using the filter noted above.
- Remove the "view" link to author archives in the user screen if author archives are not supported.
- Update the post tag and category "count" columns to correctly show the number of posts by post type, for use with custom post types supporting built-in taxonomies.
- Update the customizer "Homepage" view to match the Reading options page view, when homepage is set.
- Update the default posts page admin notice indicating the blog is redirected.
- New `dwpb_pass_query_string_on_redirect` filter, returning `true` enables all valid url query params to be passed during redirects. Default is false. Use the `dwpb_allowed_query_vars` to limit the vars passed in the redirect by passing an array of keys (e.g. return array( 'utm' ) to only pass utm query variables in the query string). Closes #52
- Add javascript to hide admin screen items not easily selected by CSS, include:
	- Hiding toggle comment link on welcome screen (if they are not supported by other post types),
	- Hiding the category and tag permalink base options (if not supported by other post types), and
	- Hiding the default category & default post format on Writing options page.

**Fixes:**
- Bring back some admin page redirects to account for use cases where direct access to `post.php`, `post-new.php`, etc occur. Closes #45.
- Replace the REST API site health check (which uses the `post` type) with a matching function using the `page` endpoint instead. This was throwing an error with the `post` type REST endpoints are disabled. Closes #46.
- Fix issue with Reading Settings link in admin notice outputting raw HTML instead of a link. Closes #47.
- In order to account for multiple subpages of a common parent page being removed the `dwpb_menu_subpages_to_remove` param has been updated to support an array of subpages in the format of `$remove_subpages['parent-page-slug.php'] = array( 'subpage-1.php', 'subpage-2.php' );`, though it still supports subpages as strings for backwards compatibility. Fixes bugs were `options-writing.php` and `options-discussion.php` were conflicting.

**Improvements/Updates:**
- Update admin filters to a common format and removing redundent filters. Filter changes include:
	- New filter: `dwpb_redirect_admin_url` filters the final url used in admin redirects.
	- `dwpb_redirect_admin` only accepts 1 parameter, the previous version accepted 3 (dropping `$redirect_url` & `$current_url`).
	- `dwpb_redirect_admin_edit_post` is now `dwpb_redirect_admin_edit`.
	- `dwpb_redirect_single_post_edit` is now `dwpb_redirect_admin_post`.
	- `dwpb_redirect_admin_edit_single_post` is now `dwpb_redirect_admin_edit`.
	- `dwpb_redirect_edit_tax` has been removed. Use `dwpb_redirect_admin_edit_tags` or `dwpb_redirect_admin_term` instead, depending on the context.
	- `dwpb_redirect_edit_comments` has been removed. use `dwpb_redirect_admin_edit_comments` instead.
	- `dwpb_redirect_options_discussion` has been removed. Use `dwpb_redirect_admin_options_discussion` instead.
	- The filter `dwpb_redirect_admin_options_writing` that would pass a boolean to toggle off the options writing page has been remaned `dwpb_remove_options_writing` and must be passed with `true` in order to have the page redirect _and_ the admin menu item removed. By default the value filtered is false and the options Writing page does not go away, as numerous other plugins use this page for non-blog related settings. Now `dwpb_redirect_admin_options_writing` is used to filter the redirect url itself, replacing the previously named `dwpb_redirect_options_writing` filter.
	- `dwpb_redirect_options_tools` has been removed. Use `dwpb_redirect_admin_options_tools` instead.
	- New filter: `dwpb_xmlrpc_methods_to_remove` (see above).
	- New filter: `dwpb_author_archive_post_types` (see above).
	- New filter: `dwpb_disable_author_archives` (see above).
- Update public redirect filters to match the pattern used for the new admin redirects. Filer changes include:
	- New filter: `dwpb_front_end_redirect_url` filters the final url used in front end redirects.
	- New filter: `dwpb_redirect_author_archive` to change the redirect used on author archives, if they are disabled.
	- New filter: `dwpb_disable_user_sitemap` to change the user sitemap default, pass `true` to keep disable the sitmap. Note that if author archives are disabled by the above filter, user sitemps will be as well.
	- `dwpb_redirect_posts` is now `dwpb_redirect_post`.
	- `dwpb_redirect_post_{$post->ID}` filter has been removed. Use `dwpb_redirect_post` and check for the post id to target a specific post.
	- `dwpb_redirect_front_end` only accepts 1 parameter, the previous version accepted 3 (dropping `$redirect_url` & `$current_url`).
- Bump minimum PHP to 5.6.
- Tested up to WP Core version 5.9.3.
- Updated minimum WP Core version to 4.0.
- Updated translation file for all current plugin strings.

= 0.4.10 =
- Fix a bug from v0.4.9 that caused redirects on custom post type archives, correcting the `modify_query` function to only remove posts from built-in taxonomy archives, as that was the original intent.

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
* Add check for Multisite to avoid network page redirects. Props to @Mactory.
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

= 0.5.0 =

**New:**
- Add disable author archive functionality via new `dwpb_disable_author_archives` filter. Pass `true` to disable author archives entirely. Default does not disable author archives because numerous other plugins use author archives for other purposes. (A future settings page will provide more flexibility here).
- Add `dwpb_author_archive_post_types` filter to provide author archive support for custom post types. Pass an array of post type slugs to this filter to modify the post types queried on author archives, if not removed by filter above.
- Add `dwpb_xmlrpc_methods_to_remove` filter to extend the methods being disabled by the plugin. Pass `false` to remove the functionality entirely. Closes #50
- Remove user sitemaps unless author archives are supported by custom post types via the filter noted above.
- Replace the "Posts" column on the user admin screen by a "Pages" column, also adds similar columns for custom post types using the filter noted above.
- Remove the "view" link to author archives in the user screen if author archives are not supported.
- Update the post tag and category "count" columns to correctly show the number of posts by post type, for use with custom post types supporting built-in taxonomies.
- Update the customizer "Homepage" view to match the Reading options page view, when homepage is set.
- Update the default posts page admin notice indicating the blog is redirected.
- New `dwpb_pass_query_string_on_redirect` filter, returning `true` enables all valid url query params to be passed during redirects. Default is false. Use the `dwpb_allowed_query_vars` to limit the vars passed in the redirect by passing an array of keys (e.g. return array( 'utm' ) to only pass utm query variables in the query string). Closes #52
- Add javascript to hide admin screen items not easily selected by CSS, include:
	- Hiding toggle comment link on welcome screen (if they are not supported by other post types),
	- Hiding the category and tag permalink base options (if not supported by other post types), and
	- Hiding the default category & default post format on Writing options page.

**Fixes:**
- Bring back some admin page redirects to account for use cases where direct access to `post.php`, `post-new.php`, etc occur. Closes #45.
- Replace the REST API site health check (which uses the `post` type) with a matching function using the `page` endpoint instead. This was throwing an error with the `post` type REST endpoints are disabled. Closes #46.
- Fix issue with Reading Settings link in admin notice outputting raw HTML instead of a link. Closes #47.
- In order to account for multiple subpages of a common parent page being removed the `dwpb_menu_subpages_to_remove` param has been updated to support an array of subpages in the format of `$remove_subpages['parent-page-slug.php'] = array( 'subpage-1.php', 'subpage-2.php' );`, though it still supports subpages as strings for backwards compatibility. Fixes bugs were `options-writing.php` and `options-discussion.php` were conflicting.

**Improvements/Updates:**
- Update admin filters to a common format and removing redundent filters. Filter changes include:
	- New filter: `dwpb_redirect_admin_url` filters the final url used in admin redirects.
	- `dwpb_redirect_admin` only accepts 1 parameter, the previous version accepted 3 (dropping `$redirect_url` & `$current_url`).
	- `dwpb_redirect_admin_edit_post` is now `dwpb_redirect_admin_edit`.
	- `dwpb_redirect_single_post_edit` is now `dwpb_redirect_admin_post`.
	- `dwpb_redirect_admin_edit_single_post` is now `dwpb_redirect_admin_edit`.
	- `dwpb_redirect_edit_tax` has been removed. Use `dwpb_redirect_admin_edit_tags` or `dwpb_redirect_admin_term` instead, depending on the context.
	- `dwpb_redirect_edit_comments` has been removed. use `dwpb_redirect_admin_edit_comments` instead.
	- `dwpb_redirect_options_discussion` has been removed. Use `dwpb_redirect_admin_options_discussion` instead.
	- The filter `dwpb_redirect_admin_options_writing` that would pass a boolean to toggle off the options writing page has been remaned `dwpb_remove_options_writing` and must be passed with `true` in order to have the page redirect _and_ the admin menu item removed. By default the value filtered is false and the options Writing page does not go away, as numerous other plugins use this page for non-blog related settings. Now `dwpb_redirect_admin_options_writing` is used to filter the redirect url itself, replacing the previously named `dwpb_redirect_options_writing` filter.
	- `dwpb_redirect_options_tools` has been removed. Use `dwpb_redirect_admin_options_tools` instead.
	- New filter: `dwpb_xmlrpc_methods_to_remove` (see above).
	- New filter: `dwpb_author_archive_post_types` (see above).
	- New filter: `dwpb_disable_author_archives` (see above).
- Update public redirect filters to match the pattern used for the new admin redirects. Filer changes include:
	- New filter: `dwpb_front_end_redirect_url` filters the final url used in front end redirects.
	- New filter: `dwpb_redirect_author_archive` to change the redirect used on author archives, if they are disabled.
	- New filter: `dwpb_disable_user_sitemap` to change the user sitemap default, pass `true` to keep disable the sitmap. Note that if author archives are disabled by the above filter, user sitemps will be as well.
	- `dwpb_redirect_posts` is now `dwpb_redirect_post`.
	- `dwpb_redirect_post_{$post->ID}` filter has been removed. Use `dwpb_redirect_post` and check for the post id to target a specific post.
	- `dwpb_redirect_front_end` only accepts 1 parameter, the previous version accepted 3 (dropping `$redirect_url` & `$current_url`).
- Bump minimum PHP to 5.6.
- Tested up to WP Core version 5.9.3.
- Updated minimum WP Core version to 4.0.
- Updated translation file for all current plugin strings.

= 0.4.10 =
- Fix a bug from v0.4.9 that caused redirects on custom post type archives.

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
* Add check for Multisite to avoid network page redirects, props to @Mactory.
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
