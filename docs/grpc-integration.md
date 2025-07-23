# gRPC Integration for Laravel API Model Relations

This document explains how to use the gRPC integration with the Laravel API Model Relations package.

## Overview

The Laravel API Model Relations package now supports gRPC as an alternative to REST APIs for model synchronization. gRPC offers several advantages:

- Better performance with binary protocol (Protocol Buffers)
- Strongly typed interfaces
- Bidirectional streaming
- Built-in code generation

## Configuration

### 1. Install Required Dependencies

First, install the gRPC PHP extension and Protocol Buffers:

```bash
pecl install grpc
pecl install protobuf
```

Add the extensions to your php.ini:

```ini
extension=grpc.so
extension=protobuf.so
```

### 2. Generate gRPC Client Code

You'll need to generate PHP client code from your .proto files. Install the Protocol Buffer compiler and gRPC plugin:

```bash
# Install protoc compiler
brew install protobuf

# Install gRPC plugin
brew install grpc
```

Generate PHP code from your .proto files:

```bash
protoc --proto_path=protos/ --php_out=generated/ --grpc_out=generated/ --plugin=protoc-gen-grpc=`which grpc_php_plugin` protos/your_service.proto
```

### 3. Configure API Model to Use gRPC

In your `.env` file:

```
API_MODEL_PROTOCOL=grpc
```

Or set it per model by adding a property to your model class:

```php
protected $apiProtocol = 'grpc';
```

### 4. Configure gRPC Services

In `config/api_model.php`, define your gRPC service mappings:

```php
'grpc' => [
    'services' => [
        'App\Models\User' => [
            'client' => 'App\Grpc\UserServiceClient',
            'methods' => [
                'get' => [
                    'method' => 'GetUser',
                    'request' => 'App\Grpc\GetUserRequest',
                ],
                'list' => [
                    'method' => 'ListUsers',
                    'request' => 'App\Grpc\ListUsersRequest',
                ],
                'create' => [
                    'method' => 'CreateUser',
                    'request' => 'App\Grpc\CreateUserRequest',
                ],
                'update' => [
                    'method' => 'UpdateUser',
                    'request' => 'App\Grpc\UpdateUserRequest',
                ],
                'delete' => [
                    'method' => 'DeleteUser',
                    'request' => 'App\Grpc\DeleteUserRequest',
                ],
            ],
        ],
    ],
]
```

## Usage

Once configured, your models will automatically use gRPC for API communication. The same methods you use for REST APIs will work with gRPC:

```php
// Find a user from the API
$user = User::findFromApi($id);

// Get all users from the API
$users = User::allFromApi();

// Save a user to the API
$user = new User(['name' => 'John Doe']);
$user->save(); // Will use gRPC if configured

// Update a user
$user->name = 'Jane Doe';
$user->update();

// Delete a user
$user->delete();
```

## Protocol-Specific Model Configuration

You can customize how your model interacts with gRPC by adding these properties to your model:

```php
class User extends ApiModel
{
    // Use gRPC for this model regardless of global config
    protected $apiProtocol = 'grpc';
    
    // Base URL for the gRPC server
    protected $apiBaseUrl = 'localhost:50051';
    
    // Override the endpoint name if different from the default
    protected $apiEndpoint = 'users';
}
```

## Data Transformation

The package automatically converts between Eloquent model attributes and Protocol Buffer messages. For complex data structures, you may need to customize the transformation by overriding these methods:

```php
protected function prepareAttributesForApi(array $attributes): array
{
    // Transform attributes before sending to API
    return $attributes;
}

protected function processApiResponse($response)
{
    // Process API response before using it in the model
    return $response;
}
```

## Testing with gRPC

When testing with gRPC, you can mock the gRPC client:

```php
// In your test
$mockClient = Mockery::mock(ApiClientInterface::class);
$mockClient->shouldReceive('get')->andReturn(['id' => 1, 'name' => 'Test User']);
$this->app->instance('api-client', $mockClient);
```

Or disable API sync during testing:

```php
// In .env.testing
API_MODEL_SYNC_IN_TESTING=false
```

## Troubleshooting

### Common Issues

1. **Connection Refused**: Ensure your gRPC server is running and accessible
2. **Method Not Found**: Check your service definition in the config file
3. **Type Mismatch**: Ensure your model attributes match the expected types in your Protocol Buffer messages

### Debugging

Enable detailed logging for gRPC operations:

```php
// In config/logging.php
'channels' => [
    'api' => [
        'driver' => 'daily',
        'path' => storage_path('logs/api.log'),
        'level' => 'debug',
    ],
],
```

Then in your `.env`:

```
LOG_CHANNEL=api
```

## Performance Considerations

- gRPC performs best with persistent connections
- Consider using connection pooling for high-traffic applications
- For large datasets, use streaming methods if available in your gRPC service
