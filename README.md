Disable Blog
======================

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/disable-blog)](https://wordpress.org/plugins/disable-blog/)

**Requires at least:** 3.1.0  
**Tested up to:** 5.4.1  
**Stable version:** 0.4.8.1
**License:** GPLv2 or later

## Description

Go blog-less with WordPress. This plugin disables all blog-related functionality (by hiding, removing, and redirecting). 

Does the following:

- Removes 'Posts' Admin Menu.
- Removes 'post' post type from most queries.
- Disables the Feed for Posts.
- Redirects 'New Post' and 'Edit Post' admin pages to 'New Page' and 'Edit Page' admin pages.
- Redirects 'Comments' admin page with query variable `post_type=post` to main comments page.
- Disable comments feed only if 'post' is only type shown.
- Redirects Single Posts, Post Archives, Tag & Category archives to home page (the latter two are only redirected if 'post' post type is the only post type associated with it).
- Filters out the 'post' post type fromm 'Comments' admin page.
- Removes Post from '+New' admin bar menu.
- Removes post-related dashboard widgets.
- Hides number of posts and comment count on Activity dashboard widget.
- Removes/Redirects 'Writing' Options from Settings Menu.
- Hides 'Posts' options on 'Menus' admin page.
- Removes Post Related Widgets.
- Disables "Press This" functionality.
- Forces Reading Settings: `show_on_front`, `pages_for_posts`, and `posts_on_front`, if they are not already set.
- Removes Available Tools from admin menu and redirects page (houses Press This and Category/Tag converter).
- Hide/redirect discussion options page if 'post' is the only post type supporting it (typically supported by pages).
- Filter comment counts to remove comments associated with 'post' post type.
- Remove feed link from front end (for WP >= 4.4.0), remove comment feed link if 'post' is the only post type supporting comments.
- Hide options in Reading Settings page related to posts (shows front page and search engine options only now).
- Hides other post-related reading options, except Search Engine Visibilty.
- Removes post from author archive query.
- Removes comment and trackback support for posts.
- Alters the comment count to remove any comments associated with 'post' post type.
- Disables the REST API for 'post' post type.

**Note that this plugin will not delete anything - existing posts, comments, categories and tags will remain in your database.** 

If Settings > Reading > Front Page Displays is not set to show on a page, then some aspects of the plugin won't work, be sure to set your front page to a static page.

#### Contributing

All contributions are welcomed and considered, please refer to [contributing.md](contributing.md).

#### FAQ

1. Why Not Disable Comments Entirely?
 - This could be done, but other post types (like Pages) may have comment support. If you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin
2. I want to delete my posts and comments
 - Deactivate the plugin, delete your posts (which will delete related comments), and delete any tags or categories you might want to remove as well. Then reactivate the plugin to hide everything.

#### Todo
- Enhanced support for tags and categories with custom post types (replace the count, tag cloud, etc to exclude posts)
- Change count in category and tag screen, if taxonomies are supported by another post type
- Change tag cloud in similar condition as above
- Remove posts from Media Library "uploaded to" column
- Remove Feeds from Meta Widget
- Disable front-end post query
- Filter or remove post-related topics in help tab (specifically in the Dashboard)
- Remove posts from comment feeds, if they are enabled
- Disable XML-RPC for posts
- Hide blog-related settings in customizer view
