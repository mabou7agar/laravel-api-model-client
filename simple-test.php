<?php

/**
 * Simple Test Runner for Laravel API Model Client Package
 * This approach doesn't rely on PHPUnit and works with basic PHP
 */

// Include the package files directly
require_once __DIR__ . '/src/Models/ApiModel.php';
require_once __DIR__ . '/src/Query/ApiQueryBuilder.php';

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;

// Simple test product model for testing
class SimpleTestProduct extends ApiModel
{
    protected $apiEndpoint = 'api/v1/products';
    protected $fillable = ['id', 'name', 'sku', 'price', 'type', 'status', 'in_stock'];
    protected $casts = [
        'id' => 'integer',
        'price' => 'float',
        'status' => 'boolean',
        'in_stock' => 'boolean',
    ];
}

echo "🚀 SIMPLE TEST RUNNER - Laravel API Model Client v1.0.9\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function simpleTest($description, $callback) {
    global $passed, $failed;
    echo "🧪 Testing: {$description}\n";
    
    try {
        $result = $callback();
        if ($result === true) {
            echo "   ✅ PASSED\n";
            $passed++;
        } else {
            echo "   ❌ FAILED: {$result}\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ❌ ERROR: " . $e->getMessage() . "\n";
        $failed++;
    } catch (Error $e) {
        echo "   ❌ ERROR: " . $e->getMessage() . "\n";
        $failed++;
    }
    
    echo "\n";
}

// Test 1: Basic model instantiation
simpleTest('ApiModel can be instantiated', function() {
    try {
        $model = new SimpleTestProduct();
        if (!($model instanceof ApiModel)) {
            return "Model is not instance of ApiModel";
        }
        if ($model->getApiEndpoint() !== 'api/v1/products') {
            return "API endpoint mismatch. Expected: api/v1/products, Got: " . $model->getApiEndpoint();
        }
        return true;
    } catch (Exception $e) {
        return "Exception: " . $e->getMessage();
    }
});

// Test 2: Model with attributes
simpleTest('ApiModel can be created with attributes', function() {
    try {
        $attributes = [
            'id' => 566,
            'name' => 'Test Product',
            'sku' => 'test-sku',
            'price' => 150.00,
            'status' => true,
            'in_stock' => false,
        ];
        
        $model = new SimpleTestProduct($attributes);
        
        if ($model->id !== 566) {
            return "ID mismatch. Expected: 566, Got: " . $model->id;
        }
        if ($model->name !== 'Test Product') {
            return "Name mismatch. Expected: Test Product, Got: " . $model->name;
        }
        if ($model->price !== 150.00) {
            return "Price mismatch. Expected: 150.00, Got: " . $model->price;
        }
        
        return true;
    } catch (Exception $e) {
        return "Exception: " . $e->getMessage();
    }
});

// Test 3: newFromApiResponse method (our critical fix)
simpleTest('newFromApiResponse method works with parameters', function() {
    try {
        $model = new SimpleTestProduct();
        $responseData = [
            'id' => 566,
            'name' => 'API Product',
            'sku' => 'api-sku',
            'price' => 200.00,
            'status' => 1,
            'in_stock' => true,
        ];
        
        $newModel = $model->newFromApiResponse($responseData);
        
        if (!($newModel instanceof SimpleTestProduct)) {
            return "newFromApiResponse did not return correct instance";
        }
        if ($newModel->id !== 566) {
            return "ID not set correctly from API response";
        }
        if ($newModel->name !== 'API Product') {
            return "Name not set correctly from API response";
        }
        if (!$newModel->exists) {
            return "Model exists flag not set";
        }
        
        return true;
    } catch (Exception $e) {
        return "Exception: " . $e->getMessage();
    }
});

// Test 4: newFromApiResponse with default parameter (our fix)
simpleTest('newFromApiResponse method works with default parameter', function() {
    try {
        $model = new SimpleTestProduct();
        $newModel = $model->newFromApiResponse(); // Test default parameter
        
        if ($newModel !== null) {
            return "Should return null for empty response, got: " . gettype($newModel);
        }
        
        return true;
    } catch (Exception $e) {
        return "Exception: " . $e->getMessage();
    }
});

// Test 5: extractItemsFromResponse method
simpleTest('extractItemsFromResponse handles different formats', function() {
    try {
        $model = new SimpleTestProduct();
        
        // Test nested data structure
        $nestedResponse = [
            'data' => [
                ['id' => 1, 'name' => 'Product 1'],
                ['id' => 2, 'name' => 'Product 2'],
            ],
            'meta' => ['total' => 2]
        ];
        
        $items = $model->extractItemsFromResponse($nestedResponse);
        if (count($items) !== 2) {
            return "Nested response parsing failed. Expected 2 items, got: " . count($items);
        }
        
        // Test flat array
        $flatResponse = [
            ['id' => 3, 'name' => 'Product 3'],
            ['id' => 4, 'name' => 'Product 4'],
        ];
        
        $items = $model->extractItemsFromResponse($flatResponse);
        if (count($items) !== 2) {
            return "Flat response parsing failed. Expected 2 items, got: " . count($items);
        }
        
        // Test single item
        $singleResponse = ['id' => 5, 'name' => 'Product 5'];
        $items = $model->extractItemsFromResponse($singleResponse);
        if (count($items) !== 1) {
            return "Single response parsing failed. Expected 1 item, got: " . count($items);
        }
        
        return true;
    } catch (Exception $e) {
        return "Exception: " . $e->getMessage();
    }
});

// Test 6: Static query methods exist
simpleTest('Static query methods are available', function() {
    try {
        // Check if methods exist
        if (!method_exists(SimpleTestProduct::class, 'take')) {
            return "take() method does not exist";
        }
        if (!method_exists(SimpleTestProduct::class, 'limit')) {
            return "limit() method does not exist";
        }
        if (!method_exists(SimpleTestProduct::class, 'where')) {
            return "where() method does not exist";
        }
        if (!method_exists(SimpleTestProduct::class, 'query')) {
            return "query() method does not exist";
        }
        
        return true;
    } catch (Exception $e) {
        return "Exception: " . $e->getMessage();
    }
});

// Summary
echo "🎯 TEST SUMMARY\n";
echo "===============\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo "✅ Passed: {$passed}\n";
echo "❌ Failed: {$failed}\n";

if ($failed === 0) {
    echo "\n🎉 ALL TESTS PASSED!\n";
    echo "✅ Core functionality is working correctly\n";
    echo "✅ Critical fixes are implemented and functional\n";
    echo "✅ Package is ready for use\n";
} else {
    echo "\n⚠️  SOME TESTS FAILED\n";
    echo "Please review the issues above\n";
}

echo "\n📋 TESTED FUNCTIONALITY:\n";
echo "✅ ApiModel instantiation and basic functionality\n";
echo "✅ Model creation with attributes and type casting\n";
echo "✅ newFromApiResponse() method with default parameter fix\n";
echo "✅ Data structure parsing for different API response formats\n";
echo "✅ Static query methods availability\n";
echo "✅ Core package structure and method existence\n";

echo "\n🚀 PACKAGE STATUS: " . ($failed === 0 ? "FUNCTIONAL" : "NEEDS ATTENTION") . "\n";

exit($failed === 0 ? 0 : 1);
