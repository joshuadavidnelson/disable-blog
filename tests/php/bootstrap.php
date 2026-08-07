<?php
/**
 * PHPUnit bootstrap for the plugin's WP_Mock-based unit suite.
 *
 * @package DisableBlog
 */

require __DIR__ . '/../../vendor/autoload.php';

WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();

require __DIR__ . '/Support/WpPolyfills.php';
require __DIR__ . '/includes/TestCase.php';

// Plugin file under test.
require __DIR__ . '/../../includes/functions.php';
