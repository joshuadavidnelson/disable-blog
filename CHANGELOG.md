### Changelog

#### 0.4.11

**New:**
- Redirecting Author archives to homepage. Includes new `dwpb_author_archive_post_types` filter to provide author archive support for custom post types. Pass an array of post type slugs to this filter and the redirect goes away, author archives support those post types, and the user sitemap stays in place. Default is to remove the archives entirely, since posts are the only post type that show up on these archives.
- Remove user sitemaps unless author archives are supported by custom post types via the filter noted above.
- Replace the "Posts" column on the user admin screen by a "Pages" column, also adds similar columns for custom post types using the filter noted above.
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
	- `dwpb_redirect_admin` only accepts 2 parameters, the previous version accepted 3 (dropping `$current_url`).
	- `dwpb_redirect_admin_edit_post` is now `dwpb_redirect_admin_edit`.
	- `dwpb_redirect_single_post_edit` is now `dwpb_redirect_admin_post`.
	- `dwpb_redirect_admin_edit_single_post` is now `dwpb_redirect_admin_edit`.
	- `dwpb_redirect_edit_tax` has been removed. Use `dwpb_redirect_admin_edit_tags` or `dwpb_redirect_admin_term` instead, depending on the context.
	- `dwpb_redirect_edit_comments` has been removed. use `dwpb_redirect_admin_edit_comments` instead.
	- `dwpb_redirect_options_discussion` has been removed. Use `dwpb_redirect_admin_options_discussion` instead.
	- The filter `dwpb_redirect_admin_options_writing` that would pass a boolean to toggle off the options writing page has been remaned `dwpb_remove_options_writing` and must be passed with `true` in order to have the page redirect _and_ the admin menu item removed. By default the value filtered is false and the options Writing page does not go away, as numerous other plugins use this page for non-blog related settings. Now `dwpb_redirect_admin_options_writing` is used to filter the redirect url itself, replacing the previously named `dwpb_redirect_options_writing` filter.  
	- `dwpb_redirect_options_tools` has been removed. Use `dwpb_redirect_admin_options_tools` instead.
- Update public redirect filters to match the pattern used for the new admin redirects. Filer changes include:
	- New filter: `dwpb_front_end_redirect_url` filters the final url used in front end redirects.
	- New filter: `dwpb_redirect_author_archive` to change the redirect used on author archives.
	- New filter: `dwpb_disable_user_sitemap` to change the user sitemap default, pass "false" to keep the sitemap in place. Using the author archive post type filter will impact the sitemap - if author archives are enabled for custom post types, then the sitemap is on.
	- `dwpb_redirect_posts` is now `dwpb_redirect_post`.
	- `dwpb_redirect_post_{$post->ID}` filter has been removed. Use `dwpb_redirect_post` and check for the post id to target a specific post.
	- `dwpb_redirect_front_end` only accepts 2 parameters, the previous version accepted 3 (dropping `$current_url`).
- Bump minimum PHP to 5.6.
- Tested up to WP Core version 5.6.
- Updated minimum WP Core version to 4.0.
- Updated translation file for all current plugin strings.

#### 0.4.10
- Fix a bug from v0.4.9 that caused redirects on custom post type archives, correcting the `modify_query` function to only remove posts from built-in taxonomy archives, as that was the original intent.

#### 0.4.9
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

#### 0.4.8.1
- Do'h! Forgot to update the version number in the main plugin file. Bump.

#### 0.4.8
- Fixed typo in variable name for current vs redirect url check. (h/t @chesio, PR #30)
- Update function names from template to `disable_blog`. (h/t @szepeviktor, PR #31)
- Add WP.org Badge to readme.md.  (h/t @szepeviktor, PR #32)
- Change the name of the CI workflow to be specific to deployment. (h/t @szepeviktor, PR #33)
- Some code tidying and inline documentation.

#### 0.4.7
- Using GitHub actions publish on WP.org from github releases.
- Cleaned up the Reading settings, adding admin notices if front page is not set.
- Add check for Multisite to avoid network page redirects. Closes #17, props to @Mactory.
- Added Contributing and Code of Conduct documentation.
- Check that `is_singular` works prior to running redirects to avoid non-object errors in feeds.

#### 0.4.6
- Added check on disable feed functionality to confirm post type prior to disabling feed.

#### 0.4.5
- Remove the functionality hiding the Settings > Writing admin page, allow this option to be re-enabled via the older filter. This page used to be entirely related to posts, but is also used to select the editor type (Gutenberg vs Classic).
- Correct misspelled dwpb_redirect_options_tools filter.

#### 0.4.4
- Hide the Settings > Writing menu item, which shows up with Disable Comments enabled everywhere. Thanks to @dater for identifying.

#### 0.4.3
- Fix fatal error conflict with WooCommerce versions older than 2.6.3 (props to @Mahjouba91 for the heads up), no returns an array of comments in the filter for those older WooCommerce versions.
- Add de/activation hooks to clear comment caches
- Cleanup comment count functions.

#### 0.4.2
- Disable the REST API for 'post' post type. Props to @shawnhooper.

#### 0.4.1
- Fix unintended redirect for custom admin pages under tools.php. Props to @greatislander for the catch.

#### 0.4.0
- Refactor code to match WP Plugin Boilerplate structure, including:
 - Move hooks and filters into loader class.
 - Separate Admin and Public hooks.
 - Add support for internationalization.
- Expanded inline documentation.
- Add another failsafe for potential redirect loops.
- Disable comments feed only if 'post' is only type shown.
- Hide/redirect discussion options page if 'post' is the only post type supporting it (typically supported by pages).
- Filter comment counts to remove comments associated with 'post' post type.
- Add $is_comment_feed variable to disable feed filters.
- Remove feed link from front end (for WP >= 4.4.0), remove comment feed link if 'post' is the only post type supporting comments.
- Hide options in Reading Settings page related to posts (shows front page and search engine options only now), previously it was hiding everything on this page (bugfix!).
- Fix show_on_front pages - now, if it's set to 'posts' it will set the blog page to value 0 (not a valid option) and set the front page to value 1.
- Add uninstall.php to remove plugin version saved in options table on uninstall.

#### 0.3.3
- Weird issue with svn, same as version 0.3.2.

#### 0.3.2
- Fix potential loop issue with `home_url` in redirection function.
- Fix custom taxonomy save redirect (used to redirect to dashboard, now it saves correctly).

#### 0.3.1
- Add/update readme.txt.

#### 0.3.0
- Singleton Class.
- Clean up documentation.
- Add filters.
 	
#### 0.2.0
More improvements:

- Remove 'post' post type from most queries.
- Change disable feed functionality to a redirect instead of die message.
- Refine admin redirects.
- Add redirects for Single Posts, Post Archives, Tag & Category archives to home page (the latter two are only redirected if 'post' post type is the only post type associated with it).
- Filter out the 'post' post type from 'Comments' admin page.
- Remove Post from '+New' admin bar menu.
- Hide number of posts and comment count on Activity dashboard widget.
- Remove 'Writing' Options from Settings Menu.
- Redirect 'Writing' Options to General Options.
- Hide 'Posts' options on 'Menus' admin page.
- Remove Post Related Widgets.
- Disable "Press This" functionality.
- Disable "Post By Email" functionality.
- Force Reading Settings: show_on_front, pages_for_posts, and posts_on_front, if they are not already set.
- Hide other post-related reading options, except Search Engine Visibility.

#### 0.1.0
Initial beta release.
