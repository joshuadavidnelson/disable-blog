# GitBook Documentation Site Design

**Date:** 2026-08-05
**Status:** Approved
**Documents plugin version:** 0.5.6

## Goal

Publish a GitBook documentation site for Disable Blog from a `/docs/` folder, matching
the setup already used by archived-post-status. The site covers how to install, use,
uninstall, and extend the plugin.

The docs will publish at `docs.disable.blog`. Until that is live, all internal links are
relative so they resolve correctly on GitHub.

## Version and branch

This branch is based on `stable` (0.5.5), but the docs describe **0.5.6**, which is in
development on `e2e/playwright`. 0.5.6 changes the public filter surface enough that
documenting 0.5.5 would be wrong on release day.

Every source detail is read from `e2e/playwright`, not from the checked-out `stable`
tree.

Only new files are added, so nothing conflicts with the 0.5.6 work in flight.

## Writing rules

These apply to every page.

- No em dashes.
- Brief, plainly readable by end users. Short declarative sentences, second person.
- Document the plugin's released behavior. Include version nuance only when it changes
  what a user sees or does, written as a "Changed in X.Y.Z" callout.
- Do not document bugs, known issues, or anything about work in progress. If it does not
  impact a user on a release branch, it does not go in.
- No references to branches, refactors, or internal process.

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

None. `README.md`, `readme.txt`, and `.distignore` are left untouched so the 0.5.6 work
in flight can absorb those edits.

Two follow-ups are handed back rather than done here:

1. `.distignore` needs `/docs/` and `.gitbook.yaml` added, or the docs ship inside the
   WordPress.org plugin ZIP. The current file excludes neither.
2. `README.md` can drop its "How does this plugin work?" list and FAQ in favor of links
   into `docs/`, once the docs are live.

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

The admin redirect table lists all nine screens that redirect: `post.php`, `edit.php`,
`post-new.php`, `term.php`, `edit-tags.php`, `edit-comments.php`,
`options-discussion.php`, `options-writing.php`, and `tools.php`.

Dashboard widgets removed are Quick Press and Activity.

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

Covers 26 named filters:

`dwpb_pass_query_string_on_redirect`, `dwpb_allowed_query_vars`,
`dwpb_redirect_status_code`, `dwpb_redirect_front_end`, `dwpb_front_end_redirect_url`,
`dwpb_redirect_admin`, `dwpb_admin_redirect_url`, `dwpb_disable_feed`,
`dwpb_redirect_feeds`, `dwpb_feed_message`, `dwpb_feed_die_message`,
`dwpb_disable_author_archives`, `dwpb_author_archive_post_types`,
`dwpb_tag_post_types`, `dwpb_category_post_types`, `dwpb_taxonomy_support`,
`dwpb_disabled_xmlrpc_methods`, `dwpb_remove_pingback_header`,
`dwpb_disable_user_sitemap`, `dwpb_disable_removed_sitemaps`,
`dwpb_menu_pages_to_remove`, `dwpb_menu_subpages_to_remove`,
`dwpb_remove_options_writing`, `dwpb_unregister_widgets`,
`dwpb_admin_user_post_types`, and `dwpb_disable_user_post_column`.

Plus the six front-end redirect filters, named for the page being viewed:
`dwpb_redirect_post`, `dwpb_redirect_post_tag_archive`,
`dwpb_redirect_category_archive`, `dwpb_redirect_blog_page`,
`dwpb_redirect_date_archive`, and `dwpb_redirect_author_archive`.

Plus the nine admin redirect filters: `dwpb_redirect_admin_post`,
`dwpb_redirect_admin_edit`, `dwpb_redirect_admin_post_new`,
`dwpb_redirect_admin_term`, `dwpb_redirect_admin_edit_tags`,
`dwpb_redirect_admin_edit_comments`, `dwpb_redirect_admin_options_discussion`,
`dwpb_redirect_admin_options_writing`, and `dwpb_redirect_admin_tools`.

Plus three dynamic filters: `dwpb_post_types_supporting_{$feature}`,
`dwpb_disable_{$metabox_id}` (valid IDs are `dashboard_quick_press` and
`dashboard_activity`), and `dwpb_create_user_{$post_type}_column`.

Plus the `dwpb_init` action.

A short Deprecated section at the end covers the three filters renamed in 0.5.6. Each
still works through `apply_filters_deprecated()`, and each entry names its replacement:

| Deprecated | Use instead |
| --- | --- |
| `dwpb_redirect_admin_options_tools` | `dwpb_redirect_admin_tools` |
| `dpwb_disable_user_post_column` | `dwpb_disable_user_post_column` |
| `dpwb_create_user_{$post_type}_column` | `dwpb_create_user_{$post_type}_column` |

Do not document `https_local_ssl_verify`. That is a WordPress core filter the plugin
re-applies inside its Site Health check, not a plugin filter.

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
same pattern archived-post-status uses. It picks up 0.5.6 automatically once that
release merges.

## Accuracy

Every filter entry takes its Since version, default value, and parameter list from the
0.5.6 source rather than from inference. Where a docblock disagrees with the code, the
code wins. Every code example is written to run as given.

## Out of scope

- Screenshots. The pages are structured so images can be added later without rewriting.
  `docs/.gitbook/assets/` is created empty and ready.
- Any edit to `README.md`, `readme.txt`, or `.distignore`.
- Any change to plugin behavior.
