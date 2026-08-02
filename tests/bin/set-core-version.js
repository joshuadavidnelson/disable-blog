#!/usr/bin/env node

/**
 * Set (or clear) the WordPress core version used by wp-env for Disable Blog e2e tests.
 *
 * Usage:
 *   node ./tests/bin/set-core-version.js <version>
 *   node ./tests/bin/set-core-version.js latest
 *   node ./tests/bin/set-core-version.js
 */

const fs = require("fs");
const { exit } = require("process");

const path = `${process.cwd()}/.wp-env.override.json`;

let config = fs.existsSync(path) ? require(path) : {};

const args = process.argv.slice(2);

if (args.length == 0) exit(0);

if (args[0] == "latest") {
  if (fs.existsSync(path)) {
    fs.unlinkSync(path);
  }
  exit(0);
}

config.core = args[0];

if (!config.core.match(/^WordPress\/WordPress\#/)) {
  config.core = "WordPress/WordPress#" + config.core;
}

try {
  fs.writeFileSync(path, JSON.stringify(config));
} catch (err) {
  console.error(err);
}
