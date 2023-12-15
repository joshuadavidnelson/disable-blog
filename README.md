Disable Blog
======================

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/disable-blog)](https://wordpress.org/plugins/disable-blog/) ![Downloads](https://img.shields.io/wordpress/plugin/dt/disable-blog.svg) ![Rating](https://img.shields.io/wordpress/plugin/r/disable-blog.svg)

**Requires at least WordPress:** 5.3  
**Tested up to WordPress:** 6.4.2  
**Stable version:** 0.5.4  
**License:** GPLv2 or later  
**Requires PHP:** 7.4  
**Tested up to PHP:** 8.2  

All the power of WordPress, without a blog.

## Description

Disable Blog is a comprehensive plugin to disable the built-in blogging functionality on your site. You'll be free to use pages and custom post types without the burden of a blog.

The blog is "disabled" when the plugin is activated, which removes support for the core 'post' type, hides blog-related admin pages/settings, and redirects urls on both the public and admin portions of the site. Refer to [below](#how-does-this-plugin-work) for a detailed functionality list.

### Important: Set a front page

**You need to select a page to act as the home page**. If Settings > Reading > "Front Page Displays" is not set to show a page, then this plugin will not function correctly. Not doing so will mean that your post page can still be visible on the front-end of the site. It's not required, but it is recommended you select a page for the  "posts page" setting, this page will be automatically redirected to the static "home page."

### Site Content & Data

This plugin will not delete any of your site's data, however existing blog related content will not be accessible while this plugins is active. This includes posts, categories, tags, and related comments.

If you have content and wish to remove it, either delete that content prior to activation or deactivate this plugin, delete it, and re-active. 

### Comments

Comments remain enabled, unless the 'post' type is the only type supporting comments (pages also support comments by default, so the comments section won't disappear in most cases). If you're looking to disable comments completely, check out the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin.

### How does this plugin work?

Activating Disable Blog does the following:

- Turns the `post` type into a non-public content type, with support for zero post type features. Any attempts to edit or view posts within the admin screen will be met with a WordPress error page or be redirect to the homepage.

- Front-end:
	- Disables the post feed and removes the feed links from the header (for WP >= 4.4.0) and disables the comment feed/removes comment feed link if 'post' is the only post type supporting comments (note that the default condition pages and attachments support comments).
	- Removes posts from all archive pages.
	- Remove 'post' post type from XML sitemaps and categories/tags from XML sitemaps, if not being used by a custom post type (WP Version 5.5).
	- Disables the REST API for 'post' post type, as well as tags & categories (if not used by another custom post type).
	- Disables XMLRPC for posts, as well as tags & categories (if not used by another custom post type).
	- Disable author archives (redirect to homepage) via `dwpb_disable_author_archives` filter. Add the following to your theme functions.php file or a custom plugin file: `add_filter( 'dwpb_disable_author_archives', '__return_true' );` The plugin by default does not disable author archives because numerous other plugins use author archives for other purposes. (A future settings page will provide more flexibility here). Change the url being used in the redirect with the `dwpb_redirect_author_archive` filter.
	- Removes post sitemaps and, if not supported via the `dwpb_disable_author_archive` filter, removes user sitemaps. User sitemaps can be toggled back on via that filter or directly passing `false` to the `dwpb_disable_user_sitemap` filter.
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
	- Optionally remove/redirect the Settings > Writing page via `dwpb_remove_options_writing` filter (default is false).
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

**Note that this plugin will not delete anything - existing posts, comments, categories and tags will remain in your database.** 

If Settings > Reading > Front Page Displays is not set to show on a page, then some aspects of the plugin won't work, be sure to set your front page to a static page.

## FAQ

1. Can I Disable Comments?
 - Other post types (like Pages) may have comment support and other great plugins exist that can disable comments, so this feature was not part of the initial development of this plugin. A future release will include options to disable comments, but until then if you would like to disable comments, try the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin.
2. I want to delete my posts and comments
 - Deactivate the plugin, delete your posts (which will delete related comments), and delete any tags or categories you might want to remove as well. Then reactivate the Disable Blog to hide everything again.
3. How can I disable author archives?
 - If you're not using the built-in WP author archives for other purposes (example url: `example.com/author/author-name`) and would like to disable them entirely, add the following to your theme functions.php file or a custom plugin file: `add_filter( 'dwpb_disable_author_archives', '__return_true' );`. If author archives are not disabled, the plugin adds functionality to support custom post types on author archives by passing an array of post type slugs to `dwpb_author_archive_post_types` filter - however, theme support is usually needed to disable custom content types correctly.

## Support

This plugin is maintained for free but **please reach out** and I will assist you as soon as possible. You can visit the [WordPress.org support forums](https://wordpress.org/support/plugin/disable-blog/) or create an [issue](https://github.com/joshuadavidnelson/disable-blog/issues/) on the [GitHub repository](https://github.com/joshuadavidnelson/disable-blog/).

## Contributing

All contributions are welcomed and considered, please refer to [contributing.md](contributing.md).

### Pull requests
All pull requests should be directed at the `develop` branch, and will be reviewed prior to merging. No pull requests will be merged with failing tests, but it's okay if you don't initially pass tests. Please create a draft pull request for proof of concept code or changes you'd like to have input on prior to review.

Please make on a branch specific to a single issue or feature. For instance, if you are suggest a solution to an issue, please create fork with a branch like `issue-894`. Or if you are proposing a new feature, create a fork with the branch name indicating the feature like `feature-example-bananas`

All improvements are merged into `develop` and then queued up for release before being merged into `stable`. Releases are deployed via github actions to wordpress.org on tagging a new release.

### Main Branches

The `stable` branch is reserved for releases and intended to be a mirror of the official current release, or `trunk` on wordpress.org.

The `develop` branch is the most current working branch. _Please direct all pull requests to the `develop` branch_

### Developing Disable Blog Locally

**Requirements:**
- Docker
- Node Package Manager (npm)

This repo contains the files needed to boot up a local development environment using [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

Run `npm install` and the `npm run env:start` to boot up a local environment. 
