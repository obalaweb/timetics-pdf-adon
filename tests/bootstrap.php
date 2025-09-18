<?php
/**
 * Test Bootstrap
 * 
 * Sets up the WordPress test environment for the Timetics PDF Addon tests
 */

// Define test constants
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php');

// Load WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Load WordPress test functions
require_once $_tests_dir . '/includes/functions.php';

// Load the plugin
function _manually_load_plugin() {
    // Load WordPress
    require dirname(dirname(__DIR__)) . '/wp-load.php';
    
    // Load Timetics core plugin
    if (file_exists(dirname(__DIR__) . '/timetics/timetics.php')) {
        require_once dirname(__DIR__) . '/timetics/timetics.php';
    }
    
    // Load Timetics Pro if available
    if (file_exists(dirname(__DIR__) . '/timetics-pro/timetics-pro.php')) {
        require_once dirname(__DIR__) . '/timetics-pro/timetics-pro.php';
    }
    
    // Load our plugin
    require_once dirname(__DIR__) . '/timetics-pdf-addon.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load our test base class
require_once __DIR__ . '/class-test-base.php';
