#!/bin/bash

# Laravel API Model Client - Comprehensive Test Runner
# This script runs all available tests for the package

echo "🚀 Laravel API Model Client - Test Suite Runner"
echo "================================================"

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "❌ Error: Please run this script from the package root directory"
    exit 1
fi

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "📦 Installing dependencies..."
    composer install --no-dev --optimize-autoloader
fi

echo ""
echo "🧪 Running Comprehensive Test Suite..."
echo "======================================="

# Run the comprehensive test
php comprehensive-test.php

COMPREHENSIVE_EXIT_CODE=$?

echo ""
echo "🔍 Running PHPUnit Tests..."
echo "============================"

# Run PHPUnit tests if they exist
if [ -f "vendor/bin/phpunit" ]; then
    vendor/bin/phpunit --testdox
    PHPUNIT_EXIT_CODE=$?
else
    echo "⚠️  PHPUnit not found, skipping unit tests"
    PHPUNIT_EXIT_CODE=0
fi

echo ""
echo "🏃‍♂️ Running Simple Test Runner..."
echo "=================================="

# Run the existing simple test
php run-tests.php

SIMPLE_EXIT_CODE=$?

echo ""
echo "📋 Final Test Summary"
echo "===================="

if [ $COMPREHENSIVE_EXIT_CODE -eq 0 ] && [ $PHPUNIT_EXIT_CODE -eq 0 ] && [ $SIMPLE_EXIT_CODE -eq 0 ]; then
    echo "🎉 ALL TESTS PASSED!"
    echo "✅ Package is ready for publication"
    echo ""
    echo "📦 Next Steps:"
    echo "1. Commit all changes to Git"
    echo "2. Create a release tag: git tag v1.0.11"
    echo "3. Push to GitHub: git push origin v1.0.11"
    echo "4. Submit to Packagist.org"
    exit 0
else
    echo "❌ SOME TESTS FAILED"
    echo "⚠️  Please fix issues before publishing"
    
    if [ $COMPREHENSIVE_EXIT_CODE -ne 0 ]; then
        echo "   - Comprehensive tests failed"
    fi
    
    if [ $PHPUNIT_EXIT_CODE -ne 0 ]; then
        echo "   - PHPUnit tests failed"
    fi
    
    if [ $SIMPLE_EXIT_CODE -ne 0 ]; then
        echo "   - Simple tests failed"
    fi
    
    exit 1
fi
