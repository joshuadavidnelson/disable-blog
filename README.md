Disable Blog
======================

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/disable-blog)](https://wordpress.org/plugins/disable-blog/) ![Downloads](https://img.shields.io/wordpress/plugin/dt/disable-blog.svg) ![Rating](https://img.shields.io/wordpress/plugin/r/disable-blog.svg)

**Requires at least:** 4.0  
**Tested up to WordPress:** 5.6  
**Stable version:** 0.4.9  
**License:** GPLv2 or later  
**Requires PHP:** 5.6  
**Tested up to PHP:** 7.4

## Description

Go blog-less with WordPress. This plugin disables all blog-related functionality (by hiding, removing, and redirecting).

Does the following:

- Turns the `post` type into a non-public content type, with support for zero post type features. Any attempts to edit or view posts within the admin screen will be met with a WordPress error page or be redirect to the homepage.

- Front-end:
	- Disables the post feed and remoives the feed links from the header (for WP >= 4.4.0) and disables the comment feed/removes comment feed link if 'post' is the only post type supporting comments (note that the default condition pages and attachments support comments).
	- Removes posts from all archive pages.
	- Remove 'post' post type from XML sitemaps and categories/tags from XML sitemaps, if not being used by a custom post type (WP Version 5.5).
	- Disables the REST API for 'post' post type, as well as tags & categories (if not used by another custom post type).
	- Disables XMLRPC for posts, as well as tags & categories (if not used by another custom post type).
	- Removes post sitemaps and, if not supported via the `dwpb_redirect_author_archive` filter, user sitemaps. User sitemaps can be toggled back on via that filter or directly passing `false` to the `dwpb_disable_user_sitemap` filter.
	- Redirects (301):
		- All Single Posts & Post Archive urls to the homepage (requires a 'page' as your homepage in Settings > Reading)
		- The blog page to the homepage.
		- All Tag & Category archives to home page, unless they are supported by a custom post type.
		- Date archives to the homepage.
		- As of v0.4.11 redirect author archives to the homepage, unless custom post types are passed via the `dwpb_redirect_author_archive` filter.

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
	- Hide options in Reading Settings page related to posts (shows front page and search engine options).
	- Removes 'Post' options on 'Menus' admin page.
	- Filters 'post' post type out of main query.
	- Disables "Press This" functionality.
	- Disables post by email configuration.
	- Hides Category and Tag permalink base options, if they are not supported by a custom post type.
	- Hides "Toggle Comments" link on Welcome screen if comments are only supported for posts.
	- Hides default category and default post format on Writing Options screen.
	- Replace the REST API availability site health check with a duplicate function that uses the `page` type instead of the `post` type (avoids false positive error in Site Health).
	- Replaces the "Posts" column in the user table with "Pages," linked to pages by that author.

**Note that this plugin will not delete anything - existing posts, comments, categories and tags will remain in your database.** 

If Settings > Reading > Front Page Displays is not set to show on a page, then some aspects of the plugin won't work, be sure to set your front page to a static page.

#### Contributing

All contributions are welcomed and considered, please refer to [contributing.md](contributing.md).

#### FAQ

1. Why Not Disable Comments Entirely?
 - This could be done, but other post types (like Pages) may have comment support. If you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin
2. I want to delete my posts and comments
 - Deactivate the plugin, delete your posts (which will delete related comments), and delete any tags or categories you might want to remove as well. Then reactivate the plugin to hide everything.
