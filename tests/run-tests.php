<?php
/**
 * Test Runner
 * 
 * Runs all tests for the Timetics PDF Addon
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define test constants
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php');

// Check if we're running from command line
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

echo "🧪 Timetics PDF Addon Test Suite\n";
echo "================================\n\n";

// Check if WordPress test environment is available
$wp_tests_dir = getenv('WP_TESTS_DIR');
if (!$wp_tests_dir) {
    $wp_tests_dir = '/tmp/wordpress-tests-lib';
}

if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
    echo "❌ WordPress test environment not found at: $wp_tests_dir\n";
    echo "Please set WP_TESTS_DIR environment variable or install WordPress test suite.\n";
    exit(1);
}

// Load WordPress test environment
require_once $wp_tests_dir . '/includes/functions.php';

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
require $wp_tests_dir . '/includes/bootstrap.php';

// Load our test base class
require_once __DIR__ . '/class-test-base.php';

// Simple test runner
class SimpleTestRunner {
    private $passed = 0;
    private $failed = 0;
    private $skipped = 0;
    private $results = [];
    
    public function runTests() {
        echo "🚀 Starting tests...\n\n";
        
        // Get all test files
        $test_files = [
            __DIR__ . '/unit/test-wordpress-integration.php',
            __DIR__ . '/unit/test-timetics-integration.php',
            __DIR__ . '/unit/test-timetics-pro-integration.php',
            __DIR__ . '/integration/test-pdf-generation.php'
        ];
        
        foreach ($test_files as $test_file) {
            if (file_exists($test_file)) {
                $this->runTestFile($test_file);
            } else {
                echo "⚠️  Test file not found: $test_file\n";
            }
        }
        
        $this->printSummary();
    }
    
    private function runTestFile($test_file) {
        $class_name = basename($test_file, '.php');
        $class_name = str_replace('test-', 'Test_', $class_name);
        $class_name = str_replace('-', '_', $class_name);
        $class_name = ucwords($class_name, '_');
        
        echo "📁 Running $class_name...\n";
        
        // Load the test file
        require_once $test_file;
        
        if (!class_exists($class_name)) {
            echo "❌ Class $class_name not found in $test_file\n";
            $this->failed++;
            return;
        }
        
        // Get all test methods
        $reflection = new ReflectionClass($class_name);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $test_methods = array_filter($methods, function($method) {
            return strpos($method->getName(), 'test_') === 0;
        });
        
        foreach ($test_methods as $method) {
            $this->runTestMethod($class_name, $method->getName());
        }
        
        echo "\n";
    }
    
    private function runTestMethod($class_name, $method_name) {
        try {
            $test_instance = new $class_name();
            $test_instance->setUp();
            
            echo "  🔍 $method_name... ";
            
            $test_instance->$method_name();
            
            echo "✅ PASSED\n";
            $this->passed++;
            $this->results[] = "✅ $class_name::$method_name - PASSED";
            
        } catch (Exception $e) {
            echo "❌ FAILED\n";
            echo "     Error: " . $e->getMessage() . "\n";
            $this->failed++;
            $this->results[] = "❌ $class_name::$method_name - FAILED: " . $e->getMessage();
            
        } catch (Error $e) {
            echo "💥 ERROR\n";
            echo "     Error: " . $e->getMessage() . "\n";
            $this->failed++;
            $this->results[] = "💥 $class_name::$method_name - ERROR: " . $e->getMessage();
        }
        
        try {
            $test_instance->tearDown();
        } catch (Exception $e) {
            // Ignore teardown errors
        }
    }
    
    private function printSummary() {
        echo "📊 Test Summary\n";
        echo "===============\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "⏭️  Skipped: {$this->skipped}\n";
        echo "📈 Total: " . ($this->passed + $this->failed + $this->skipped) . "\n\n";
        
        if ($this->failed > 0) {
            echo "❌ Failed Tests:\n";
            foreach ($this->results as $result) {
                if (strpos($result, '❌') === 0 || strpos($result, '💥') === 0) {
                    echo "   $result\n";
                }
            }
            echo "\n";
        }
        
        if ($this->passed > 0) {
            echo "✅ All tests completed!\n";
        }
        
        // Exit with appropriate code
        exit($this->failed > 0 ? 1 : 0);
    }
}

// Run the tests
$runner = new SimpleTestRunner();
$runner->runTests();
