# Hooks, Filters, and Actions

Disable Blog has no settings page. Every behavior it controls, from redirects to
dashboard widgets, is adjustable through a filter or action instead. This page is the
complete reference: every filter and action the plugin fires, with its parameters,
defaults, and a working example.

{% hint style="info" %}
Add any of the examples below to your theme's `functions.php` file or as an [MU plugin](https://wordpress.org/documentation/article/must-use-plugins/).
{% endhint %}

### Index

**Redirects**

[`dwpb_redirect_front_end`](#dwpb_redirect_front_end), [`dwpb_front_end_redirect_url`](#dwpb_front_end_redirect_url), [`dwpb_redirect_admin`](#dwpb_redirect_admin), [`dwpb_admin_redirect_url`](#dwpb_admin_redirect_url), [`dwpb_redirect_status_code`](#dwpb_redirect_status_code), [`dwpb_pass_query_string_on_redirect`](#dwpb_pass_query_string_on_redirect), [`dwpb_allowed_query_vars`](#dwpb_allowed_query_vars), [front-end page redirects](#front-end-page-redirects) (6 filters), [admin screen redirects](#admin-screen-redirects) (9 filters).

**Front-End**

[`dwpb_disable_feed`](#dwpb_disable_feed), [`dwpb_redirect_feeds`](#dwpb_redirect_feeds), [`dwpb_feed_message`](#dwpb_feed_message), [`dwpb_feed_die_message`](#dwpb_feed_die_message), [`dwpb_disabled_xmlrpc_methods`](#dwpb_disabled_xmlrpc_methods), [`dwpb_remove_pingback_header`](#dwpb_remove_pingback_header), [`dwpb_disable_user_sitemap`](#dwpb_disable_user_sitemap), [`dwpb_disable_removed_sitemaps`](#dwpb_disable_removed_sitemaps).

**Admin**

[`dwpb_menu_pages_to_remove`](#dwpb_menu_pages_to_remove), [`dwpb_menu_subpages_to_remove`](#dwpb_menu_subpages_to_remove), [`dwpb_remove_options_writing`](#dwpb_remove_options_writing), [`dwpb_unregister_widgets`](#dwpb_unregister_widgets), [`dwpb_disable_{$metabox_id}`](#dwpb_disable_metabox_id), [`dwpb_admin_user_post_types`](#dwpb_admin_user_post_types), [`dwpb_disable_user_post_column`](#dwpb_disable_user_post_column), [`dwpb_create_user_{$post_type}_column`](#dwpb_create_user_post_type_column).

**Post Types & Taxonomies**

[`dwpb_post_types_supporting_{$feature}`](#dwpb_post_types_supporting_feature), [`dwpb_taxonomy_support`](#dwpb_taxonomy_support), [`dwpb_tag_post_types`](#dwpb_tag_post_types), [`dwpb_category_post_types`](#dwpb_category_post_types), [`dwpb_disable_author_archives`](#dwpb_disable_author_archives), [`dwpb_author_archive_post_types`](#dwpb_author_archive_post_types).

**Actions**

[`dwpb_init`](#dwpb_init).

**Deprecated**

[`dwpb_redirect_admin_options_tools`](#deprecated), [`dpwb_disable_user_post_column`](#deprecated), [`dpwb_create_user_{$post_type}_column`](#deprecated).

### Redirects

Disable Blog redirects front-end pages and admin screens that no longer apply once the
blog is disabled. These filters control where those redirects go, whether they happen at
all, and how the destination URL is built.

#### **`dwpb_redirect_front_end`**

Toggles all front-end redirects at once. Return `false` to stop the plugin from
redirecting single posts, archives, and the other pages listed under
[Front-end page redirects](#front-end-page-redirects).

**Since:** 0.2.0

**Parameters:**

* `bool $bool` - True to redirect, false to leave the page alone (default: `true`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
// Turn off every front-end redirect.
add_filter( 'dwpb_redirect_front_end', '__return_false' );
```
{% endcode %}

#### **`dwpb_front_end_redirect_url`**

Overrides the destination URL for every front-end redirect at once. It fires after the
page-specific filter (for example `dwpb_redirect_post`) has already run, and receives
that filter's result, so whatever it returns is where the plugin actually sends the
visitor.

**Since:** 0.5.0

**Parameters:**

* `string $redirect_url` - The URL to redirect to (default: the result of the page-specific redirect filter, which itself defaults to the site's front page URL)

**Returns:** `string`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_front_end_redirect_url', function( $redirect_url ) {
    return home_url( '/site-notice/' );
} );
```
{% endcode %}

#### Front-end page redirects

Six filters share one pattern: each is named `dwpb_redirect_<page>`, takes the site's
front page URL as its only parameter, and returns the URL to redirect to. Only the first
matching page below fires on a given request.

| Filter | Page | Redirects when |
| --- | --- | --- |
| `dwpb_redirect_post` | Single posts | Always |
| `dwpb_redirect_post_tag_archive` | Tag archives | No other post type uses the `post_tag` taxonomy |
| `dwpb_redirect_category_archive` | Category archives | No other post type uses the `category` taxonomy |
| `dwpb_redirect_blog_page` | Posts page | A Posts page is set in Reading Settings |
| `dwpb_redirect_date_archive` | Date archives | Always |
| `dwpb_redirect_author_archive` | Author archives | The `dwpb_disable_author_archives` filter is set to `true`. Off by default |

**Since:** 0.4.0

**Parameters:**

* `string $url` - The URL to redirect to (default: the site's front page URL)

**Returns:** `string`

**Example:**

{% code overflow="wrap" %}
```php
// Send single posts to a custom page instead of the homepage.
add_filter( 'dwpb_redirect_post', function( $url ) {
    return home_url( '/no-more-blog/' );
} );
```
{% endcode %}

#### **`dwpb_redirect_admin`**

Toggles all admin redirects at once. Return `false` to stop the plugin from redirecting
`post.php`, `edit.php`, and the other screens listed under
[Admin screen redirects](#admin-screen-redirects).

**Since:** 0.4.0

**Parameters:**

* `bool $bool` - True to redirect, false to leave the screen alone (default: `true`)
* `string $redirect_url` - The URL the plugin is about to redirect to

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
// Turn off every admin redirect.
add_filter( 'dwpb_redirect_admin', '__return_false' );
```
{% endcode %}

#### **`dwpb_admin_redirect_url`**

Overrides the destination URL for every admin redirect at once. It runs on every admin
page load, not only the nine screens below, receiving whatever the page-specific filter
produced, or `false` if none of them matched. A callback that unconditionally returns a
URL redirects every admin screen, so check `$redirect_url` before replacing it.

**Since:** 0.5.0

**Parameters:**

* `string|false $redirect_url` - The URL to redirect to, or `false` if no admin redirect matched this screen

**Returns:** `string`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_admin_redirect_url', function( $redirect_url ) {
    if ( ! $redirect_url ) {
        return $redirect_url;
    }
    return admin_url( 'options-general.php' );
} );
```
{% endcode %}

#### Admin screen redirects

Nine filters share one pattern: each is named `dwpb_redirect_admin_<screen>`, takes a URL
as its only parameter, and returns the URL to redirect to. These do not run on Network
Admin screens.

| Filter | Screen | Redirects when |
| --- | --- | --- |
| `dwpb_redirect_admin_post` | `post.php` (edit a single post) | The post being edited is the `post` type |
| `dwpb_redirect_admin_edit` | `edit.php` (Posts list) | No post type is specified, or it is explicitly `post` |
| `dwpb_redirect_admin_post_new` | `post-new.php` (Add New Post) | No post type is specified, or it is explicitly `post` |
| `dwpb_redirect_admin_term` | `term.php` (edit a single term) | The taxonomy is not used by another post type |
| `dwpb_redirect_admin_edit_tags` | `edit-tags.php` (Categories/Tags list) | The taxonomy is not used by another post type |
| `dwpb_redirect_admin_edit_comments` | `edit-comments.php` (Comments) | No post type other than `post` supports comments |
| `dwpb_redirect_admin_options_discussion` | `options-discussion.php` (Settings > Discussion) | No post type other than `post` supports comments |
| `dwpb_redirect_admin_options_writing` | `options-writing.php` (Settings > Writing) | The `dwpb_remove_options_writing` filter is set to `true`. Off by default |
| `dwpb_redirect_admin_tools` | `tools.php` (Tools > Available Tools) | There is no `page` parameter in the URL |

**Since:** 0.4.0

**Parameters:**

* `string $url` - The URL to redirect to. Defaults to the dashboard, except `dwpb_redirect_admin_edit` and `dwpb_redirect_admin_post_new`, which default to the matching `page` screen, and `dwpb_redirect_admin_options_writing`, which defaults to Settings > General

**Returns:** `string`

**Example:**

{% code overflow="wrap" %}
```php
// Send the Tools screen redirect to the Plugins screen instead of the dashboard.
add_filter( 'dwpb_redirect_admin_tools', function( $url ) {
    return admin_url( 'plugins.php' );
} );
```
{% endcode %}

{% hint style="info" %}
`dwpb_redirect_admin_tools` also fires the older `dwpb_redirect_admin_options_tools`
name, for compatibility. See [Deprecated](#deprecated).
{% endhint %}

#### **`dwpb_redirect_status_code`**

Filters the HTTP status code used for every redirect the plugin performs, front-end and
admin alike.

**Since:** 0.5.0

**Parameters:**

* `int $status_code` - The status code to send (default: `301`)
* `string $current_url` - The URL being redirected from
* `string $redirect_url` - The URL being redirected to

**Returns:** `int`. Must resolve to a whole number between 300 and 399; anything else falls back to 301.

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_redirect_status_code', function( $status_code, $current_url, $redirect_url ) {
    return 302;
}, 10, 3 );
```
{% endcode %}

#### **`dwpb_pass_query_string_on_redirect`**

Toggles whether the current request's query string is carried over to the redirect
destination. Works together with [`dwpb_allowed_query_vars`](#dwpb_allowed_query_vars),
which decides which keys are actually kept.

**Since:** 0.5.0

**Parameters:**

* `bool $bool` - True to carry the query string over (default: `false`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_pass_query_string_on_redirect', '__return_true' );
```
{% endcode %}

{% hint style="warning" %}
Both filters are needed together. This one only switches on the mechanism that looks at
the query string, it does not decide what gets kept. With `dwpb_allowed_query_vars` left
at its empty default, nothing is carried over even when this returns `true`.
{% endhint %}

#### **`dwpb_allowed_query_vars`**

Filters the list of query string keys allowed to pass through to a redirect
destination. Only takes effect when
[`dwpb_pass_query_string_on_redirect`](#dwpb_pass_query_string_on_redirect) also returns
`true`.

**Since:** 0.5.0

**Parameters:**

* `array $allowed_query_vars` - The allowed query variable keys (default: `array()`)

**Returns:** `array`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_allowed_query_vars', function( $allowed_query_vars ) {
    return array( 'utm_source', 'utm_medium', 'utm_campaign' );
} );
```
{% endcode %}

### Front-End

These filters control what changes for site visitors: feeds, the X-Pingback header,
XML-RPC methods, and sitemap entries.

#### **`dwpb_disable_feed`**

Toggles whether a given feed request is disabled. Runs for the main post feed and, when
no post type other than `post` supports comments, the comment feed too.

**Since:** 0.4.0

**Parameters:**

* `bool $bool` - True to disable the feed (default: `true`)
* `WP_Post $post` - The global post object
* `bool $is_comment_feed` - True if this is a comment feed rather than the main feed

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
// Keep the main post feed available.
add_filter( 'dwpb_disable_feed', '__return_false' );
```
{% endcode %}

#### **`dwpb_redirect_feeds`**

Filters the URL a disabled feed request redirects to.

**Since:** 0.4.0

**Parameters:**

* `string $url` - The redirect URL (default: the site's homepage, from `home_url()`)
* `WP_Post $post` - The global post object
* `bool $is_comment_feed` - True if this is a comment feed rather than the main feed

**Returns:** `string`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_redirect_feeds', function( $url, $post, $is_comment_feed ) {
    return home_url( '/subscribe/' );
}, 10, 3 );
```
{% endcode %}

#### **`dwpb_feed_message`**

Toggles a plain die message in place of the feed redirect.

**Since:** 0.4.0

**Parameters:**

* `bool $bool` - True to show a message instead of redirecting (default: `false`)
* `WP_Post $post` - The global post object
* `bool $is_comment_feed` - True if this is a comment feed rather than the main feed

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_feed_message', '__return_true' );
```
{% endcode %}

#### **`dwpb_feed_die_message`**

Filters the message shown when
[`dwpb_feed_message`](#dwpb_feed_message) is set to `true`. Only `<a>` tags survive in
the returned string; everything else is stripped.

**Since:** 0.4.0

**Parameters:**

* `string $message` - The message text (default: "No feed available, please visit our homepage:" followed by a link to the feed redirect URL)

**Returns:** `string`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_feed_die_message', function( $message ) {
    return 'This site does not publish a feed.';
} );
```
{% endcode %}

#### **`dwpb_disabled_xmlrpc_methods`**

Filters the list of XML-RPC methods the plugin removes: the core, Blogger, and
MovableType/MetaWeblog posting methods, pingback support, and the category and tag
management methods, the last two only when no other post type uses that taxonomy.

**Since:** 0.5.0

**Parameters:**

* `array $methods_to_remove` - The XML-RPC method names to disable

**Returns:** `array|bool`. Return `false` to leave every method in place.

**Example:**

{% code overflow="wrap" %}
```php
// Keep every XML-RPC method available.
add_filter( 'dwpb_disabled_xmlrpc_methods', '__return_false' );
```
{% endcode %}

{% hint style="info" %}
On WordPress 7.1 and later, core removes `pingback.ping` itself outside of a production
environment. That method stays gone there no matter what this filter returns.
{% endhint %}

#### **`dwpb_remove_pingback_header`**

Toggles the `X-Pingback` HTTP header WordPress normally sends.

**Since:** 0.4.0

**Parameters:**

* `bool $bool` - True to remove the header (default: `true`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
// Keep the X-Pingback header.
add_filter( 'dwpb_remove_pingback_header', '__return_false' );
```
{% endcode %}

#### **`dwpb_disable_user_sitemap`**

Toggles the built-in users (author) sitemap.

**Since:** 0.5.0

**Parameters:**

* `bool $bool` - True to disable the sitemap (default: `true`, unless post types have been added to author archives with `dwpb_author_archive_post_types` and author archives are not otherwise disabled, in which case it defaults to `false`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
// Always remove the user/author sitemap, regardless of author archive settings.
add_filter( 'dwpb_disable_user_sitemap', '__return_true' );
```
{% endcode %}

#### **`dwpb_disable_removed_sitemaps`**

Toggles whether a request for a sitemap sub-file the plugin has removed entirely, such as
`/wp-sitemap-users-1.xml` once the users provider is gone, returns a 404 instead of
falling through to a normal page.

**Since:** 0.5.6

**Parameters:**

* `bool $bool` - True to 404 the request (default: `true`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
// Let removed sitemap sub-files fall through instead of returning a 404.
add_filter( 'dwpb_disable_removed_sitemaps', '__return_false' );
```
{% endcode %}

### Admin

These filters control what changes in wp-admin: menus, dashboard widgets, sidebar
widgets, and the Users table.

#### **`dwpb_menu_pages_to_remove`**

Filters the top-level admin pages the plugin removes.

**Since:** 0.4.0

**Parameters:**

* `array $remove_pages` - The page slugs to remove (default: `array( 'edit.php' )`, plus `'edit-comments.php'` when no post type other than `post` supports comments)

**Returns:** `array`

**Example:**

{% code overflow="wrap" %}
```php
// Keep the Posts menu visible.
add_filter( 'dwpb_menu_pages_to_remove', function( $remove_pages ) {
    return array_diff( $remove_pages, array( 'edit.php' ) );
} );
```
{% endcode %}

#### **`dwpb_menu_subpages_to_remove`**

Filters the admin submenu pages the plugin removes. Each array key is a top-level page
slug, its value an array of submenu page slugs to remove from under it.

**Since:** 0.4.0

**Parameters:**

* `array $remove_subpages` - The page => subpages map (default: `array( 'tools.php' => array( 'tools.php' ), 'options-general.php' => array() )`, with `'options-writing.php'` added when `dwpb_remove_options_writing` is `true`, and `'options-discussion.php'` added when no post type other than `post` supports comments)

**Returns:** `array`

**Example:**

{% code overflow="wrap" %}
```php
// Keep the Tools submenu page.
add_filter( 'dwpb_menu_subpages_to_remove', function( $remove_subpages ) {
    unset( $remove_subpages['tools.php'] );
    return $remove_subpages;
} );
```
{% endcode %}

#### **`dwpb_remove_options_writing`**

Toggles the Settings > Writing screen. Off by default because other plugins often add
their own fields to that screen. Setting this to `true` removes it from the admin menu
and redirects any direct visit to Settings > General.

**Since:** 0.4.5

**Parameters:**

* `bool $bool` - True to remove the screen (default: `false`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_remove_options_writing', '__return_true' );
```
{% endcode %}

#### **`dwpb_unregister_widgets`**

Filters whether each blog related legacy widget gets unregistered. Runs once per widget:
Recent Comments, Tag Cloud, Categories, Archives, Calendar, Links, Recent Posts, and RSS.

**Since:** 0.4.0

**Parameters:**

* `bool $bool` - True to unregister the widget (default: `true`)
* `string $widget` - The widget class name currently being processed, for example `WP_Widget_Recent_Comments`

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_unregister_widgets', function( $bool, $widget ) {
    if ( 'WP_Widget_Recent_Comments' === $widget ) {
        return false;
    }
    return $bool;
}, 10, 2 );
```
{% endcode %}

#### **`dwpb_disable_{$metabox_id}`**

Toggles a single dashboard widget. `{$metabox_id}` is either `dashboard_quick_press` or
`dashboard_activity`, the only two dashboard widgets the plugin removes in 0.5.6.

**Since:** 0.4.1

**Parameters:**

* `bool $bool` - True to remove the widget (default: `true`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
// Keep the Quick Press dashboard widget.
add_filter( 'dwpb_disable_dashboard_quick_press', '__return_false' );
```
{% endcode %}

#### **`dwpb_admin_user_post_types`**

Filters the post types that get their own column on the Users screen.

**Since:** 0.5.0

**Parameters:**

* `array $post_types` - The post type slugs to show (default: `array( 'page' )`, plus any post types added to author archives with `dwpb_author_archive_post_types`)

**Returns:** `array`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_admin_user_post_types', function( $post_types ) {
    $post_types[] = 'event';
    return $post_types;
} );
```
{% endcode %}

#### **`dwpb_disable_user_post_column`**

Toggles the Posts column that the plugin removes from the Users screen.

**Since:** 0.5.0

**Parameters:**

* `bool $bool` - True to remove the column (default: `true`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
// Keep the Posts column on the Users screen.
add_filter( 'dwpb_disable_user_post_column', '__return_false' );
```
{% endcode %}

{% hint style="info" %}
**Changed in 0.5.6:** the correct filter name is `dwpb_disable_user_post_column`. The
misspelled `dpwb_disable_user_post_column` still works. See [Deprecated](#deprecated).
{% endhint %}

#### **`dwpb_create_user_{$post_type}_column`**

Toggles the column the plugin adds to the Users screen for each post type in
[`dwpb_admin_user_post_types`](#dwpb_admin_user_post_types), for example `page` or a
custom post type added to author archives.

**Since:** 0.5.0

**Parameters:**

* `bool $bool` - True to add the column (default: `true`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
// Hide the column added for the 'event' post type.
add_filter( 'dwpb_create_user_event_column', '__return_false' );
```
{% endcode %}

{% hint style="info" %}
**Changed in 0.5.6:** the correct filter name is `dwpb_create_user_{$post_type}_column`.
The misspelled `dpwb_create_user_{$post_type}_column` still works. See
[Deprecated](#deprecated).
{% endhint %}

### Post Types & Taxonomies

These filters control how the plugin detects which post types, other than `post`,
support a given feature or taxonomy. That detection decides what stays enabled
throughout the plugin.

#### **`dwpb_post_types_supporting_{$feature}`**

Filters the list of post types, other than `post`, that support a given `$feature`. The
plugin uses this internally, for example `dwpb_post_types_supporting_comments`, to decide
whether related admin screens, menus, and widgets stay active.

**Since:** 0.4.0

**Parameters:**

* `array|bool $post_types_with_feature` - The post types supporting `$feature` (default: the detected list, or `false` if none)
* `array $args` - The arguments passed to `get_post_types()` when building that list (default: `array()`)

**Returns:** `array|bool`

**Example:**

{% code overflow="wrap" %}
```php
// Treat comments as unsupported everywhere, regardless of actual post type support.
add_filter( 'dwpb_post_types_supporting_comments', '__return_false' );
```
{% endcode %}

#### **`dwpb_taxonomy_support`**

Short-circuits `dwpb_post_types_with_tax()`, the function the plugin uses internally to
find which post types, other than `post`, use a given taxonomy. Returning anything other
than `null` replaces the result entirely, skipping the plugin's own detection.

**Since:** 0.4.0

**Parameters:**

* `mixed $override` - Return non-null to override the result (default: `null`)
* `string|object $taxonomy` - The taxonomy slug or taxonomy object being checked
* `array $post_types` - Every registered post type, from `get_post_types( $args, $output )`. This is not filtered down to the post types that actually use the taxonomy
* `array $args` - The arguments passed to `dwpb_post_types_with_tax()` (default: `array()`)
* `string $output` - Either `'names'` or `'objects'` (default: `'names'`)

**Returns:** `mixed`. A non-null return replaces the function's result; `null` falls through to the plugin's normal detection.

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_taxonomy_support', function( $override, $taxonomy, $post_types, $args, $output ) {
    if ( 'category' === $taxonomy ) {
        return array( 'event' );
    }
    return $override;
}, 10, 5 );
```
{% endcode %}

#### **`dwpb_tag_post_types`**

Filters the post types shown on a tag archive that stays public because another post
type uses the `post_tag` taxonomy.

**Since:** 0.4.0

**Parameters:**

* `array $post_types` - The post types, other than `post`, that use `post_tag` (default: the result of `dwpb_post_types_with_tax( 'post_tag' )`)
* `WP_Query $query` - The main query being modified

**Returns:** `array`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_tag_post_types', function( $post_types, $query ) {
    $post_types[] = 'event';
    return $post_types;
}, 10, 2 );
```
{% endcode %}

#### **`dwpb_category_post_types`**

Filters the post types shown on a category archive that stays public because another
post type uses the `category` taxonomy.

**Since:** 0.4.0

**Parameters:**

* `array $post_types` - The post types, other than `post`, that use `category` (default: the result of `dwpb_post_types_with_tax( 'category' )`)
* `WP_Query $query` - The main query being modified

**Returns:** `array`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_category_post_types', function( $post_types, $query ) {
    $post_types[] = 'event';
    return $post_types;
}, 10, 2 );
```
{% endcode %}

#### **`dwpb_disable_author_archives`**

Toggles author archives entirely. Off by default because themes and plugins commonly
repurpose the author archive as a profile page.

**Since:** 0.5.0

**Parameters:**

* `bool $bool` - True to disable author archives (default: `false`)

**Returns:** `bool`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_disable_author_archives', '__return_true' );
```
{% endcode %}

{% hint style="info" %}
Turning this on also removes the users sitemap.
{% endhint %}

#### **`dwpb_author_archive_post_types`**

Adds post types to author archive pages. The returned array replaces the list entirely,
so include `post` yourself if you still want ordinary posts to keep appearing.

**Since:** 0.5.0

**Parameters:**

* `array|bool $post_types` - The post type slugs to show on author archives (default: `array()`)

**Returns:** `array|bool`

**Example:**

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_author_archive_post_types', function( $post_types ) {
    return array( 'post', 'event' );
} );
```
{% endcode %}

{% hint style="info" %}
This filter also decides whether the users sitemap appears. Left at its empty default,
the users sitemap stays off. Adding any post type here turns it back on, unless
`dwpb_disable_user_sitemap` overrides that.
{% endhint %}

### Actions

Disable Blog fires one action of its own.

#### **`dwpb_init`**

Fires at the very start of the plugin's main class constructor, before Disable Blog
checks for an upgrade, loads its dependencies, or registers any of its own hooks. Use it
to run code that needs to happen before anything else in the plugin.

**Since:** 0.4.0

**Parameters:** None

**Example:**

{% code overflow="wrap" %}
```php
add_action( 'dwpb_init', function() {
    // Runs before Disable Blog loads its own classes and hooks.
} );
```
{% endcode %}

### Deprecated

These filter names still work as of 0.5.6. Each one runs through
`apply_filters_deprecated()`, so hooking it also triggers a WordPress deprecation
notice.

The replacement filter runs first, and the deprecated one then receives its result. If
you have callbacks on both names, the deprecated name gets the final say. Move your code
to the replacement and drop the old callback.

| Deprecated | Use instead |
| --- | --- |
| `dwpb_redirect_admin_options_tools` | `dwpb_redirect_admin_tools` |
| `dpwb_disable_user_post_column` | `dwpb_disable_user_post_column` |
| `dpwb_create_user_{$post_type}_column` | `dwpb_create_user_{$post_type}_column` |

***

See [PHP Functions](functions.md) for the plugin's callable functions, and [Common
Customizations](common-customizations.md) for task-oriented recipes built on the filters
above.
