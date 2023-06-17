<?php
/**
 * Bootsrap file for tests.
 *
 * @package Disable_Blog
 */

require_once __DIR__ . '/../../vendor/autoload.php';

WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();

define( 'DB_PLUGIN_PATH', dirname( __DIR__, 2 ) );

require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/TestCase.php';

// Load plugin files.
require_once DB_PLUGIN_PATH . '/includes/functions.php';
