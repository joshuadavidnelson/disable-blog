<?php
/**
 * PHPUnit bootstrap for the plugin's WP_Mock-based unit suite.
 *
 * @package DisableBlog
 */

require __DIR__ . '/../../vendor/autoload.php';

// WP_Mock::bootstrap() defines ABSPATH only `if ( ! defined() )`, specifically so test
// environments can override it first -- this points it at tests/php/Support/fixtures/ so
// IntegrationsTest's isolated-process test can exercise Disable_Blog_Integrations::
// is_plugin_active()'s `include_once ABSPATH . 'wp-admin/includes/plugin.php'` fallback
// against a real fixture file instead of a nonexistent WordPress core path.
define( 'ABSPATH', __DIR__ . '/Support/fixtures/' );

WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();

require __DIR__ . '/Support/WpPolyfills.php';
require __DIR__ . '/includes/TestCase.php';
require __DIR__ . '/includes/PluginLifecycleTestCase.php';

// Plugin files under test.
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/class-disable-blog-loader.php';
require __DIR__ . '/../../includes/class-disable-blog-i18n.php';
require __DIR__ . '/../../includes/class-disable-blog-integrations.php';
require __DIR__ . '/../../includes/class-disable-blog-activator.php';
require __DIR__ . '/../../includes/class-disable-blog-deactivator.php';
