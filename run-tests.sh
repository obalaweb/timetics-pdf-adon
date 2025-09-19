#!/bin/bash

# Timetics PDF Addon Test Runner
# Usage: ./run-tests.sh

echo "🧪 Timetics PDF Addon Test Suite"
echo "================================="
echo ""

# Check if we're in the right directory
if [ ! -f "timetics-pdf-addon.php" ]; then
    echo "❌ Error: Please run this script from the plugin root directory"
    exit 1
fi

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "❌ Error: PHP is not installed or not in PATH"
    exit 1
fi

# Check if WordPress test environment is set
if [ -z "$WP_TESTS_DIR" ]; then
    echo "⚠️  Warning: WP_TESTS_DIR environment variable not set"
    echo "   Using default: /tmp/wordpress-tests-lib"
    export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
fi

# Check if WordPress test environment exists
if [ ! -f "$WP_TESTS_DIR/includes/functions.php" ]; then
    echo "❌ Error: WordPress test environment not found at: $WP_TESTS_DIR"
    echo ""
    echo "To set up WordPress tests:"
    echo "1. Install WordPress test suite"
    echo "2. Set WP_TESTS_DIR environment variable"
    echo "3. Run this script again"
    echo ""
    echo "Example:"
    echo "  export WP_TESTS_DIR=/path/to/wordpress-tests-lib"
    echo "  ./run-tests.sh"
    exit 1
fi

echo "✅ WordPress test environment found at: $WP_TESTS_DIR"
echo ""

# Run the tests
echo "🚀 Running tests..."
echo ""

php tests/run-tests.php

# Capture exit code
EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "🎉 All tests passed!"
else
    echo "💥 Some tests failed!"
fi

exit $EXIT_CODE
