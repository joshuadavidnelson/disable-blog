# Plugin Integrations

Disable Blog changes its own behavior when certain other plugins are active. Two
integrations ship with the plugin: Disable Comments and WooCommerce. Both are
automatic. There is nothing to configure.

### Disable Comments

If [Disable Comments](https://wordpress.org/plugins/disable-comments/) is active,
Disable Blog forces its own `dwpb_post_types_supporting_comments` filter to always
return `false`, as if no post type supported comments at all.

That has two separate effects, because Disable Blog checks comment support two
different ways:

* The hooks that adjust comment behavior (admin comment counts, `wp_count_comments`,
  filtering comments out of the Comments list, and similar) only register when
  [`dwpb_post_types_with_feature( 'comments' )`](functions.md) returns something. With
  the filter forced to `false`, none of them register, so Disable Blog stops adjusting
  comment behavior and leaves that to Disable Comments.
* The screen-removal checks test the opposite condition,
  `! dwpb_post_types_with_feature( 'comments' )`, so forcing the filter to `false`
  makes that condition true instead. The Comments menu item and Settings > Discussion
  are both removed, and visiting either page directly redirects to the dashboard, the
  same as on a site where nothing but `post` supports comments.

In short, with Disable Comments active, Disable Blog stops adjusting comment behavior
itself, and its own Comments admin screens still disappear, exactly as they would with
no comment-supporting post types at all.

### WooCommerce

If WooCommerce is active and at least one post type other than `post` still supports
comments, Disable Blog adds a compatibility filter on `wp_count_comments`.

The filter only changes anything on WooCommerce version 2.6.2 or earlier, meaning any
release before 2.6.3. Those versions returned an object instead of an array from the
site-wide comment count (`wp_count_comments( 0 )`), which broke code expecting an
array. Disable Blog casts the count back to an array when it detects one of those old
versions.

{% hint style="info" %}
Current WooCommerce releases are unaffected. This filter only matters on sites still
running WooCommerce 2.6.2 or earlier.
{% endhint %}

### Writing your own integration

`Disable_Blog_Integrations` is a small class of plugin-detection helpers, the same
class behind both integrations above. Its `is_plugin_active()` method wraps
WordPress's own `is_plugin_active()` function, loading `wp-admin/includes/plugin.php`
first if it is not already available, since that function does not normally exist
outside `wp-admin`. `is_disable_comments_active()` and `is_woocommerce_active()` both
build on that one method, each checking a known plugin path and, as a fallback, a class
or function the other plugin defines.

Everything is wired together in `plugin_integrations()` on the main plugin class, which
runs once when Disable Blog boots. There is no formal registration API for third-party
integrations. The supported way to make Disable Blog aware of another plugin is the
same filters covered in
[Hooks, Filters, and Actions](hooks-filters-and-actions.md), combined with your own
`is_plugin_active()` check, the same approach used above for Disable Comments and
WooCommerce.

For example, to make Disable Blog back off its own comment handling whenever some other
comment-management plugin is active, add this to your theme's `functions.php` file or
as an [MU plugin](https://wordpress.org/documentation/article/must-use-plugins/):

{% code overflow="wrap" %}
```php
function my_dwpb_defer_to_comments_plugin() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( is_plugin_active( 'some-other-comments-plugin/plugin.php' ) ) {
        add_filter( 'dwpb_post_types_supporting_comments', '__return_false' );
    }
}
// Priority 5 so this runs before Disable Blog's own setup, which hooks
// 'plugins_loaded' at the default priority of 10.
add_action( 'plugins_loaded', 'my_dwpb_defer_to_comments_plugin', 5 );
```
{% endcode %}

This is the same pattern Disable Blog uses for Disable Comments, aimed at whichever
plugin you need to detect. Swap in its real plugin path, and swap the filter for
whichever behavior in [Hooks, Filters, and Actions](hooks-filters-and-actions.md) you
want to switch off.
