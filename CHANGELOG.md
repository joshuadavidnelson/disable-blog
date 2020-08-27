### Changelog

##### 0.4.8.1
- Do'h! Forgot to update the version number in the main plugin file. Bump.

##### 0.4.8
- Fixed typo in variable name for current vs redirect url check. (h/t @chesio, PR #30)
- Update function names from template to `disable_blog`. (h/t @szepeviktor, PR #31)
- Add WP.org Badge to readme.md.  (h/t @szepeviktor, PR #32)
- Change the name of the CI workflow to be specific to deployment. (h/t @szepeviktor, PR #33)
- Some code tidying and inline documentation.

##### 0.4.7
- Using GitHub actions publish on WP.org from github releases.
- Cleaned up the Reading settings, adding admin notices if front page is not set.
- Add check for Multisite to avoid network page redirects. Closes #17, props to @Mactory.
- Added Contributing and Code of Conduct documentation.
- Check that `is_singular` works prior to running redirects to avoid non-object errors in feeds.

##### 0.4.6
- Added check on disable feed functionality to confirm post type prior to disabling feed.

##### 0.4.5
- Remove the functionality hiding the Settings > Writing admin page, allow this option to be re-enabled via the older filter. This page used to be entirely related to posts, but is also used to select the editor type (Gutenberg vs Classic).
- Correct misspelled dwpb_redirect_options_tools filter.

##### 0.4.4
- Hide the Settings > Writing menu item, which shows up with Disable Comments enabled everywhere. Thanks to @dater for identifying.

##### 0.4.3
- Fix fatal error conflict with WooCommerce versions older than 2.6.3 (props to @Mahjouba91 for the heads up), no returns an array of comments in the filter for those older WooCommerce versions.
- Add de/activation hooks to clear comment caches
- Cleanup comment count functions.

##### 0.4.2
- Disable the REST API for 'post' post type. Props to @shawnhooper.

##### 0.4.1
- Fix unintended redirect for custom admin pages under tools.php. Props to @greatislander for the catch.

##### 0.4.0
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

##### 0.3.3
- Weird issue with svn, same as version 0.3.2.

##### 0.3.2
- Fix potential loop issue with `home_url` in redirection function.
- Fix custom taxonomy save redirect (used to redirect to dashboard, now it saves correctly).

##### 0.3.1
- Add/update readme.txt.

##### 0.3.0
- Singleton Class.
- Clean up documentation.
- Add filters.
 	
##### 0.2.0
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

##### 0.1.0
Initial beta release.