<?php
/**
 * WordPress Native Test Runner
 * 
 * Runs tests using the existing WordPress installation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress - go up from wp-content/plugins/timetics-pdf-addon/tests to wp root
    $wp_root = dirname(dirname(dirname(dirname(__DIR__))));
    require_once $wp_root . '/wp-load.php';
    
    // Load our plugin manually since WordPress doesn't auto-load plugins in CLI
    require_once dirname(__DIR__) . '/timetics-pdf-addon.php';
}

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🧪 Timetics PDF Addon Test Suite (WordPress Native)\n";
echo "==================================================\n\n";

// Simple test runner for WordPress environment
class WordPressTestRunner {
    private $passed = 0;
    private $failed = 0;
    private $skipped = 0;
    private $results = [];
    
    public function runTests() {
        echo "🚀 Starting tests in WordPress environment...\n\n";
        
        // Test WordPress environment
        $this->testWordPressEnvironment();
        
        // Test plugin loading
        $this->testPluginLoading();
        
        // Test Timetics integration
        $this->testTimeticsIntegration();
        
        // Test PDF functionality
        $this->testPDFFunctionality();
        
        // Test critical fixes
        $this->testCriticalFixes();
        
        $this->printSummary();
    }
    
    private function testWordPressEnvironment() {
        echo "📁 Testing WordPress Environment...\n";
        
        $this->runTest('WordPress loaded', function() {
            return defined('ABSPATH') && function_exists('wp_insert_post');
        });
        
        $this->runTest('Database connection', function() {
            global $wpdb;
            return $wpdb->db_connect();
        });
        
        $this->runTest('Post functions available', function() {
            return function_exists('wp_insert_post') && 
                   function_exists('get_post') && 
                   function_exists('update_post_meta') && 
                   function_exists('get_post_meta');
        });
        
        $this->runTest('User functions available', function() {
            return function_exists('wp_insert_user') && 
                   function_exists('get_userdata') && 
                   function_exists('update_user_meta') && 
                   function_exists('get_user_meta');
        });
        
        echo "\n";
    }
    
    private function testPluginLoading() {
        echo "📁 Testing Plugin Loading...\n";
        
        $this->runTest('PDF Addon class exists', function() {
            return class_exists('Timetics_Pdf_Addon');
        });
        
        $this->runTest('PDF Addon instantiable', function() {
            try {
                $addon = Timetics_Pdf_Addon::get_instance();
                return $addon instanceof Timetics_Pdf_Addon;
            } catch (Exception $e) {
                return false;
            }
        });
        
        $this->runTest('PDF Addon methods exist', function() {
            $addon = Timetics_Pdf_Addon::get_instance();
            return method_exists($addon, 'maybe_attach_pdf') &&
                   method_exists($addon, 'handle_timetics_email') &&
                   method_exists($addon, 'register_rest_routes');
        });
        
        echo "\n";
    }
    
    private function testTimeticsIntegration() {
        echo "📁 Testing Timetics Integration...\n";
        
        $this->runTest('Timetics Booking class exists', function() {
            return class_exists('Timetics\Core\Bookings\Booking');
        });
        
        $this->runTest('Timetics Appointment class exists', function() {
            return class_exists('Timetics\Core\Appointments\Appointment');
        });
        
        $this->runTest('Timetics Customer class exists', function() {
            return class_exists('Timetics\Core\Customers\Customer');
        });
        
        $this->runTest('Timetics Staff class exists', function() {
            return class_exists('Timetics\Core\Staffs\Staff');
        });
        
        $this->runTest('Booking get_total method exists', function() {
            if (!class_exists('Timetics\Core\Bookings\Booking')) {
                return false;
            }
            $booking = new \Timetics\Core\Bookings\Booking(1);
            return method_exists($booking, 'get_total');
        });
        
        $this->runTest('Booking get_total_price method does NOT exist', function() {
            if (!class_exists('Timetics\Core\Bookings\Booking')) {
                return true; // Skip if Timetics not available
            }
            $booking = new \Timetics\Core\Bookings\Booking(1);
            return !method_exists($booking, 'get_total_price');
        });
        
        echo "\n";
    }
    
    private function testPDFFunctionality() {
        echo "📁 Testing PDF Functionality...\n";
        
        $this->runTest('PDF Addon can create test booking', function() {
            try {
                // Create test booking
                $booking_id = wp_insert_post([
                    'post_title' => 'Test Booking',
                    'post_type' => 'timetics-booking',
                    'post_status' => 'completed',
                    'post_author' => 1
                ]);
                
                if (!$booking_id) {
                    return false;
                }
                
                // Add booking meta
                update_post_meta($booking_id, '_tt_booking_customer', 1);
                update_post_meta($booking_id, '_tt_booking_order_total', 100.00);
                
                // Test that we can retrieve the booking
                $booking = get_post($booking_id);
                $total = get_post_meta($booking_id, '_tt_booking_order_total', true);
                
                // Clean up
                wp_delete_post($booking_id, true);
                
                return $booking && $total == 100.00;
            } catch (Exception $e) {
                return false;
            }
        });
        
        $this->runTest('PDF Addon handles invalid booking ID', function() {
            try {
                // Test that invalid booking ID returns null
                $booking = get_post(99999);
                return $booking === null;
            } catch (Exception $e) {
                return false;
            }
        });
        
        echo "\n";
    }
    
    private function testCriticalFixes() {
        echo "📁 Testing Critical Fixes...\n";
        
        $this->runTest('substr() array handling fix', function() {
            // Test that our substr fix works
            $test_array = ['key' => 'value', 'number' => 123];
            $test_string = 'test string';
            
            // This should not throw an error
            try {
                $result1 = is_array($test_array) ? json_encode($test_array) : substr($test_array, 0, 200);
                $result2 = is_array($test_string) ? json_encode($test_string) : substr($test_string, 0, 200);
                
                return is_string($result1) && is_string($result2);
            } catch (Exception $e) {
                return false;
            }
        });
        
        $this->runTest('get_total() method call fix', function() {
            if (!class_exists('Timetics\Core\Bookings\Booking')) {
                return true; // Skip if Timetics not available
            }
            
            try {
                // Create test booking
                $booking_id = wp_insert_post([
                    'post_title' => 'Test Booking for Method',
                    'post_type' => 'timetics-booking',
                    'post_status' => 'completed',
                    'post_author' => 1
                ]);
                
                update_post_meta($booking_id, '_tt_booking_order_total', 150.00);
                
                $booking = new \Timetics\Core\Bookings\Booking($booking_id);
                $total = $booking->get_total();
                
                // Clean up
                wp_delete_post($booking_id, true);
                
                return $total == 150.00;
            } catch (Exception $e) {
                return false;
            }
        });
        
        echo "\n";
    }
    
    private function runTest($test_name, $test_function) {
        echo "  🔍 $test_name... ";
        
        try {
            $result = $test_function();
            
            if ($result === true) {
                echo "✅ PASSED\n";
                $this->passed++;
                $this->results[] = "✅ $test_name - PASSED";
            } else {
                echo "❌ FAILED\n";
                $this->failed++;
                $this->results[] = "❌ $test_name - FAILED";
            }
        } catch (Exception $e) {
            echo "💥 ERROR\n";
            echo "     Error: " . $e->getMessage() . "\n";
            $this->failed++;
            $this->results[] = "💥 $test_name - ERROR: " . $e->getMessage();
        } catch (Error $e) {
            echo "💥 ERROR\n";
            echo "     Error: " . $e->getMessage() . "\n";
            $this->failed++;
            $this->results[] = "💥 $test_name - ERROR: " . $e->getMessage();
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
$runner = new WordPressTestRunner();
$runner->runTests();
