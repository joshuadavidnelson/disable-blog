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

// Strict mode makes calling a WP function with no WP_Mock::userFunction() stub registered
// for the CURRENT test throw ExpectationFailedException instead of silently returning null.
// Without it, a test missing a stub only fails when it's the first test in the run to reach
// that function (a fatal "call to undefined function", order-dependent); once any earlier
// test has declared the function, later tests missing the stub pass by accident. Strict mode
// makes every missing stub fail every time, regardless of run order. activateStrictMode()
// is a no-op once WP_Mock::$__bootstrapped is true, so it must run before bootstrap().
WP_Mock::activateStrictMode();
WP_Mock::bootstrap();

require __DIR__ . '/Support/WpPolyfills.php';
require __DIR__ . '/includes/TestCase.php';
require __DIR__ . '/includes/PluginLifecycleTestCase.php';

// Core class stand-ins used across multiple test files; required once here (rather than
// per test file) so nothing tries to redeclare them and fatals.
require __DIR__ . '/Support/fixtures/class-wp-post-stub.php';
require __DIR__ . '/Support/fixtures/class-public-query-double.php';

// Plugin files under test.
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/class-disable-blog-loader.php';
require __DIR__ . '/../../includes/class-disable-blog-i18n.php';
require __DIR__ . '/../../includes/class-disable-blog-integrations.php';
require __DIR__ . '/../../includes/class-disable-blog-activator.php';
require __DIR__ . '/../../includes/class-disable-blog-deactivator.php';

// Disable_Blog_Functions is deliberately NOT required here -- see ConstructorInjectionTest.php's
// note; LoaderTest's autoloader test needs it to remain undefined until its own isolated process.
require __DIR__ . '/../../includes/class-disable-blog-public.php';
