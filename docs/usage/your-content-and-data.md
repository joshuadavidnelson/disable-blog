# Your Content & Data

Activating Disable Blog will not delete anything on your site. This page explains exactly what happens to your content, and how to get it back.

### Nothing is deleted

Your posts, categories, tags, and comments all stay in the database exactly as they were before you activated the plugin. Disable Blog hides and redirects blog content, it never removes it.

### What "disabled" means in practice

While the plugin is active, that content is not reachable on the front end and not editable in wp-admin. A post's URL redirects visitors to your homepage, and the Posts admin screens redirect you away, most of them to the dashboard. The content is not gone, it is only out of reach.

### Getting your content back

Deactivate the plugin and everything returns immediately. Posts reappear on the front end, comments come back, and the Posts menu returns in wp-admin. Nothing needs to be restored or re-imported, because nothing was ever removed.

### If you actually want to delete the posts

If you want that content gone for good, the order matters:

1. Deactivate the plugin.
2. Delete the posts you no longer want. Deleting a post also deletes its comments.
3. Delete any categories and tags you no longer need.
4. Reactivate the plugin.

{% hint style="warning" %}
Do this in order. While Disable Blog is active, the screens you need to delete posts, categories, and tags are redirected, so deactivate first.
{% endhint %}

### What the plugin stores

Disable Blog stores two small options in the database: `dwpb_version` and `dwpb_previous_version`. They record the plugin version currently installed and the version you upgraded from, and the plugin uses them only to detect upgrades, not to store any of your content or settings.

Uninstalling the plugin removes both options. See [Deactivate & Uninstall](deactivate-and-uninstall.md) for what uninstalling does.
