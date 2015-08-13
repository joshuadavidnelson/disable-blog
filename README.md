Disable WordPress Blog
======================

**Requires at least:** 3.1.0
**Tested up to:** 4.1.0
**Stable version:** 0.3.1
**License:** GPLv2 or later

## Description
A plugin to disable the blog functionality of WordPress (by hiding, removing, and redirecting). Useful when you want a WordPress site to remain static and hide blog-related elements from admin users.

*This is a beta version, some aspects may still be in need of refining*

Does the following:
- Removes 'Posts' Admin Menu
- Removes 'post' post type from most queries
- Disables the Feed for Posts
- Redirects 'New Post' and 'Edit Post' admin pages to 'New Page' and 'Edit Page' admin pages
- Redirects 'Comments' admin page with query variable `post_type=post` to main comments page
- Redirects Single Posts, Post Archives, Tag & Category archives to home page (the latter two are only redirected if 'post' post type is the only post type associated with it)
- Filters out the 'post' post type fromm 'Comments' admin page
- Removes Post from '+New' admin bar menu
- Removes post-related dashboard widgets
- Hides number of posts and comment count on Activity dashboard widget
- Removes 'Writing' Options from Settings Menu
- Redirects 'Writing' Options to General Options
- Hides 'Posts' options on 'Menus' admin page
- Removes Post Related Widgets
- Disables "Press This" functionality
- Disables "Post By Email" functionality
- Forces Reading Settings: `show_on_front`, `pages_for_posts`, and `posts_on_front`, if they are not already set
- Hides other post-related reading options, except Search Engine Visibilty
- Removes post from author archive query

**Note that this plugin will not delete anything - existing posts, comments, categories and tags will remain in your database.** 

If Settings > Reading > Front Page Displays is not set to show on a page, then that setting will be forced by this plugin (includes three interrlated seetings: `show_on_front`, `pages_for_posts`, and `posts_on_front`)

#### FAQ

1. Why Not Disable Comments Entirely?
 - This could be done, but other post types (like Pages) may have comment support. If you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin
2. I want to delete my posts and comments
 - Deactivate the plugin, delete your posts (which will delete related comments), and delete any tags or categories you might want to remove as well. Then reactivate the plugin to hide everything.

##### Todo
- Enhanced support for tags and categories with custom post types (replace the count, tag cloud, etc to exclude posts)
- Remove posts from Media Library "uploaded to" column
- Change count in category and tag screen, if taxonomies are supported by another post type
- Change tag cloud in similar condition as above
- Remove Feeds from Meta Widget
- Disable front-end post query