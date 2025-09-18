#!/bin/bash

# WordPress Native Test Runner
# Usage: ./run-wp-tests.sh

echo "🧪 Timetics PDF Addon Test Suite (WordPress Native)"
echo "=================================================="
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

# Check if we're in a WordPress environment
if [ ! -f "../../../wp-load.php" ]; then
    echo "❌ Error: WordPress installation not found"
    echo "   Expected: ../../../wp-load.php"
    echo "   Current directory: $(pwd)"
    exit 1
fi

echo "✅ WordPress installation found"
echo "✅ Running tests in WordPress environment..."
echo ""

# Run the WordPress native tests
php tests/run-wp-tests.php

# Capture exit code
EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "🎉 All tests passed!"
else
    echo "💥 Some tests failed!"
fi

exit $EXIT_CODE
