Disable WordPress Blog
======================

A plugin to disable the blog functionality of WordPress (by hiding, removing, and redirecting). Useful when you want a WordPress site to remain static and hide blog-related elements from admin users.

*Note that this is a beta version, some aspects may still be in need of debugging and refining*

Does the following:
- Removes 'Posts' Admin Menu
- Disables the Feed for Posts
- Redirects 'New Post' and 'Edit Post' admin pages to 'New Page' and 'Edit Page' admin pages
- Redirects 'Comments' admin page with query variable `post_type=post` to main comments page
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

**Note that this plugin will not delete anything, any existing posts, comments, categories and tags will remain in your database.** 

Only three settings are forced: `show_on_front`, `pages_for_posts`, and `posts_on_front`, if they are not already set

#### FAQ

1. Why Not Disable Comments Entirely?
 - This could be done, but other post types (like Pages) may have comment support. If you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin
2. I want to delete my posts and comments
 - Deactivate the plugin, delete your posts (which will delete related comments), and delete any tags or categories you might want to remove as well. Then reactivate the plugin to hide everything.
