# Frequently Asked Questions

<details>

<summary>Can I disable comments?</summary>

Disable Blog leaves comments turned on, because pages and other post types can use comments independently of posts. If you want to turn comments off across your whole site, use the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin. Disable Blog detects when Disable Comments is active and turns off its own comment handling, so the two plugins do not overlap.

</details>

<details>

<summary>I want to delete my posts and comments.</summary>

Deleting a post also deletes its comments, but you need to do this in a specific order since the plugin redirects the screens you would normally use to delete them. See [Your Content & Data](your-content-and-data.md) for the full steps.

</details>

<details>

<summary>How can I disable author archives?</summary>

Use the `dwpb_disable_author_archives` filter. It defaults to `false` because many themes and plugins use author archives as profile or account pages, so Disable Blog leaves them alone unless you turn them off yourself. Add this to your theme's `functions.php` file or as an [MU plugin](https://wordpress.org/documentation/article/must-use-plugins/):

```php
add_filter( 'dwpb_disable_author_archives', '__return_true' );
```

If some custom post types should still appear on author archives, list them with the `dwpb_author_archive_post_types` filter.

</details>

<details>

<summary>How can I change the plugin's behavior?</summary>

Disable Blog has no settings page. Every behavior, from redirects to feeds to sitemaps, is controlled through filters instead. See [Hooks, Filters, and Actions](../extending-the-plugin/hooks-filters-and-actions.md) for the full reference.

</details>

<details>

<summary>Why is my blog page still showing?</summary>

Disable Blog only redirects the front end when your site has a static front page set. If Settings > Reading > "Your homepage displays" is set to "Your latest posts" instead of a static page, none of the plugin's front-end redirects run, including the blog page. See [Setting Up Your Site](setting-up.md) for the full requirement.

</details>

<details>

<summary>Where do the redirects send people?</summary>

To your homepage. Single posts, date archives, the posts page, and any tag or category archive that nothing else uses all redirect there, using a 301. In wp-admin, the Posts list and Add New Post go to their Pages equivalents, and the rest of the redirected screens go to the dashboard. See [Front-End Changes](front-end-changes.md) and [Admin Changes](admin-changes.md) for the full tables.

</details>

<details>

<summary>Why does the Query Loop block default to pages?</summary>

Because posts are disabled, Disable Blog changes the Query Loop block's default post type from `post` to `page`. Without that, a newly inserted Query Loop would default to content the plugin has turned off and show nothing. You can still pick any other post type in the block settings.

</details>

<details>

<summary>I use categories or tags on a custom post type, will they still work?</summary>

Yes. Before Disable Blog hides or redirects a category or tag, it checks whether any post type other than `post` is registered to use that taxonomy. If a custom post type uses `category` or `post_tag`, the taxonomy stays fully functional there, including its admin screens, archives, and REST support. Override this detection with the `dwpb_taxonomy_support` filter by adding it to your theme's `functions.php` file or as an [MU plugin](https://wordpress.org/documentation/article/must-use-plugins/):

```php
add_filter( 'dwpb_taxonomy_support', function( $override, $taxonomy ) {
	if ( 'post_tag' === $taxonomy ) {
		return array( 'my_custom_post_type' );
	}
	return $override;
}, 10, 2 );
```

</details>

<details>

<summary>Does this work on multisite?</summary>

Yes. You can activate Disable Blog on a single site or across the whole network. Admin redirects are skipped on network admin screens, since those manage the network rather than a single site's content. Uninstalling clears the plugin's stored options from every site in the network, unless the network has 5,000 sites or more, in which case that per-site cleanup is skipped.

</details>

<details>

<summary>Where are the plugin's settings?</summary>

There is no settings screen. Disable Blog is configured entirely through code, using the filters listed in [Hooks, Filters, and Actions](../extending-the-plugin/hooks-filters-and-actions.md).

</details>

<details>

<summary>Does this delete anything?</summary>

No. Disable Blog only hides and redirects, it never deletes posts, comments, categories, or tags. See [Your Content & Data](your-content-and-data.md) for exactly what stays and what changes.

</details>
