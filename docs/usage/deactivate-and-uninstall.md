# Deactivate & Uninstall

### Deactivate the plugin

Deactivating Disable Blog is safe. All blog functionality returns immediately: posts and comments on posts become visible again, the Latest Posts and Calendar blocks come back, and the plugin's redirects stop. Nothing is lost.

#### From the Plugins screen

1. From the WordPress admin screen, navigate to "Plugins."
2. Locate the Disable Blog plugin.
3. Click "Deactivate."

#### With WP-CLI

Deactivate the plugin with wp-cli:

{% code overflow="wrap" %}
```bash
wp plugin deactivate disable-blog
```
{% endcode %}

### Uninstall the plugin

#### Uninstall from the Plugins screen

1. Deactivate the plugin (see above).
2. From the WordPress admin screen, navigate to "Plugins."
3. Locate the Disable Blog plugin.
4. Click "Delete."

#### Uninstall with WP-CLI

Uninstall the plugin with wp-cli:

{% code overflow="wrap" %}
```bash
wp plugin uninstall disable-blog
```
{% endcode %}

This runs the same cleanup as deleting the plugin from the Plugins screen.

{% hint style="info" %}
**Changed in 0.5.7.** Uninstalling with WP-CLI now removes the plugin's options. Before, it left `dwpb_version` and `dwpb_previous_version` in the database.
{% endhint %}

#### Remove via Composer

Run this command:

{% code overflow="wrap" %}
```bash
composer remove wpackagist-plugin/disable-blog
```
{% endcode %}

### What uninstall deletes

Uninstalling removes the database options Disable Blog uses to track its own version, `dwpb_version` and `dwpb_previous_version`, and nothing else.

Your posts, comments, categories, and tags are untouched. They stay in the database exactly as they were. See [Your Content & Data](your-content-and-data.md) for the fuller explanation.

{% hint style="info" %}
On multisite, uninstalling clears those options from every site in the network, one at a time. Networks with 5,000 or more sites skip this per-site cleanup.
{% endhint %}
