#!/usr/bin/env node

/**
 * Set (or clear) the WordPress core version used by wp-env for Disable Blog e2e tests.
 *
 * Usage:
 *   node ./tests/bin/set-core-version.js <version>
 *   node ./tests/bin/set-core-version.js https://wordpress.org/wordpress-7.1.zip
 *   node ./tests/bin/set-core-version.js latest
 *   node ./tests/bin/set-core-version.js
 *
 * "latest" removes the override, so the core pinned in .wp-env.json applies.
 */

const fs = require("fs");
const { exit } = require("process");

const path = `${process.cwd()}/.wp-env.override.json`;

let config = fs.existsSync(path) ? require(path) : {};

const args = process.argv.slice(2);

if (args.length == 0) exit(0);

const value = args[0].trim();

if (value === "") {
  console.error("Core version is empty. Pass a version, a ref, an https:// zip URL, or latest.");
  exit(1);
}

if (value == "latest") {
  if (fs.existsSync(path)) {
    fs.unlinkSync(path);
  }
  exit(0);
}

if (/^http:\/\//i.test(value)) {
  console.error("Core zip URLs must use https://, got: " + value);
  exit(1);
}

if (/^https:\/\//i.test(value)) {
  // A full zip URL is used as-is, apart from a normalized scheme.
  config.core = value.replace(/^https:\/\//i, "https://");
} else if (value.match(/^WordPress\/WordPress\#/)) {
  config.core = value;
} else {
  config.core = "WordPress/WordPress#" + value;
}

try {
  fs.writeFileSync(path, JSON.stringify(config));
} catch (err) {
  // Exit non-zero: a swallowed failure here leaves no override file, which
  // the CI step reads as the pinned core, silently testing the wrong
  // WordPress on the leg that exists to pin one.
  console.error(err);
  exit(1);
}
