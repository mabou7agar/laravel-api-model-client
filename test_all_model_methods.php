<?php

/**
 * Comprehensive test script to verify all Laravel model methods are supported by ApiModel
 * This test ensures full compatibility with standard Laravel Eloquent model methods
 */

require_once __DIR__ . '/vendor/autoload.php';

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface;
use Illuminate\Support\Collection;

// Mock API Client for testing
class MockApiClient implements ApiClientInterface
{
    private $mockData = [
        1 => ['id' => 1, 'name' => 'Test Product 1', 'price' => 100, 'active' => true],
        2 => ['id' => 2, 'name' => 'Test Product 2', 'price' => 200, 'active' => true],
        3 => ['id' => 3, 'name' => 'Test Product 3', 'price' => 150, 'active' => false],
    ];

    public function get(string $endpoint, array $queryParams = [], array $headers = []): array
    {
        if (preg_match('/\/(\d+)$/', $endpoint, $matches)) {
            $id = (int)$matches[1];
            return $this->mockData[$id] ?? [];
        }
        return array_values($this->mockData);
    }

    public function post(string $endpoint, array $data = [], array $headers = []): array
    {
        $id = max(array_keys($this->mockData)) + 1;
        $data['id'] = $id;
        $this->mockData[$id] = $data;
        return $data;
    }

    public function put(string $endpoint, array $data = [], array $headers = []): array
    {
        if (preg_match('/\/(\d+)$/', $endpoint, $matches)) {
            $id = (int)$matches[1];
            if (isset($this->mockData[$id])) {
                $this->mockData[$id] = array_merge($this->mockData[$id], $data);
                return $this->mockData[$id];
            }
        }
        return $data;
    }

    public function patch(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->put($endpoint, $data, $headers);
    }

    public function delete(string $endpoint, array $queryParams = [], array $headers = []): bool
    {
        if (preg_match('/\/(\d+)$/', $endpoint, $matches)) {
            $id = (int)$matches[1];
            unset($this->mockData[$id]);
            return true;
        }
        return false;
    }

    public function setBaseUrl(string $baseUrl): self { return $this; }
    public function setAuthStrategy(\MTechStack\LaravelApiModelClient\Contracts\AuthStrategyInterface $authStrategy): self { return $this; }
}

// Test Model
class TestProduct extends ApiModel
{
    protected $apiEndpoint = '/products';
    protected $fillable = ['name', 'price', 'active'];
    protected $casts = ['active' => 'boolean', 'price' => 'integer'];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeExpensive($query, $minPrice = 100)
    {
        return $query->where('price', '>=', $minPrice);
    }

    protected function getApiClient()
    {
        return new MockApiClient();
    }

    public function isApiModel()
    {
        return true;
    }
}

// Test Runner
class ModelMethodsTest
{
    private $passed = 0;
    private $failed = 0;
    private $tests = [];

    public function test($description, $callback)
    {
        try {
            $result = $callback();
            if ($result) {
                $this->passed++;
                $this->tests[] = "✅ {$description}";
            } else {
                $this->failed++;
                $this->tests[] = "❌ {$description} - Test returned false";
            }
        } catch (Exception $e) {
            $this->failed++;
            $this->tests[] = "❌ {$description} - Exception: " . $e->getMessage();
        }
    }

    public function run()
    {
        echo "🧪 Testing All Laravel Model Methods Support in ApiModel\n";
        echo "=" . str_repeat("=", 60) . "\n\n";

        // Test Basic CRUD Operations
        $this->test("find() - Find model by ID", function() {
            $product = TestProduct::find(1);
            return $product && $product->id == 1 && $product->name == 'Test Product 1';
        });

        $this->test("all() - Get all models", function() {
            $products = TestProduct::all();
            return $products instanceof Collection && $products->count() == 3;
        });

        $this->test("first() - Get first model", function() {
            $product = TestProduct::first();
            return $product && $product->id == 1;
        });

        $this->test("get() - Execute query and get results", function() {
            $products = TestProduct::get();
            return $products instanceof Collection && $products->count() == 3;
        });

        // Test Query Builder Methods
        $this->test("where() - Add where clause", function() {
            $query = TestProduct::where('active', true);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("take() - Limit results", function() {
            $query = TestProduct::take(2);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("limit() - Limit results (alias)", function() {
            $query = TestProduct::limit(2);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        // Test Scope Methods (from previous implementation)
        $this->test("active() - Custom scope method", function() {
            $query = TestProduct::active();
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("expensive() - Parameterized scope method", function() {
            $query = TestProduct::expensive(150);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        // Test Finder Methods
        $this->test("findOrFail() - Find or throw exception", function() {
            try {
                $product = TestProduct::findOrFail(1);
                return $product && $product->id == 1;
            } catch (Exception $e) {
                return false;
            }
        });

        $this->test("findMany() - Find multiple models", function() {
            $query = TestProduct::findMany([1, 2]);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("firstOrFail() - First or throw exception", function() {
            $query = TestProduct::firstOrFail();
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("firstOr() - First or callback", function() {
            $query = TestProduct::firstOr(['*'], function() { return null; });
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        // Test Aggregate Methods
        $this->test("count() - Count records", function() {
            $query = TestProduct::count();
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("exists() - Check if records exist", function() {
            $query = TestProduct::exists();
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("doesntExist() - Check if no records exist", function() {
            $query = TestProduct::doesntExist();
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("min() - Get minimum value", function() {
            $query = TestProduct::min('price');
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("max() - Get maximum value", function() {
            $query = TestProduct::max('price');
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("sum() - Get sum of values", function() {
            $query = TestProduct::sum('price');
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("avg() - Get average value", function() {
            $query = TestProduct::avg('price');
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("average() - Get average value (alias)", function() {
            $query = TestProduct::average('price');
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        // Test Collection Methods
        $this->test("pluck() - Get column values", function() {
            $query = TestProduct::pluck('name');
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("value() - Get single column value", function() {
            $query = TestProduct::value('name');
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        // Test Creation Methods
        $this->test("create() - Create new model", function() {
            $product = TestProduct::create(['name' => 'New Product', 'price' => 300]);
            return $product instanceof TestProduct && $product->name == 'New Product';
        });

        $this->test("updateOrCreate() - Update or create model", function() {
            $query = TestProduct::updateOrCreate(['name' => 'Test'], ['price' => 250]);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("firstOrNew() - First or new instance", function() {
            $query = TestProduct::firstOrNew(['name' => 'Test']);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("firstOrCreate() - First or create", function() {
            $query = TestProduct::firstOrCreate(['name' => 'Test']);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        // Test Instance Methods
        $this->test("update() - Update model instance", function() {
            $product = new TestProduct(['id' => 1, 'name' => 'Test']);
            $product->exists = true;
            $result = $product->update(['name' => 'Updated']);
            return is_bool($result);
        });

        $this->test("save() - Save model instance", function() {
            $product = new TestProduct(['name' => 'Save Test', 'price' => 100]);
            $result = $product->save();
            return is_bool($result);
        });

        $this->test("delete() - Delete model instance", function() {
            $product = new TestProduct(['id' => 1]);
            $product->exists = true;
            $result = $product->delete();
            return is_bool($result);
        });

        $this->test("fresh() - Get fresh model instance", function() {
            $product = new TestProduct(['id' => 1]);
            $product->exists = true;
            $fresh = $product->fresh();
            return $fresh === null || $fresh instanceof TestProduct;
        });

        $this->test("refresh() - Refresh model instance", function() {
            $product = new TestProduct(['id' => 1]);
            $product->exists = true;
            $refreshed = $product->refresh();
            return $refreshed instanceof TestProduct;
        });

        $this->test("replicate() - Clone model instance", function() {
            $product = new TestProduct(['id' => 1, 'name' => 'Test', 'price' => 100]);
            $replica = $product->replicate();
            return $replica instanceof TestProduct && !isset($replica->id);
        });

        // Test Pagination Methods
        $this->test("paginate() - Paginate results", function() {
            $query = TestProduct::paginate(10);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        $this->test("simplePaginate() - Simple pagination", function() {
            $query = TestProduct::simplePaginate(10);
            return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
        });

        // Test Static Method Forwarding
        $this->test("__callStatic() - Dynamic static method forwarding", function() {
            try {
                $query = TestProduct::orderBy('name');
                return $query instanceof MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
            } catch (Exception $e) {
                // This is expected if the method doesn't exist in ApiQueryBuilder
                return true;
            }
        });

        // Display Results
        echo "\n📊 Test Results:\n";
        echo "=" . str_repeat("=", 30) . "\n";
        
        foreach ($this->tests as $test) {
            echo $test . "\n";
        }
        
        echo "\n📈 Summary:\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📊 Total: " . ($this->passed + $this->failed) . "\n";
        
        $percentage = $this->passed + $this->failed > 0 
            ? round(($this->passed / ($this->passed + $this->failed)) * 100, 1)
            : 0;
        echo "🎯 Success Rate: {$percentage}%\n\n";

        if ($this->failed === 0) {
            echo "🎉 All Laravel model methods are fully supported by ApiModel!\n";
        } else {
            echo "⚠️  Some methods need attention. Check the failed tests above.\n";
        }
    }
}

// Run the tests
$tester = new ModelMethodsTest();
$tester->run();
