#!/bin/bash

# Laravel API Model Relations Package - Compatibility Test Script
# This script tests the package against Laravel 9, 10, and 11

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# Function to print section header
print_header() {
    echo -e "\n${YELLOW}==== $1 ====${NC}\n"
}

# Function to print success message
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print error message
print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Function to test a specific Laravel version
test_laravel_version() {
    local version=$1
    local test_dir="laravel${version}-test"
    
    print_header "Testing with Laravel $version"
    
    # Create a new Laravel project
    echo "Creating Laravel $version project..."
    composer create-project --prefer-dist laravel/laravel $test_dir "^$version.0" || {
        print_error "Failed to create Laravel $version project"
        return 1
    }
    
    # Navigate to the test directory
    cd $test_dir || {
        print_error "Failed to navigate to test directory"
        return 1
    }
    
    # Add the package from local path
    echo "Adding Laravel API Model Relations package..."
    composer config repositories.local path ../
    composer require "api-model-relations/laravel-api-model-relations:@dev" || {
        print_error "Failed to require the package"
        cd ..
        return 1
    }
    
    # Publish the package configuration
    echo "Publishing package configuration..."
    php artisan vendor:publish --provider="MTechStack\LaravelApiModelClient\ApiModelRelationsServiceProvider" || {
        print_error "Failed to publish package configuration"
        cd ..
        return 1
    }
    
    # Create a test model
    echo "Creating test model..."
    mkdir -p app/Models/Api
    cat > app/Models/Api/TestApiModel.php << 'EOL'
<?php

namespace App\Models\Api;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\SyncWithApi;

class TestApiModel extends ApiModel
{
    use SyncWithApi;

    protected $apiEndpoint = 'test-endpoint';
    
    protected $fillable = [
        'id',
        'name',
        'description',
    ];
    
    public function testRelation()
    {
        return $this->hasManyFromApi(TestApiModel::class, 'test_id');
    }
}
EOL
    
    # Create a basic test
    echo "Creating basic test..."
    mkdir -p tests/Feature
    cat > tests/Feature/ApiModelTest.php << 'EOL'
<?php

namespace Tests\Feature;

use App\Models\Api\TestApiModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiModelTest extends TestCase
{
    public function test_api_model_can_be_instantiated()
    {
        $model = new TestApiModel();
        $this->assertInstanceOf(TestApiModel::class, $model);
    }
    
    public function test_api_model_has_api_endpoint()
    {
        $model = new TestApiModel();
        $this->assertEquals('test-endpoint', $model->getApiEndpoint());
    }
    
    public function test_api_model_has_relationships()
    {
        $model = new TestApiModel();
        $relation = $model->testRelation();
        $this->assertNotNull($relation);
    }
}
EOL
    
    # Run the tests
    echo "Running tests..."
    php artisan test tests/Feature/ApiModelTest.php || {
        print_error "Tests failed for Laravel $version"
        cd ..
        return 1
    }
    
    print_success "All tests passed for Laravel $version"
    
    # Return to the parent directory
    cd ..
    return 0
}

# Main script
print_header "Laravel API Model Relations Package Compatibility Test"

# Test Laravel 9
test_laravel_version 9
laravel9_result=$?

# Test Laravel 10
test_laravel_version 10
laravel10_result=$?

# Test Laravel 11
test_laravel_version 11
laravel11_result=$?

# Print summary
print_header "Compatibility Test Summary"

if [ $laravel9_result -eq 0 ]; then
    print_success "Laravel 9: Compatible"
else
    print_error "Laravel 9: Incompatible"
fi

if [ $laravel10_result -eq 0 ]; then
    print_success "Laravel 10: Compatible"
else
    print_error "Laravel 10: Incompatible"
fi

if [ $laravel11_result -eq 0 ]; then
    print_success "Laravel 11: Compatible"
else
    print_error "Laravel 11: Incompatible"
fi

# Exit with success if all tests passed
if [ $laravel9_result -eq 0 ] && [ $laravel10_result -eq 0 ] && [ $laravel11_result -eq 0 ]; then
    print_success "Package is compatible with Laravel 9, 10, and 11"
    exit 0
else
    print_error "Package has compatibility issues with one or more Laravel versions"
    exit 1
fi
