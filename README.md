Disable Blog
======================

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/disable-blog)](https://wordpress.org/plugins/disable-blog/)

**Requires at least:** 3.1.0
**Tested up to:** 5.5.1
**Stable version:** 0.4.9
**License:** GPLv2 or later
**Requires PHP:** 5.3

## Description

Go blog-less with WordPress. This plugin disables all blog-related functionality (by hiding, removing, and redirecting).

Does the following:

- Turns the `post` type into a non-public type, with support for zero post type features. Any attempts to edit or view posts within the admin screen will be met with a WordPress error page.

- Front-end:
	- Remove the Feed links from the header.
	- Removes the feed link from front end (for WP >= 4.4.0), removes comment feed link if 'post' is the only post type supporting comments.
	- Removes posts from all archive pages.
	- Disables the Feed for Posts.
	- Remove 'post' post type from XML sitemaps and built-in taxonomies from XML sitemaps, if not being used by a custom post type (WP Version 5.5).
	- Disable comments feed only if 'post' is only type shown.
	- Disables the REST API for 'post' post type, as well as tags & categories (if not used by another custom post type).
	- Disables XMLRPC for posts, as well as tags & categories (if not used by another custom post type).
	- Redirects (301):
		- All Single Posts & Post Archive urls to the homepage (requires a 'page' as your homepage in Settings > Reading)
		- The blog page to the homepage.
		- All Tag & Category archives to home page, unless they are supported by a custom post type.

- Admin side:
	- Redirects tag and category pages to dashboard, unless used by a custom post type.
	- If comments are not supported by other post types (by default comments are supported by pages and attachments), it will hide the menu links for and redirect discussion options page and 'Comments' admin page to the dashboard.
	- Filters out the 'post' post type fromm 'Comments' admin page.
	- Alters the comment count to remove any comments associated with 'post' post type.
	- Optionally remove/redirect the Settings > Writting page via `dwpb_redirect_admin_options_writing` filter (default is false).
	- Removes Available Tools from admin menu and redirects page (houses Press This and Category/Tag converter).
	- Removes Post from '+New' admin bar menu.
	- Removes 'Posts' Admin Menu.
	- Removes post-related dashboard widgets.
	- Hides number of posts and comment count on Activity dashboard widget.
	- Removes Post Related Widgets.
	- Hide options in Reading Settings page related to posts (shows front page and search engine options).
	- Places "Pages" above "Media" in admin menu and removes divider below dashboard.
	- Removes 'Post' options on 'Menus' admin page.
	- Filters 'post' post type out of main query.
	- Disables "Press This" functionality.
	- Disables post by email configuration.

**Note that this plugin will not delete anything - existing posts, comments, categories and tags will remain in your database.** 

If Settings > Reading > Front Page Displays is not set to show on a page, then some aspects of the plugin won't work, be sure to set your front page to a static page.

#### Contributing

All contributions are welcomed and considered, please refer to [contributing.md](contributing.md).

#### FAQ

1. Why Not Disable Comments Entirely?
 - This could be done, but other post types (like Pages) may have comment support. If you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin
2. I want to delete my posts and comments
 - Deactivate the plugin, delete your posts (which will delete related comments), and delete any tags or categories you might want to remove as well. Then reactivate the plugin to hide everything.
