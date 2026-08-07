# Front-End Changes

Disable Blog changes what visitors can reach on the front end of your site. This page is
the full reference for what changes: redirects, feeds, archives, sitemaps, and the REST
API and XML-RPC.

{% hint style="info" %}
The redirects on this page only run once your site has a static homepage set. See
[Setting Up Your Site](setting-up.md).
{% endhint %}

### Redirects

Disable Blog redirects six kinds of front-end pages to your homepage. Sitemap requests
are excluded from this check; see Sitemaps, below.

| Page | Redirects to | When |
| --- | --- | --- |
| Single posts | Homepage | Always |
| Tag archives | Homepage | Only when no other post type uses the `post_tag` taxonomy |
| Category archives | Homepage | Only when no other post type uses the `category` taxonomy |
| Posts page | Homepage | Only when a Posts page is set in Reading Settings |
| Date archives | Homepage | Always |
| Author archives | Homepage | Only when the `dwpb_disable_author_archives` filter is set to `true`. Off by default |

Redirects use a 301 (Moved Permanently) status by default. Change it with the
`dwpb_redirect_status_code` filter; invalid values fall back to 301.

Query strings are dropped from the destination URL by default. Allow specific ones
through with the `dwpb_pass_query_string_on_redirect` and `dwpb_allowed_query_vars`
filters.

Turn off all front-end redirects at once with `dwpb_redirect_front_end`, or override the
destination URL for all of them with `dwpb_front_end_redirect_url`. Each row in the table
above also has its own filter, named for the page it redirects, for example
`dwpb_redirect_post` for single posts.

### Feeds

Disable Blog turns off the site's main post feed, in every format WordPress serves: RSS,
RSS2, RDF, and Atom. This includes requests made with a query string, like
`/?feed=rss2`, not just pretty-permalink URLs like `/feed/`. Feeds for other post types
are not affected.

Visiting the feed redirects to your homepage by default, using the same redirect
settings as the rest of the site. Show a plain message instead of redirecting with the
`dwpb_feed_message` filter, and customize that message with `dwpb_feed_die_message`. The
redirect destination is filterable on its own with `dwpb_redirect_feeds`.

Comment feeds keep working as long as some post type other than `post` supports
comments, which is true by default since Pages support comments too. If `post` is the
only post type that supports comments, its comment feed is disabled the same way as the
main feed. Turn feed disabling off entirely with `dwpb_disable_feed`.

Feed-related links are also removed from your site's `<head>`: the general feed links,
the extra feed links WordPress adds on archives and single entries, the RSD link, and
the Windows Live Writer manifest link.

### Archives

On a tag or category archive that stays public, because another post type also uses
that taxonomy, the archive only shows posts from those other post types. Ordinary posts
don't appear there. Adjust which post types show with the `dwpb_tag_post_types` and
`dwpb_category_post_types` filters.

Author archives are not changed this way by default; an author's archive page lists
their posts exactly as it always has. Add other post types to an author's archive with
the `dwpb_author_archive_post_types` filter. That filter replaces the list of post types
entirely, so include `post` yourself if you still want ordinary posts to appear alongside
the others.

### Sitemaps

Disable Blog updates WordPress's built-in XML sitemaps to match the rest of the site.

* The post sitemap no longer lists posts.
* The category and tag sitemaps are removed, unless another post type uses that
  taxonomy.
* The users (author) sitemap is removed by default. It only reappears if you add post
  types to author archives with the `dwpb_author_archive_post_types` filter. Override
  this directly with `dwpb_disable_user_sitemap`.

Requesting a sitemap the plugin has removed, such as `/wp-sitemap-users-1.xml`, returns
a 404 instead of a live page. Turn this off with `dwpb_disable_removed_sitemaps`.

### REST API and XML-RPC

The `post` type is always removed from the REST API. Its `category` and `post_tag`
taxonomies are removed too, but only when no other post type uses them, so a custom post
type sharing either taxonomy keeps full REST support for it.

XML-RPC loses its posting methods: the ones used to create, edit, delete, and retrieve
posts, plus the equivalent methods from the older Blogger and MovableType/MetaWeblog
APIs, and pingback support. If no other post type uses categories or tags, the methods
for managing those are removed too. Adjust the list with the
`dwpb_disabled_xmlrpc_methods` filter, or return `false` from it to leave every method in
place.

The `X-Pingback` HTTP header WordPress normally sends is also removed. Turn this off
with `dwpb_remove_pingback_header`.

***

Every behavior on this page is controlled by a filter. See
[Hooks, Filters, and Actions](../extending-the-plugin/hooks-filters-and-actions.md) for
the full reference, including default values and examples.
