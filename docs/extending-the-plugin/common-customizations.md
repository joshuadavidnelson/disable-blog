# Common Customizations

These are the most common adjustments people make to Disable Blog. For the complete list of filters and actions the plugin offers, see [Hooks, Filters, and Actions](hooks-filters-and-actions.md).

Add this to your theme's `functions.php` file or as an [MU plugin](https://wordpress.org/documentation/article/must-use-plugins/). Give each function below a unique name if you use more than one on the same site.

### Disable author archives

Author archives are left alone by default. Themes and plugins commonly repurpose the author archive as a profile page, so the plugin will not touch it unless you say so.

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_disable_author_archives', '__return_true' );
```
{% endcode %}

{% hint style="info" %}
Turning this on also removes the users sitemap.
{% endhint %}

### Keep categories or tags working for a custom post type

If a custom post type is genuinely registered with the `category` or `post_tag` taxonomy, this already works with no code. The plugin checks WordPress's own registration data to see what uses each taxonomy, and keeps that taxonomy's admin screens and archives active as soon as anything other than `post` is using it.

Use `dwpb_taxonomy_support` only to override that detection, for example if a post type uses the taxonomy in a way the plugin's check cannot see.

{% code overflow="wrap" %}
```php
function my_dwpb_taxonomy_support( $override, $taxonomy, $post_types, $args, $output ) {
    if ( 'post_tag' === $taxonomy ) {
        return array( 'book' );
    }

    return $override;
}
add_filter( 'dwpb_taxonomy_support', 'my_dwpb_taxonomy_support', 10, 5 );
```
{% endcode %}

| # | Parameter | What it is |
| --- | --- | --- |
| 1 | `$override` | `null` by default. Return anything else and that value short-circuits the result. |
| 2 | `$taxonomy` | The taxonomy slug or object being checked. |
| 3 | `$post_types` | Every registered post type matching `$args`, not just the ones already using the taxonomy. |
| 4 | `$args` | The arguments used to fetch that list of post types. |
| 5 | `$output` | `'names'` or `'objects'`. |

Returning `$override` unchanged, as the example does for any taxonomy other than `post_tag`, leaves the plugin's own detection in place.

### Change where redirects send people

`dwpb_front_end_redirect_url` changes the destination for every front-end redirect at once. It fires after any page-specific filter and receives that filter's result, so if you hook it, your return value is what the plugin actually redirects to, regardless of page type.

{% code overflow="wrap" %}
```php
function my_dwpb_front_end_redirect_url( $redirect_url ) {
    return home_url( '/welcome/' );
}
add_filter( 'dwpb_front_end_redirect_url', 'my_dwpb_front_end_redirect_url' );
```
{% endcode %}

To change just one kind of page instead, use its own filter. Single posts use `dwpb_redirect_post`, and tag archives, category archives, the blog page, date archives, and author archives each have a matching filter of their own. See [Hooks, Filters, and Actions](hooks-filters-and-actions.md) for the full list.

{% code overflow="wrap" %}
```php
function my_dwpb_redirect_post( $redirect_url ) {
    return home_url( '/blog-has-moved/' );
}
add_filter( 'dwpb_redirect_post', 'my_dwpb_redirect_post' );
```
{% endcode %}

### Change the redirect status code

{% code overflow="wrap" %}
```php
function my_dwpb_redirect_status_code( $status_code, $current_url, $redirect_url ) {
    return 302;
}
add_filter( 'dwpb_redirect_status_code', 'my_dwpb_redirect_status_code', 10, 3 );
```
{% endcode %}

{% hint style="warning" %}
The returned value must be a number from 300 to 399. Anything outside that range, including 200, is ignored and the plugin falls back to 301.
{% endhint %}

### Turn off redirects entirely

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_redirect_front_end', '__return_false' );
add_filter( 'dwpb_redirect_admin', '__return_false' );
```
{% endcode %}

{% hint style="info" %}
Redirects are one layer of the plugin, not the whole plugin. Turning both of these off only stops the forwarding. The `post` type itself is still non-public, excluded from search, left out of the REST API, and hidden from the admin menu, none of that depends on the redirect filters.
{% endhint %}

### Remove the Settings > Writing screen

This is off by default because other plugins commonly add their own fields to that screen.

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_remove_options_writing', '__return_true' );
```
{% endcode %}

Turning it on removes Settings > Writing from the admin menu and redirects any direct visit to it.

### Keep a feed alive

Add this to your theme's `functions.php` file or as an [MU plugin](https://wordpress.org/documentation/article/must-use-plugins/):

{% code overflow="wrap" %}
```php
function my_dwpb_keep_comment_feed_alive( $disable, $post, $is_comment_feed ) {
    if ( $is_comment_feed ) {
        return false;
    }

    return $disable;
}
add_filter( 'dwpb_disable_feed', 'my_dwpb_keep_comment_feed_alive', 10, 3 );
```
{% endcode %}

`$disable` arrives as `true`, meaning the plugin is about to shut the feed off. Return `false` to let that one through. `$post` is the global post object, and `$is_comment_feed` tells you whether the request is a comment feed rather than the main content feed. The example above keeps comment feeds working and leaves everything else disabled. Flip the condition to keep the main feed instead and disable comment feeds.

### Show a message instead of redirecting a feed

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_feed_message', '__return_true' );

function my_dwpb_feed_die_message( $message ) {
    return 'This site does not publish a feed. Visit <a href="' . esc_url( home_url() ) . '">the homepage</a> for the latest updates.';
}
add_filter( 'dwpb_feed_die_message', 'my_dwpb_feed_die_message' );
```
{% endcode %}

`dwpb_feed_message` defaults to false, so a disabled feed redirects to the homepage. Returning true stops the redirect and shows a message built with `dwpb_feed_die_message` instead. Only `<a>` tags survive in that message, everything else is stripped.

### Let query strings through a redirect

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_pass_query_string_on_redirect', '__return_true' );

function my_dwpb_allowed_query_vars( $allowed_query_vars ) {
    $allowed_query_vars[] = 'utm_source';
    $allowed_query_vars[] = 'utm_campaign';

    return $allowed_query_vars;
}
add_filter( 'dwpb_allowed_query_vars', 'my_dwpb_allowed_query_vars' );
```
{% endcode %}

{% hint style="warning" %}
Both filters are needed together. `dwpb_pass_query_string_on_redirect` only switches on the mechanism that looks at the current query string, it does not decide what gets kept. `dwpb_allowed_query_vars` decides that, and it defaults to an empty array. With no keys on that list, turning on `dwpb_pass_query_string_on_redirect` by itself carries nothing over, because there is nothing allowed to copy yet.
{% endhint %}

### Add custom post types to author archives

By default only `post` entries show on an author archive. Add this to your theme's `functions.php` file or as an [MU plugin](https://wordpress.org/documentation/article/must-use-plugins/):

{% code overflow="wrap" %}
```php
function my_dwpb_author_archive_post_types( $post_types ) {
    $post_types[] = 'post';
    $post_types[] = 'book';

    return $post_types;
}
add_filter( 'dwpb_author_archive_post_types', 'my_dwpb_author_archive_post_types' );
```
{% endcode %}

{% hint style="warning" %}
`$post_types` arrives empty, and whatever you return replaces the list used on author archives rather than adding to it. If you want ordinary posts to keep appearing, include `post` yourself as shown above. Leave it out and only `book` entries will show.
{% endhint %}

This filter also decides whether the users sitemap appears. Left untouched, the users sitemap is left out. Add any post type here and the users sitemap turns on as a side effect.

### Keep a dashboard widget

The plugin removes two dashboard widgets by default: Quick Press and Activity. Keep one by returning false from its own filter.

{% code overflow="wrap" %}
```php
add_filter( 'dwpb_disable_dashboard_quick_press', '__return_false' );
```
{% endcode %}

Use `dwpb_disable_dashboard_activity` for the Activity widget the same way.
