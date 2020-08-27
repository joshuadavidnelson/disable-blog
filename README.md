Disable Blog
======================

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/disable-blog)](https://wordpress.org/plugins/disable-blog/)

**Requires at least:** 3.1.0  
**Tested up to:** 5.5  
**Stable version:** 0.4.9
**License:** GPLv2 or later

## Description

Go blog-less with WordPress. This plugin disables all blog-related functionality (by hiding, removing, and redirecting).

Does the following:

- Removes 'Posts' Admin Menu.
- Removes 'post' post type from queries.
- Disables the Feed for Posts.
- Disable post by email configuration.
- Disables the REST API for 'post' post type.
- Disables XMLRPC for posts, as well as tags & categories (if not used by another custom post type).
- Remove the Feed links from the header.
- Redirects 'New Post' and 'Edit Post' admin pages to 'New Page' and 'Edit Page' admin pages.
- Redirects 'Comments' admin page with query variable `post_type=post` to main comments page.
- Disable comments feed only if 'post' is only type shown.
- Redirects Single Posts, Post Archives, Tag & Category archives to home page (the latter two are only redirected if 'post' post type is the only post type associated with it).
- Filters out the 'post' post type fromm 'Comments' admin page.
- Removes Post from '+New' admin bar menu.
- Filters 'post' post type out of main query.
- Removes post-related dashboard widgets.
- Hides number of posts and comment count on Activity dashboard widget.
- Hides 'Posts' options on 'Menus' admin page.
- Removes Post Related Widgets.
- Disables "Press This" functionality.
- Removes Available Tools from admin menu and redirects page (houses Press This and Category/Tag converter).
- Hide/redirect discussion options page if 'post' is the only post type supporting it (typically supported by pages).
- Removes the feed link from front end (for WP >= 4.4.0), removes comment feed link if 'post' is the only post type supporting comments.
- Hide options in Reading Settings page related to posts (shows front page and search engine options).
- Hides other post-related reading options, except Search Engine Visibilty.
- Removes post from author archive query.
- Removes comment and trackback support for posts.
- Removes and disabled pingbacks/trackbacks on `post` post type.
- Places "Pages" above "Media" in admin menu and removes divider below dashboard.
- Alters the comment count to remove any comments associated with 'post' post type.
- Disables the REST API for 'post' post type.
- Remove 'post' post type from XML sitemaps (WP version 5.5)
- Remove built-in taxonomies from XML sitemaps (WP version 5.5), if not being used by a custom post type.

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
- Settings Page:
	- Toggle all features.
	- Add options to disable emoji, oembed, etc.
	- Add option to disable author archives.
- Dealing with existing post content:
	- Remove posts from Media Library "uploaded to" column - Filter the "uploaded to" or the attachment's parent, if it's a post then don't show it on the media library or elsewhere (probably filtering the parent to check if it's a post and returning 0 if so would be the best approach)
	- Remove posts from comment feeds, if comments are enabled
	- Add an option in the Tools section to delete all blog content (post, categories, tags, linked attachments, and comments).
	- Functionality to keep old posts accessible, marked as "archived", etc.
- Enhanced custom post type / taxonomy support:
	- Change post count in category and tag screen, if taxonomies are supported by another post type (update the post count callback and flush the cache/rebuild the counts).
- Remove Feeds from Meta Widget.
- Filter or remove post-related topics in help tabs.
- Update "homepage settings" in Customizer to match admin page experience for setting front page & blog page.
- Hide or remove post category and post format options on writing settings page.
- Remove the widget submenu from the Appearance menu, if there are no widget areas.
- Unit & CI tests.
- In permaliks page:
	- Change the "post name" and `sample-post` on the permalink settings page to be "Page Name" and `sample-page`.
	- Change the %postname% to %pagename (but setup permalinks to work with either via filter).
	- Remove options for %category% and %post_id% (change to %page_id% ?).
	- Remove category base and tag base permalink options, if not supported by a custom post type.
	- Remove "custom structures" section in help tab & update Overview & Permalink Settings tabs to remove "post" references.
- Authors/user related:
	- Author archives: redirect to homepage if only post type support on  is 'post'
	- Author sitemaps: remove sitemaps for author archives if only post type support is 'post'
	- Remove biographical informtion from user/profile page (and yoast & genesis versions) if author archives are a) disabled or b) only support "post" post type.