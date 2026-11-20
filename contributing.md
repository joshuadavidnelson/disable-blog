# Contributing to Disable Blog

Thank you for considering contributing to the on-going development of this plugin! All contributions are welcomed and support can come in many forms:

- **Sponsoring** - [Donate](https://joshuadnelson.com/donate/) to help support plugin development hours.

- **Bug reporting** - [Submit an issue](https://github.com/joshuadavidnelson/disable-blog/issues/) on the Github repo or [WordPress.org Support forum](https://wordpress.org/support/plugin/disable-blog/), _knowing_ about an issue is the first step to fixing it! Bug reports are a vital way to help make the plugin better for you and for others. **If you have found a security vulnerability, please [contact the developer directly](mailto:josh@joshuadnelson.com).**

- **Translations** - The effort to localize strings on the plugin is entirely a volunteer effort, and these contributions have a huge impact on the plugin's accessibility. Thank you to our translators! Learn more about how you can contribute [here](https://make.wordpress.org/polyglots/teams/).

- **Documentation** - Suggest edits to our documentation, post a walkthrough on how to use or extend the plugin, or provide help to other users on the [support forums](https://wordpress.org/support/plugin/disable-blog/).

- **Pull Requests** - Suggest changes to the code to enhance the plugin, see the [Pull Requests](README.md#pull-requests) section in the main readme doc. _Please direct all Pull Requests to the `develop` branch_

- **Positive Reviews** - [Adding a positive review](https://wordpress.org/support/plugin/disable-blog/reviews/#new-post) to the WordPress page helps spread the word and encourage otherts to trust our efforts.

- **Testing** - Taking the time to test the plugin and report back any findings is a great help! Feel free to test [current pull requests](https://github.com/joshuadavidnelson/disable-blog/pulls/) and leave comments, or test the current version locally with the newest version of WordPress or your own plugin. We are strongly in favor of Cross Plugin Integration - being compatible with as many other plugins as possible!

- **Support** - We welcome solutions and constructive responses to [support forum](https://wordpress.org/support/plugin/disable-blog/) posts.

However you choose to contribute, we ask that you follow the [Code of Conduct](code-of-conduct.md) and conduct yourself in a compassionate, professional manner. Thank you again for your contributions!

## Branches

Start branches from `develop` and open pull requests against `develop`. Only small, development-only changes go straight to `stable`.

## Core compatibility checks

Two checks watch for changes in WordPress core that could expose posts despite the plugin. Both run against a local `wp-env` site.

### Core surface snapshot

The snapshot records the parts of core Disable Blog depends on and compares them with a committed baseline. The code is in `tests/compat/` and the baseline is `tests/compat/baseline/surface.json`.

- `npm run compat:dump` prints the current surface.
- `npm run compat:check` compares it with the baseline, prints a report, writes it to `artifacts/surface-diff.md` and exits with an error on any difference.
- `npm run compat:update` overwrites the baseline with the current surface.

The report groups changes by section. Each line is `added`, `removed` or `changed`, followed by the key and, where it applies, the value (`changed` shows the old and new value).

Run `npm run compat:update` and commit the new baseline when core added or changed something and the plugin already handles it, or when you have reviewed the change and it needs no plugin work. Do not update the baseline when the report shows a new post surface (a REST route, post type, feed, sitemap or similar) that the plugin does not yet hide. Fix the plugin first, then update the baseline.

### Post leak crawl

`tests/e2e/specs/compat/post-leak.spec.ts` probes REST routes, front-end URLs, XML-RPC methods, abilities and comment totals as a logged-out visitor and as an admin, and fails when a response contains a post. It runs with the rest of the browser suite (`npm run test:e2e`).

Some responses are expected to include posts, such as the admin comment list. These are listed in `tests/compat/leak-allowlist.json`. To add an entry, fix the plugin first if the leak is a bug, then add an object with these fields:

- `surface`: `rest`, `front`, `xmlrpc`, `ability` or `count`.
- `actor`: `anon` or `admin`.
- `urlPattern`: text the hit's URL contains (for counts and XML-RPC, the key or method name).
- `reason`: why the leak is accepted. Every entry needs one, so a later reader can tell whether it still holds.
- `minWp` (optional): the lowest WordPress version the entry applies to, such as `"7.2"`. Below it the entry is ignored and never reported as unused.

An entry that matches no hit fails the run, so remove entries when the leak goes away.

## Continuous integration

- Every pull request runs the snapshot check on the pinned `latest` (PHP 8.3) leg of the E2E workflow. The core version is pinned in `.wp-env.json`, which is the version the baseline records.
- `core-compat.yml` runs weekly (and on demand from the Actions tab) against the newest released core, the current beta or release candidate when one exists, and nightly `master`. It runs the snapshot check and the browser suite, and files or updates an issue labeled `core-compat` when anything fails.
