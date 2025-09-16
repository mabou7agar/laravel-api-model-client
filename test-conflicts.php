<?php

require_once 'vendor/autoload.php';

// Simple test to see the actual trait conflicts
try {
    echo "Testing trait conflicts in ApiModel...\n";
    
    // This should show the exact trait conflict errors
    class TestApiModel extends \MTechStack\LaravelApiModelClient\Models\ApiModel {
        protected $table = 'test_table';
    }
    
    echo "✅ No trait conflicts found\n";
    
} catch (Error $e) {
    echo "❌ TRAIT CONFLICT ERROR:\n";
    echo $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "❌ OTHER ERROR: " . $e->getMessage() . "\n";
}
