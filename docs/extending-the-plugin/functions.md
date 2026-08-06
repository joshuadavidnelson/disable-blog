# PHP Functions

Disable Blog exposes two helper functions, both defined in `functions.php`. Each
answers the same underlying question: which post types, other than `post`, support a
given feature or taxonomy. The plugin uses them internally to decide what to redirect,
hide, or leave alone, and you can call them from your own code for the same purpose.

{% hint style="warning" %}
Always [check that a plugin function exists](https://www.php.net/manual/en/function.function-exists.php)
before calling it, in case the plugin is ever deactivated.
{% endhint %}

### `dwpb_post_types_with_feature( string $feature, array $args = array() ): array|bool`

Get the post types, other than `post`, that support a given feature, the same string
you would pass to `post_type_supports()`, for example `'comments'`, `'excerpt'`, or
`'thumbnail'`.

| Parameter | Default | Description |
| --- | --- | --- |
| `$feature` | none, required | The feature to check for. |
| `$args` | `array()` | Arguments passed to `get_post_types()` to narrow which post types get checked. |

Returns an array of post type slugs that support `$feature`. The `post` post type is
always excluded from the result, even though it supports most features by default.
Returns `false` if `$feature` is empty or not a string, and also if no post type other
than `post` supports it.

{% hint style="warning" %}
This returns `false`, not an empty array, when nothing matches. Check the result with
`if ( $post_types )` before looping over it.
{% endhint %}

Results are cached per `$feature`, in the `post-types-by-feature` cache group, under
the key `post-types-supporting-{$feature}`. The `$args` you pass are not part of the
cache key, so two calls with the same `$feature` but different `$args` share whichever
result was cached first. A feature that nothing supports is recomputed on every call
rather than served from cache, since a cached `false` is indistinguishable from an
empty cache.

Before returning, the result passes through
[`dwpb_post_types_supporting_{$feature}`](hooks-filters-and-actions.md), with
`{$feature}` replaced by the feature you passed in, for example
`dwpb_post_types_supporting_comments`. This runs on every call, cached or not. Disable
Blog's own Disable Comments integration uses this filter to turn off its comment
handling; see [Plugin Integrations](plugin-integrations.md). Full filter parameters are
on the [Hooks, Filters, and Actions](hooks-filters-and-actions.md) page.

Add this to your theme's `functions.php` file or as an [MU plugin](https://wordpress.org/documentation/article/must-use-plugins/):

{% code overflow="wrap" %}
```php
function my_dwpb_list_comment_post_types() {
    $post_types = dwpb_post_types_with_feature( 'comments' );

    if ( $post_types ) {
        foreach ( $post_types as $post_type ) {
            // Runs once for every non-'post' post type that still supports comments.
        }
    } else {
        // Nothing but 'post' supports comments, or nothing does.
    }
}
// Priority 20 so custom post types have already been registered.
add_action( 'init', 'my_dwpb_list_comment_post_types', 20 );
```
{% endcode %}

### `dwpb_post_types_with_tax( string|object $taxonomy, array $args = array(), string $output = 'names' ): array|bool`

Get the post types, other than `post`, that use a given taxonomy. Disable Blog calls
this to check whether any custom post type still uses the built-in `category` or
`post_tag` taxonomies, since if nothing but `post` does, it turns off the related
archives, redirects, and sitemap entries for that taxonomy.

| Parameter | Default | Description |
| --- | --- | --- |
| `$taxonomy` | none, required | A taxonomy slug or a taxonomy object. Only the slug is used either way. |
| `$args` | `array()` | Arguments passed to `get_post_types()` to narrow which post types get checked. |
| `$output` | `'names'` | `'names'` returns an array of post type slugs, `'objects'` returns post type objects. |

Returns an array of post type names or objects (matching `$output`) that use
`$taxonomy`. The `post` post type is always excluded from the result. Returns `false`
if `$taxonomy` is empty, and also if no post type other than `post` uses it.

{% hint style="warning" %}
Like `dwpb_post_types_with_feature()`, this returns `false`, not an empty array, when
nothing matches. Check the result before looping over it.
{% endhint %}

Before returning, the function runs
[`dwpb_taxonomy_support`](hooks-filters-and-actions.md). Return anything other than
`null` from this filter and that value becomes the function's return value instead,
short-circuiting the `false`-when-empty fallback described above. Full filter
parameters are on the [Hooks, Filters, and Actions](hooks-filters-and-actions.md) page.

Add this to your theme's `functions.php` file or as an [MU plugin](https://wordpress.org/documentation/article/must-use-plugins/):

{% code overflow="wrap" %}
```php
function my_dwpb_list_category_post_types() {
    $post_types = dwpb_post_types_with_tax( 'category' );

    if ( $post_types ) {
        // At least one custom post type still uses categories.
    } else {
        // Only 'post' used categories, so Disable Blog treats it as unused.
    }
}
// Priority 20 so custom post types have already been registered.
add_action( 'init', 'my_dwpb_list_category_post_types', 20 );
```
{% endcode %}
