Disable WordPress Blog
======================

A plugin to disable the blog functionality of WordPress (by hiding, removing, and redirecting). Useful when you want a WordPress site to remain static and hide blog-related elements from admin users.

*Note that this is a beta version, some aspects may still be in need of debugging and refining*

Does the following:
- Removes 'Posts' Admin Menu
- Disables the Feed
- Redirects New Post and Edit Post admin pages to New Page and Edit Page admin pages
- Redirects Comments admin page with query variable for 'post' post_type to main comments page
- Removes Post from '+New' admin bar menu
- Filters out the 'post' post type form comments admin page
- Removes post related dashboard widgets
- Hides number of posts on Activity dashboard widget
- Redirects 'Writing' Options to General Options
- Removes 'Writing' Options from Settings Menu
- Hides Posts on Nav Menu Admin Page
- Removes Post Related Widgets

**Note that this plugin will not delete anything and it does not make any changes to your database. Only one settings is forced: `pages_for_posts`**

#### FAQ

1. Why Not Disable Comments Entirely?
 - This could be done, but other post types (like Pages) may have comment support. If you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin
