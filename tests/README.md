# Timetics PDF Addon Test Suite

This directory contains comprehensive automated tests for the Timetics PDF Addon plugin, testing its integration with WordPress, Timetics core, and Timetics Pro.

## Test Structure

```
tests/
├── bootstrap.php                    # Test environment setup
├── class-test-base.php             # Base test class with common functionality
├── run-tests.php                   # Simple test runner
├── README.md                       # This file
├── unit/                           # Unit tests
│   ├── test-wordpress-integration.php
│   ├── test-timetics-integration.php
│   └── test-timetics-pro-integration.php
├── integration/                    # Integration tests
│   └── test-pdf-generation.php
└── fixtures/                       # Test data fixtures
```

## Test Categories

### 1. WordPress Integration Tests (`test-wordpress-integration.php`)
- Plugin activation and initialization
- WordPress hooks registration
- Post meta and user meta handling
- Core WordPress function availability
- Error handling

### 2. Timetics Integration Tests (`test-timetics-integration.php`)
- Timetics core class availability
- Booking, Appointment, Customer, and Staff object methods
- Data extraction from Timetics objects
- Custom form data handling
- Booking status and payment status handling
- Error handling for invalid data

### 3. Timetics Pro Integration Tests (`test-timetics-pro-integration.php`)
- Timetics Pro class availability (if installed)
- Event and Event_Booking object methods
- Pro-specific features detection
- Booking type detection (appointment vs event)
- Pro pricing and capacity handling

### 4. PDF Generation Integration Tests (`test-pdf-generation.php`)
- PDF addon initialization
- Booking data extraction
- Medical information extraction
- Service information extraction
- PDF HTML generation
- Email attachment detection
- Booking ID extraction from emails
- Error handling and edge cases

## Running Tests

### Prerequisites

1. **WordPress Test Environment**: You need a WordPress test environment set up
   ```bash
   # Set the WordPress test directory
   export WP_TESTS_DIR=/path/to/wordpress-tests-lib
   ```

2. **Required Plugins**: The tests expect these plugins to be available:
   - Timetics core plugin
   - Timetics Pro plugin (optional, tests will skip if not available)

### Running the Test Suite

```bash
# From the plugin directory
cd /path/to/timetics-pdf-addon
php tests/run-tests.php
```

### Expected Output

```
🧪 Timetics PDF Addon Test Suite
================================

🚀 Starting tests...

📁 Running Test_Wordpress_Integration...
  🔍 test_plugin_activation... ✅ PASSED
  🔍 test_hooks_registration... ✅ PASSED
  🔍 test_post_meta_handling... ✅ PASSED
  ...

📁 Running Test_Timetics_Integration...
  🔍 test_timetics_classes_exist... ✅ PASSED
  🔍 test_booking_class_methods... ✅ PASSED
  ...

📊 Test Summary
===============
✅ Passed: 25
❌ Failed: 0
⏭️  Skipped: 3
📈 Total: 28

✅ All tests completed!
```

## Test Data

The test suite creates and manages its own test data:

- **Test Customer**: User with customer role
- **Test Staff**: User with administrator role  
- **Test Appointment**: Timetics appointment post
- **Test Booking**: Timetics booking post with complete meta data
- **Custom Form Data**: Medical information and customer details

All test data is automatically cleaned up after each test.

## Key Test Scenarios

### Critical Error Prevention
- Tests for the `get_total_price()` → `get_total()` method fix
- Tests for `substr()` array handling fixes
- Tests for missing method error handling

### Data Extraction
- Tests extraction of booking data from Timetics objects
- Tests extraction of medical information from custom form data
- Tests extraction of service information

### Integration Points
- Tests WordPress core function availability
- Tests Timetics plugin class availability
- Tests Timetics Pro plugin integration (if available)

### Error Handling
- Tests handling of invalid booking IDs
- Tests handling of missing data
- Tests handling of array vs string data types

## Adding New Tests

1. **Create a new test file** in the appropriate directory (`unit/` or `integration/`)
2. **Extend the `Test_Base` class** for common functionality
3. **Use descriptive test method names** starting with `test_`
4. **Add proper assertions** to verify expected behavior
5. **Clean up test data** in the `tearDown()` method

### Example Test Method

```php
public function test_new_feature() {
    // Arrange
    $test_data = 'test_value';
    
    // Act
    $result = $this->pdf_addon->new_method($test_data);
    
    // Assert
    $this->assertEquals('expected_value', $result, 'Should return expected value');
}
```

## Continuous Integration

These tests are designed to be run in CI/CD pipelines:

```bash
# In CI environment
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
php tests/run-tests.php
```

The test runner exits with code 0 on success and code 1 on failure, making it suitable for automated testing.

## Troubleshooting

### Common Issues

1. **WordPress test environment not found**
   - Set `WP_TESTS_DIR` environment variable
   - Install WordPress test suite

2. **Timetics classes not found**
   - Ensure Timetics core plugin is installed
   - Check plugin activation

3. **Database errors**
   - Ensure test database is properly configured
   - Check WordPress test environment setup

### Debug Mode

To enable debug output, add this to your test:

```php
public function test_debug_example() {
    $this->pdf_addon->log_info('Debug message');
    // Test code here
}
```

Debug messages will be logged to the WordPress error log.
