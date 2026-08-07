<?php
/**
 * Stand-in for WordPress core's wp-admin/includes/plugin.php.
 *
 * Disable_Blog_Integrations::is_plugin_active() only include_once()s this path when
 * is_plugin_active() is not yet defined. Loaded solely by IntegrationsTest's isolated
 * process test for that branch; ABSPATH is pointed at this fixtures directory there.
 *
 * @package DisableBlog
 */

/**
 * Minimal is_plugin_active() so the include path under test has something real to call.
 *
 * @param string $plugin The plugin path.
 * @return bool
 */
function is_plugin_active( $plugin ) {
	return 'fixture-plugin/fixture-plugin.php' === $plugin;
}
