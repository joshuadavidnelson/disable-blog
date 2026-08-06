# GitBook Documentation Site Design

**Date:** 2026-08-05
**Status:** Approved

## Goal

Publish a GitBook documentation site for Disable Blog from a `/docs/` folder, matching
the setup already used by archived-post-status. The site covers how to install, use,
uninstall, and extend the plugin.

The docs will publish at `docs.disable.blog`. Until that is live, all internal links are
relative so they resolve correctly on GitHub.

## Writing rules

These apply to every page.

- No em dashes.
- Brief, plainly readable by end users. Short declarative sentences, second person.
- Document the plugin's current released state. Include version nuance only when it
  changes what a user sees or does, written as a "Changed in X.Y.Z" callout.
- No references to intermediate work, branches, or internal process. If it does not
  impact a user on a release branch, it does not go in.

Match the conventions in the archived-post-status `docs/` folder:
`{% hint style="warning" %}` for cautions, `{% code overflow="wrap" %}` for shell and PHP
snippets, `<details>`/`<summary>` for FAQ entries, and the phrase "Add this to your
theme's `functions.php` file or as an [MU plugin](...)" when introducing a filter
example.

## Files created

```
.gitbook.yaml                          repo root, points GitBook at ./docs/
docs/
├── README.md                          landing page
├── SUMMARY.md                         table of contents
├── changelog.md                       GitHub code-block embed of CHANGELOG.md
├── .gitbook/assets/                   empty, ready for future screenshots
├── usage/
│   ├── install-and-activate.md
│   ├── setting-up.md
│   ├── front-end-changes.md
│   ├── admin-changes.md
│   ├── your-content-and-data.md
│   ├── deactivate-and-uninstall.md
│   └── frequently-asked-questions.md
└── extending-the-plugin/
    ├── hooks-filters-and-actions.md
    ├── functions.md
    ├── common-customizations.md
    └── plugin-integrations.md
```

`.gitbook.yaml` is written as clean YAML:

```yaml
root: ./docs/

structure:
  readme: README.md
  summary: SUMMARY.md
```

## Files modified

**`README.md`.** Keeps the badges, description, static front page warning, support,
contributing, and local development sections. The "How does this plugin work?" list and
the FAQ section are replaced by a Documentation section linking into `docs/` with
relative paths.

**`.distignore`.** Add `/docs/` and `.gitbook.yaml` so neither ships in the
WordPress.org plugin ZIP. The current file excludes neither.

**`readme.txt`.** Untouched. The WordPress.org readme stays self-contained.

## Table of contents

```markdown
# Table of contents

* [Disable Blog](README.md)

## ⭐ Usage

* [Install & Activate](usage/install-and-activate.md)
* [Setting Up Your Site](usage/setting-up.md)
* [Front-End Changes](usage/front-end-changes.md)
* [Admin Changes](usage/admin-changes.md)
* [Your Content & Data](usage/your-content-and-data.md)
* [Deactivate & Uninstall](usage/deactivate-and-uninstall.md)
* [Frequently Asked Questions](usage/frequently-asked-questions.md)

## 🔌 Extending the Plugin

* [Hooks, Filters, and Actions](extending-the-plugin/hooks-filters-and-actions.md)
* [PHP Functions](extending-the-plugin/functions.md)
* [Common Customizations](extending-the-plugin/common-customizations.md)
* [Plugin Integrations](extending-the-plugin/plugin-integrations.md)

***

* [Changelog](changelog.md)
```

## Page contents

### Usage

**Install & Activate.** Plugins screen, FTP, WP-CLI, and Composer via wpackagist.

**Setting Up Your Site.** The static front page requirement, why it matters, and what
does not work without it. Covers the optional posts page and its redirect. Opens with a
warning hint, since this is the single most common source of support questions.

**Front-End Changes.** What visitors no longer see. Feeds and feed links, posts removed
from archives, sitemap changes, REST API and XML-RPC restrictions, author archive
behavior, and the full list of 301 redirects.

**Admin Changes.** What disappears from wp-admin. Menu removals, admin redirects,
comment handling, dashboard widgets, Reading and Writing settings, the user table Posts
to Pages column swap, taxonomy count corrections, and the Site Health REST check
replacement.

The admin redirect table lists only the screens that actually redirect in 0.5.5:
`post.php`, `edit.php`, `post-new.php`, `edit-tags.php`, `edit-comments.php`,
`options-discussion.php`, and `options-writing.php`. Tools is described as removed from
the menu, not redirected.

**Your Content & Data.** Nothing is deleted. Posts, categories, tags, and comments stay
in the database but become inaccessible while the plugin is active. Explains how to
actually delete that content, and what the plugin itself stores (`dwpb_version` and
`dwpb_previous_version`).

**Deactivate & Uninstall.** Deactivating from the Plugins screen, with WP-CLI, and
removing via Composer. What uninstall deletes. Notes the multisite behavior, including
that networks of 5000 or more sites skip the per-site option cleanup.

**Frequently Asked Questions.** Collapsible `<details>` entries. Carries over the four
existing FAQs (disabling comments, deleting posts and comments, disabling author
archives, changing plugin behavior) and adds ones the code answers: custom post types
using categories or tags, Query Loop block behavior, where redirects point, and
multisite.

### Extending the Plugin

**Hooks, Filters, and Actions.** The complete reference. An index at the top, then
entries grouped by area: Redirects, Front-End, Admin, and Post Types & Taxonomies. Each
entry gives a description, Since version, parameters, return value, and a runnable
example.

Covers 25 named filters:

`dwpb_pass_query_string_on_redirect`, `dwpb_allowed_query_vars`,
`dwpb_redirect_status_code`, `dwpb_redirect_front_end`, `dwpb_front_end_redirect_url`,
`dwpb_redirect_admin`, `dwpb_admin_redirect_url`, `dwpb_disable_feed`,
`dwpb_redirect_feeds`, `dwpb_feed_message`, `dwpb_feed_die_message`,
`dwpb_disable_author_archives`, `dwpb_author_archive_post_types`,
`dwpb_tag_post_types`, `dwpb_category_post_types`, `dwpb_taxonomy_support`,
`dwpb_disabled_xmlrpc_methods`, `dwpb_remove_pingback_header`,
`dwpb_disable_user_sitemap`, `dwpb_menu_pages_to_remove`,
`dwpb_menu_subpages_to_remove`, `dwpb_remove_options_writing`,
`dwpb_unregister_widgets`, `dwpb_admin_user_post_types`, and
`dpwb_disable_user_post_column`.

Plus the six front-end redirect filters built from the page being viewed:
`dwpb_redirect_post`, `dwpb_redirect_post_tag_archive`,
`dwpb_redirect_category_archive`, `dwpb_redirect_blog_page`,
`dwpb_redirect_date_archive`, and `dwpb_redirect_author_archive`.

Plus the seven admin redirect filters that fire: `dwpb_redirect_admin_post`,
`dwpb_redirect_admin_edit`, `dwpb_redirect_admin_post_new`,
`dwpb_redirect_admin_edit_tags`, `dwpb_redirect_admin_edit_comments`,
`dwpb_redirect_admin_options_discussion`, and `dwpb_redirect_admin_options_writing`.

Plus three dynamic filters: `dwpb_post_types_supporting_{$feature}`,
`dwpb_disable_{$metabox_id}` (the four dashboard widgets are `dashboard_quick_press`,
`dashboard_recent_drafts`, `dashboard_incoming_links`, and `dashboard_activity`), and
`dpwb_create_user_{$post_type}_column`.

Plus the `dwpb_init` action.

Two filters are misspelled in the source, using `dpwb` rather than `dwpb`:
`dpwb_disable_user_post_column` and `dpwb_create_user_{$post_type}_column`. Document
them exactly as they are, with a short note, since renaming them would break sites.

Do not document `https_local_ssl_verify`. That is a WordPress core filter the plugin
re-applies inside its Site Health check, not a plugin filter.

Do not document `dwpb_redirect_admin_term` or `dwpb_redirect_admin_tools`. Neither
fires in 0.5.5, so neither belongs in the docs until the underlying issue is resolved.
For the same reason, the Admin Changes page must not claim that the Tools screen
redirects to the dashboard. It is removed from the menu, which is what the docs should
say.

**PHP Functions.** `dwpb_post_types_with_feature()` and `dwpb_post_types_with_tax()`.
Signature, parameters, return value, examples, and the caching behavior.

**Common Customizations.** Task-oriented recipes: disable author archives, keep tags and
categories working for a custom post type, change redirect targets and the status code,
keep Settings > Writing available, preserve a feed, allow query variables through
redirects, and add custom post types to author archives.

**Plugin Integrations.** How Disable Blog behaves alongside Disable Comments and
WooCommerce, followed by a section on how `Disable_Blog_Integrations` works and how to
add your own integration.

### Changelog

A GitBook GitHub code-block embed pointing at `CHANGELOG.md` on the `stable` branch, the
same pattern archived-post-status uses.

## Accuracy

Every filter entry takes its Since version, default value, and parameter list from the
source rather than from inference. Where a docblock disagrees with the code, the code
wins. Every code example is written to run as given.

The docs describe what 0.5.5 actually does, which in two places differs from what
`README.md` currently claims. The Tools screen redirect is the known case. Any other
mismatch found while writing is resolved in favor of the code, and noted here.

## Out of scope

- Screenshots. The pages are structured so images can be added later without rewriting.
  `docs/.gitbook/assets/` is created empty and ready.
- Changes to `readme.txt`.
- Any change to plugin behavior.
