Disable Blog
======================

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/disable-blog)](https://wordpress.org/plugins/disable-blog/) ![Downloads](https://img.shields.io/wordpress/plugin/dt/disable-blog.svg) ![Rating](https://img.shields.io/wordpress/plugin/r/disable-blog.svg)

[![WP compatibility](https://plugintests.com/plugins/wporg/disable-blog/wp-badge.svg)](https://plugintests.com/plugins/wporg/disable-blog/latest) [![PHP compatibility](https://plugintests.com/plugins/wporg/disable-blog/php-badge.svg)](https://plugintests.com/plugins/wporg/disable-blog/latest)

**Requires at least WordPress:** 5.9  
**Tested up to WordPress:** 6.9  
**Stable version:** 0.5.5  
**License:** GPLv2 or later  
**Requires PHP:** 7.4  
**Tested up to PHP:** 8.4  

All the power of WordPress, without a blog.

## Description

Disable Blog is a comprehensive plugin to disable the built-in blogging functionality on your site. You'll be free to use pages and custom post types without the burden of a blog.

The blog is "disabled" when the plugin is activated, which removes support for the core 'post' type, hides blog-related admin pages/settings, and redirects urls on both the public and admin portions of the site. See [Front-End Changes](docs/usage/front-end-changes.md) and [Admin Changes](docs/usage/admin-changes.md) for the detailed functionality list.

### Important: Set a front page

**You need to select a page to act as the home page**. If Settings > Reading > "Front Page Displays" is not set to show a page, then this plugin will not function correctly. Not doing so will mean that your post page can still be visible on the front-end of the site. It's not required, but it is recommended you select a page for the  "posts page" setting, this page will be automatically redirected to the static "home page."

### Site Content & Data

This plugin will not delete any of your site's data, however existing blog related content will not be accessible while this plugins is active. This includes posts, categories, tags, and related comments.

If you have content and wish to remove it, either delete that content prior to activation or deactivate this plugin, delete it, and re-active. 

### Comments

Comments remain enabled, unless the 'post' type is the only type supporting comments (pages also support comments by default, so the comments section won't disappear in most cases). If you're looking to disable comments completely, check out the [Disable Comments](https://wordpress.org/plugins/disable-comments/) plugin.

## Documentation

Full documentation lives in [docs/](docs/README.md).

- [Install & Activate](docs/usage/install-and-activate.md)
- [Setting Up Your Site](docs/usage/setting-up.md)
- [Front-End Changes](docs/usage/front-end-changes.md)
- [Admin Changes](docs/usage/admin-changes.md)
- [Your Content & Data](docs/usage/your-content-and-data.md)
- [Deactivate & Uninstall](docs/usage/deactivate-and-uninstall.md)
- [Frequently Asked Questions](docs/usage/frequently-asked-questions.md)

Extending the plugin:

- [Hooks, Filters, and Actions](docs/extending-the-plugin/hooks-filters-and-actions.md)
- [PHP Functions](docs/extending-the-plugin/functions.md)
- [Common Customizations](docs/extending-the-plugin/common-customizations.md)
- [Plugin Integrations](docs/extending-the-plugin/plugin-integrations.md)

## Support

This plugin is maintained for free but **please reach out** and I will assist you as soon as possible. You can visit the [WordPress.org support forums](https://wordpress.org/support/plugin/disable-blog/) or create an [issue](https://github.com/joshuadavidnelson/disable-blog/issues/) on the [GitHub repository](https://github.com/joshuadavidnelson/disable-blog/).

## Contributing

All contributions are welcomed and considered, please refer to [contributing.md](contributing.md).

### Pull requests
All pull requests should be directed at the `develop` branch, and will be reviewed prior to merging. No pull requests will be merged with failing tests, but it's okay if you don't initially pass tests. Please create a draft pull request for proof of concept code or changes you'd like to have input on prior to review.

For instance, to suggest a solution to an issue please create fork with a branch like `issue-42`. Or, if you're proposing a new feature, create a fork with the branch name indicating the feature like `feature-example-bananas`

All improvements are merged into `develop` and then queued up for release before being merged into `stable`. Releases are deployed via github actions to wordpress.org on tagging a new release.

### Main Branches

The `stable` branch is reserved for releases and intended to be a mirror of the official current release, or `trunk` on wordpress.org.

The `develop` branch is the most current working branch. _Please direct all pull requests to the `develop` branch_

### Developing Disable Blog Locally

**Requirements:**
- Docker
- Node Package Manager (npm)

This repo contains the files needed to boot up a local development environment using [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

Run `npm install` and the `npm run env:start` to boot up a local environment. 
