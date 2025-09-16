<?php

require_once 'vendor/autoload.php';

// Simple test to check for trait conflicts
try {
    echo "Testing trait conflicts in ApiModel...\n";
    
    // Try to load the ApiModel class
    $reflection = new ReflectionClass('MTechStack\LaravelApiModelClient\Models\ApiModel');
    echo "✅ ApiModel class loaded successfully\n";
    
    // Check for syncToApi method
    if ($reflection->hasMethod('syncToApi')) {
        echo "✅ syncToApi method found\n";
        $method = $reflection->getMethod('syncToApi');
        echo "   - Method is " . ($method->isPublic() ? 'public' : 'protected/private') . "\n";
    } else {
        echo "❌ syncToApi method not found\n";
    }
    
    // Try to instantiate (this will show trait conflicts if they exist)
    echo "Attempting to instantiate ApiModel...\n";
    $model = new MTechStack\LaravelApiModelClient\Models\ApiModel();
    echo "✅ ApiModel instantiated successfully - no trait conflicts!\n";
    
} catch (Error $e) {
    echo "❌ TRAIT CONFLICT ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "❌ OTHER ERROR: " . $e->getMessage() . "\n";
}
