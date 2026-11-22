# Admin Changes

Disable Blog also changes what you see in wp-admin. This page is the full reference for
what disappears: menus, redirects, comment handling, dashboard widgets, blocks,
settings screens, and a handful of smaller changes.

### Menus

The Posts menu is always removed. The Comments menu goes too, but only when nothing
other than `post` supports comments. Pages support comments by default in WordPress, so
in practice this menu stays unless something has changed that. See Comments, below.

Under Settings, the Discussion submenu is removed under that same comments condition.
The Writing submenu stays unless you set the `dwpb_remove_options_writing` filter to
`true`; it's `false` by default because other plugins often extend that screen. Tools
loses its default "Available Tools" landing page, though the Tools menu itself stays if
another plugin adds its own tools page.

Adjust the top-level removals with `dwpb_menu_pages_to_remove`, and the submenu
removals with `dwpb_menu_subpages_to_remove`.

### Admin redirects

Nine admin screens redirect elsewhere once they no longer apply. The Posts list and Add
New Post go to their Pages equivalents, Settings > Writing goes to Settings > General,
and the rest go to the dashboard. These redirects don't run on Network Admin screens.

| Screen | Redirects to | When |
| --- | --- | --- |
| `post.php` (edit a single post) | Dashboard | The post being edited is the `post` type |
| `edit.php` (Posts list) | `edit.php?post_type=page` (Pages list) | No post type is specified, or it's explicitly `post` |
| `post-new.php` (Add New Post) | `post-new.php?post_type=page` (Add New Page) | No post type is specified, or it's explicitly `post` |
| `edit-tags.php` (Categories/Tags list) | Dashboard | The taxonomy isn't used by another post type |
| `term.php` (edit a single term) | Dashboard | The taxonomy isn't used by another post type |
| `edit-comments.php` (Comments) | Dashboard | No post type other than `post` supports comments |
| `options-discussion.php` (Settings > Discussion) | Dashboard | No post type other than `post` supports comments |
| `options-writing.php` (Settings > Writing) | `options-general.php` (Settings > General) | The `dwpb_remove_options_writing` filter is set to `true`. Off by default |
| `tools.php` (Tools > Available Tools) | Dashboard | There's no `page` parameter in the URL, so a plugin's own tools page isn't caught |

Two filters apply across the whole list: `dwpb_redirect_admin` turns off admin
redirects entirely, and `dwpb_admin_redirect_url` overrides the destination for all of
them. Each row also has its own filter, named for the screen it redirects, for example
`dwpb_redirect_admin_tools` for the Tools screen.

{% hint style="info" %}
**Changed in 0.5.7.** The Tools screen (`tools.php`) and the single term edit screen
(`term.php`) now redirect as listed. Before, both stayed reachable.
{% endhint %}

### Comments

Comments stay on. Pages support comments by default in WordPress, and so does the
built-in Attachment type, so Disable Blog leaves the Comments screen, Settings >
Discussion, and the Comments item in the admin toolbar alone unless nothing other than
`post` actually supports comments.

If you want to turn comments off across your whole site, use the
[Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin rather than
looking for that option here. When Disable Comments is active, Disable Blog stops
adjusting comments itself and treats them as unsupported, which removes the Comments
screen, Settings > Discussion, and the toolbar item. The same thing happens if anything
else on your site removes comment support from every post type except `post`.

Comments left on posts are hidden for every user, administrators included, everywhere
in the admin:

* The Comments screen lists only comments on pages and other post types that support
  comments.
* Opening a comment on a post directly, at `comment.php`, shows WordPress's own
  "Invalid comment ID." message.
* `wp comment list` in WP-CLI leaves them out.

Comment counts leave out comments on posts everywhere too: the Activity widget, the
Comments screen's status links, the toolbar bubble, and the Comments menu badge.

{% hint style="info" %}
**Changed in 0.5.7.** Comments on posts used to be filtered from the Comments screen
only. They are now hidden from every comment list, count, and comment screen, on the
front end too. See [Front-End Changes](front-end-changes.md#comments).
{% endhint %}

Comments on posts are not deleted. Privacy exports and erasures under Tools still
include a person's comments on posts. To show comments on posts again, return `false`
from the `dwpb_filter_comment_queries` filter.

### Dashboard

Disable Blog removes two dashboard widgets: Quick Press and Activity. Each has its own
filter if you'd rather keep one, named `dwpb_disable_{$metabox_id}`, for example
`dwpb_disable_dashboard_quick_press`.

If you keep the Activity widget, its Recently Published and Publishing Soon lists leave
out posts. On WordPress 7.2, so does Published On This Day.

On the At a Glance widget, the post count and comment count are hidden.

### Blocks

The Latest Posts and Calendar blocks no longer appear in the block inserter. Both only
list posts. Keep either one with the `dwpb_unregister_blocks` filter. See
[Front-End Changes](front-end-changes.md#blocks).

### Settings screens

#### Reading

Once a static homepage is set, the settings that no longer apply are hidden: how many
posts to show per page, and the feed settings for item count and whether feeds show
full text or a summary.

#### Writing

The default post format field is always hidden. The default post category field is
hidden too, unless another post type uses categories.

#### Permalinks

The category base and tag base fields are hidden individually when nothing else uses
that taxonomy, and the whole "Optional" section that holds them disappears when neither
is in use.

#### Customizer

Once a static homepage is set, the Homepage Settings panel is simplified to match the
Reading screen. The choice between "Your latest posts" and "A static page," the posts
page selector, and the excerpt display settings are all hidden, leaving just the
homepage selector.

### Other

The "+ New" item in the admin toolbar no longer includes Post. Adding a new page or
other content type still works as usual.

The Users table swaps its Posts column for a Pages column, showing how many pages each
user has authored instead. If you've added post types to author archives with
`dwpb_author_archive_post_types`, those get their own columns too. Keep the original
Posts column with `dwpb_disable_user_post_column`, or turn off the Pages column, or any
other post type's column, with `dwpb_create_user_{$post_type}_column`.

On the Tags and Categories screens, when they stay available because another post type
uses that taxonomy, the post count shown next to each term reflects only that post
type. It no longer counts ordinary posts, which aren't viewable anyway. The count is
exact, however many items use the term.

{% hint style="info" %}
**Changed in 0.5.7.** Term counts used to stop at 100. They now show the true number.
{% endhint %}

Site Health's REST API availability check runs WordPress's own test against the `page`
type instead of `post`, so it doesn't report a false problem after `post` is removed
from the REST API. It shows WordPress's current wording and error details.

***

Most behaviors on this page are controlled by a filter. See
[Hooks, Filters, and Actions](../extending-the-plugin/hooks-filters-and-actions.md) for
the full reference, including default values and examples.
