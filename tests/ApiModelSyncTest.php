<?php

namespace MTechStack\LaravelApiModelClient\Tests;

use MTechStack\LaravelApiModelClient\Jobs\SyncModelToApi;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\ApiModelInterfaceMethods;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Orchestra\Testbench\TestCase;

class ApiModelSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test table
        DB::statement('CREATE TABLE IF NOT EXISTS test_models (
            id INTEGER PRIMARY KEY,
            name VARCHAR(255),
            email VARCHAR(255),
            password VARCHAR(255),
            api_id VARCHAR(255),
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            // Add your service provider here if needed
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        
        // API model config
        $app['config']->set('api_model.queue_operations', false);
        $app['config']->set('api_model.retry_attempts', 2);
        $app['config']->set('api_model.sync_in_testing', true);
    }

    /**
     * Test successful save to both database and API.
     */
    public function testSuccessfulSaveToDbAndApi()
    {
        // Mock API client
        $apiClient = Mockery::mock('ApiClient');
        $apiClient->shouldReceive('post')
            ->once()
            ->with('test-models', Mockery::subset(['name' => 'Test Model', 'email' => 'test@example.com']))
            ->andReturn(['id' => 123, 'name' => 'Test Model', 'email' => 'test@example.com']);
        
        // Bind API client to container
        $this->app->instance('api-client', $apiClient);
        
        // Create and save model
        $model = new TestModel();
        $model->name = 'Test Model';
        $model->email = 'test@example.com';
        $model->password = 'secret'; // Should not be sent to API
        
        $result = $model->save();
        
        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('test_models', [
            'name' => 'Test Model',
            'email' => 'test@example.com',
            'password' => 'secret'
        ]);
    }

    /**
     * Test API failure with database rollback.
     */
    public function testApiFailureWithRollback()
    {
        // Mock API client
        $apiClient = Mockery::mock('ApiClient');
        $apiClient->shouldReceive('post')
            ->once()
            ->with('test-models', Mockery::any())
            ->andThrow(new \Exception('API Error'));
        
        // Bind API client to container
        $this->app->instance('api-client', $apiClient);
        
        // Create and save model
        $model = new TestModel();
        $model->name = 'Test Model';
        $model->email = 'test@example.com';
        
        $result = $model->save();
        
        // Assert
        $this->assertFalse($result);
        $this->assertDatabaseMissing('test_models', [
            'name' => 'Test Model'
        ]);
        $this->assertEquals('API Error', $model->getLastApiError());
    }

    /**
     * Test queued API operations.
     */
    public function testQueuedApiOperations()
    {
        // Enable queue operations
        config(['api_model.queue_operations' => true]);
        
        // Fake queue
        Queue::fake();
        
        // Create and save model
        $model = new TestModel();
        $model->name = 'Test Model';
        $model->email = 'test@example.com';
        
        $result = $model->save();
        
        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('test_models', [
            'name' => 'Test Model'
        ]);
        
        // Assert job was pushed to queue
        Queue::assertPushed(SyncModelToApi::class, function ($job) use ($model) {
            return $job->model->id === $model->id && $job->operation === 'save';
        });
    }

    /**
     * Test retry logic for API operations.
     */
    public function testRetryLogicForApiOperations()
    {
        // Mock API client
        $apiClient = Mockery::mock('ApiClient');
        $apiClient->shouldReceive('post')
            ->times(2) // Should be called twice (initial + 1 retry)
            ->with('test-models', Mockery::any())
            ->andThrow(new \Exception('API Error'));
        
        // Bind API client to container
        $this->app->instance('api-client', $apiClient);
        
        // Create model
        $model = new TestModel();
        $model->name = 'Test Model';
        
        // Call saveToApi directly to test retry logic
        $result = $model->saveToApi();
        
        // Assert
        $this->assertFalse($result);
        $this->assertEquals('API Error', $model->getLastApiError());
    }

    /**
     * Test environment-specific sync behavior.
     */
    public function testEnvironmentSpecificSyncBehavior()
    {
        // Disable API sync in testing
        config(['api_model.sync_in_testing' => false]);
        
        // Mock API client - should not be called
        $apiClient = Mockery::mock('ApiClient');
        $apiClient->shouldNotReceive('post');
        
        // Bind API client to container
        $this->app->instance('api-client', $apiClient);
        
        // Create and save model
        $model = new TestModel();
        $model->name = 'Test Model';
        
        $result = $model->save();
        
        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('test_models', [
            'name' => 'Test Model'
        ]);
    }

    /**
     * Test granular attribute control.
     */
    public function testGranularAttributeControl()
    {
        // Mock API client
        $apiClient = Mockery::mock('ApiClient');
        $apiClient->shouldReceive('post')
            ->once()
            ->with('test-models', ['name' => 'Test Model']) // Only name should be sent for create
            ->andReturn(['id' => 123, 'name' => 'Test Model']);
        
        $apiClient->shouldReceive('put')
            ->once()
            ->with('test-models/123', ['email' => 'updated@example.com']) // Only email should be sent for update
            ->andReturn(['id' => 123, 'name' => 'Test Model', 'email' => 'updated@example.com']);
        
        // Bind API client to container
        $this->app->instance('api-client', $apiClient);
        
        // Create model with custom attribute control
        $model = new TestModelWithCustomAttributes();
        $model->name = 'Test Model';
        $model->email = 'test@example.com';
        
        // Save (create)
        $model->save();
        
        // Update
        $model->email = 'updated@example.com';
        $model->update(['email' => 'updated@example.com']);
    }

    /**
     * Test delete with API sync.
     */
    public function testDeleteWithApiSync()
    {
        // Mock API client for create
        $apiClient = Mockery::mock('ApiClient');
        $apiClient->shouldReceive('post')
            ->once()
            ->andReturn(['id' => 123, 'name' => 'Test Model']);
        
        $apiClient->shouldReceive('delete')
            ->once()
            ->with('test-models/123')
            ->andReturn(true);
        
        // Bind API client to container
        $this->app->instance('api-client', $apiClient);
        
        // Create model
        $model = new TestModel();
        $model->name = 'Test Model';
        $model->save();
        
        // Delete model
        $result = $model->delete();
        
        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('test_models', [
            'id' => $model->id
        ]);
    }

    /**
     * Test API delete failure with rollback.
     */
    public function testApiDeleteFailureWithRollback()
    {
        // Mock API client for create
        $apiClient = Mockery::mock('ApiClient');
        $apiClient->shouldReceive('post')
            ->once()
            ->andReturn(['id' => 123, 'name' => 'Test Model']);
        
        $apiClient->shouldReceive('delete')
            ->times(config('api_model.retry_attempts'))
            ->with('test-models/123')
            ->andThrow(new \Exception('API Delete Error'));
        
        // Bind API client to container
        $this->app->instance('api-client', $apiClient);
        
        // Create model
        $model = new TestModel();
        $model->name = 'Test Model';
        $model->save();
        
        // Get ID for later assertion
        $modelId = $model->id;
        
        // Delete model
        $result = $model->delete();
        
        // Assert
        $this->assertFalse($result);
        $this->assertDatabaseHas('test_models', [
            'id' => $modelId
        ]);
        $this->assertEquals('API Delete Error', $model->getLastApiError());
    }
}

/**
 * Test model class for API sync tests.
 */
class TestModel extends Model implements \ApiModelRelations\Contracts\ApiModelInterface
{
    use ApiModelInterfaceMethods;
    
    protected $table = 'test_models';
    protected $fillable = ['name', 'email', 'password', 'api_id'];
    public $timestamps = true;
    protected $apiEndpoint = 'test-models';
    
    public function getApiClient()
    {
        return app('api-client');
    }
    
    public function getDbOnlyAttributes()
    {
        return ['password'];
    }
}

/**
 * Test model with custom attribute control.
 */
class TestModelWithCustomAttributes extends TestModel
{
    public function getCreateApiAttributes()
    {
        return ['name']; // Only send name when creating
    }
    
    public function getUpdateApiAttributes()
    {
        return ['email']; // Only send email when updating
    }
}
