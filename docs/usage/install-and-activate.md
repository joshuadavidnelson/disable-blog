# Install & Activate

### Install & Activate via WordPress Plugins Screen

1. From the WordPress admin screen, navigate to "Plugins."
2. Click on the "Add New Plugin" button.
3. In the Search bar enter `Disable Blog`.
4. Find the plugin in the results and click "Install Now."
5. After installation, activate the plugin by clicking "Activate now."

### Install via FTP

1. Download the most current version of the plugin from the WP repository or Github release.
2. Access your site's FTP using the tool of your choice, such as FileZilla or Cyberduck.
3. Unzip the plugin files and upload the `disable-blog` plugin folder into your `wp-content/plugins/` folder.
4. From the WordPress admin screen, navigate to "Plugins."
5. Find the plugin and click "activate."

### Install & Activate via WP CLI

Install the plugin with wp-cli:

{% code overflow="wrap" %}
```bash
wp plugin install disable-blog
```
{% endcode %}

Activate the plugin with wp-cli:

{% code overflow="wrap" %}
```bash
wp plugin activate disable-blog
```
{% endcode %}

### Install via Composer

To add this plugin to your project as a dependency using Composer, use [wpackagist](https://wpackagist.org/).

Run this command:

{% code overflow="wrap" %}
```bash
composer require wpackagist-plugin/disable-blog
```
{% endcode %}

Or add the package to your `composer.json`:

{% code overflow="wrap" %}
```bash
wpackagist-plugin/disable-blog
```
{% endcode %}

### Next step

Activating Disable Blog is not enough on its own. The plugin needs a static front page to redirect visitors to, or its redirects will not work. See [Setting Up Your Site](setting-up.md) to finish the setup.
