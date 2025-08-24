# Laravel API Model Relations with gRPC Support

This package extends Laravel Eloquent models to synchronize with external APIs using both REST and gRPC protocols.

## 🚀 Why gRPC?

gRPC offers significant advantages over traditional REST APIs:

- **Performance**: Uses Protocol Buffers (binary format) instead of JSON for more efficient data transfer
- **Strong Typing**: Contract-first approach with .proto files ensures type safety
- **Bidirectional Streaming**: Supports server, client, and bidirectional streaming
- **Code Generation**: Automatically generates client and server code
- **HTTP/2**: Leverages HTTP/2 features like multiplexing and header compression

## 📋 Requirements

- PHP 7.4+
- Laravel 8.0+
- gRPC PHP extension (`grpc.so`)
- Protocol Buffers PHP extension (`protobuf.so`)

## 🔧 Installation

### 1. Install PHP Extensions

```bash
pecl install grpc
pecl install protobuf
```

Add to your php.ini:
```ini
extension=grpc.so
extension=protobuf.so
```

### 2. Install Package

```bash
composer require your-vendor/laravel-api-model-relations
```

### 3. Publish Configuration

```bash
php artisan vendor:publish --provider="MTechStack\LaravelApiModelClient\MTechStack\LaravelApiModelClientServiceProvider" --tag="config"
```

### 4. Generate gRPC Client Code

Install the Protocol Buffer compiler and gRPC plugin:

```bash
# macOS
brew install protobuf
brew install grpc

# Ubuntu/Debian
apt-get install -y protobuf-compiler
apt-get install -y protobuf-compiler-grpc
```

Generate PHP code from your .proto files:

```bash
protoc --proto_path=protos/ \
       --php_out=generated/ \
       --grpc_out=generated/ \
       --plugin=protoc-gen-grpc=`which grpc_php_plugin` \
       protos/your_service.proto
```

## ⚙️ Configuration

### Basic Configuration

In your `.env` file:

```
# Use gRPC instead of REST
API_MODEL_PROTOCOL=grpc

# Other API model settings
API_MODEL_QUEUE_OPERATIONS=true
API_MODEL_RETRY_ATTEMPTS=3
API_MODEL_CACHE_ENABLED=true
API_MODEL_CACHE_TTL=3600
```

### Service Mapping

Configure your gRPC services in `config/api_model.php`:

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

## 🔍 Usage

### Creating a gRPC-Compatible Model

```php
<?php

namespace App\Models;

use MTechStack\LaravelApiModelClient\Models\ApiModel;

class User extends ApiModel
{
    // Optional: Override global protocol setting for this model
    protected $apiProtocol = 'grpc';
    
    // Base URL for the gRPC server
    protected $apiBaseUrl = 'localhost:50051';
    
    // API endpoint (service name in gRPC)
    protected $apiEndpoint = 'users';
    
    // Fillable attributes
    protected $fillable = ['name', 'email', 'role'];
    
    // Attributes that should only be stored in the database, not sent to API
    public function getDbOnlyAttributes()
    {
        return ['password', 'remember_token'];
    }
}
```

### Basic Operations

```php
// Find a user from the API
$user = User::findFromApi(1);

// Get all users from the API
$users = User::allFromApi();

// Create a new user (saved to both database and API)
$user = new User([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'role' => 'user'
]);
$user->save();

// Update a user
$user->name = 'Jane Doe';
$user->update();

// Delete a user
$user->delete();
```

### Advanced Features

#### Transaction-based Operations

All operations are wrapped in database transactions to ensure consistency between your database and API:

```php
try {
    $user = new User($userData);
    $user->save(); // If API call fails, database changes are rolled back
} catch (\Exception $e) {
    // Handle exception
}
```

#### Queue-based API Operations

API operations can be queued for better performance:

```php
// In .env
API_MODEL_QUEUE_OPERATIONS=true
API_MODEL_QUEUE_NAME=api-sync

// Process the queue
php artisan queue:work --queue=api-sync
```

#### Retry Logic

Failed API operations are automatically retried with exponential backoff:

```php
// In .env
API_MODEL_RETRY_ATTEMPTS=3
```

#### Caching

API responses are cached for better performance:

```php
// In .env
API_MODEL_CACHE_ENABLED=true
API_MODEL_CACHE_TTL=3600 // 1 hour
```

## 🧪 Testing

When testing with gRPC, you can:

1. **Mock the gRPC client**:

```php
$mockClient = Mockery::mock(ApiClientInterface::class);
$mockClient->shouldReceive('get')
    ->andReturn(['id' => 1, 'name' => 'Test User']);
$this->app->instance('api-client', $mockClient);
```

2. **Disable API sync during testing**:

```php
// In .env.testing
API_MODEL_SYNC_IN_TESTING=false
```

## 🔄 Protocol Buffers Example

Here's a sample .proto file for a User service:

```protobuf
syntax = "proto3";

package user;

service UserService {
    rpc GetUser (GetUserRequest) returns (User);
    rpc ListUsers (ListUsersRequest) returns (UserList);
    rpc CreateUser (User) returns (User);
    rpc UpdateUser (User) returns (User);
    rpc DeleteUser (DeleteUserRequest) returns (DeleteUserResponse);
}

message GetUserRequest {
    int32 id = 1;
}

message ListUsersRequest {
    int32 page = 1;
    int32 per_page = 2;
}

message User {
    int32 id = 1;
    string name = 2;
    string email = 3;
    string role = 4;
    string created_at = 5;
    string updated_at = 6;
}

message UserList {
    repeated User items = 1;
    int32 total = 2;
}

message DeleteUserRequest {
    int32 id = 1;
}

message DeleteUserResponse {
    bool success = 1;
}
```

## 🔧 Troubleshooting

### Common Issues

1. **Connection Refused**
   - Ensure your gRPC server is running and accessible
   - Check firewall settings

2. **Method Not Found**
   - Verify your service definition in the config file
   - Check that the method exists in your gRPC service

3. **Type Mismatch**
   - Ensure your model attributes match the expected types in your Protocol Buffer messages

### Debugging

Enable detailed logging:

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

## 📈 Performance Tips

- Use connection pooling for high-traffic applications
- Enable response caching for frequently accessed data
- Consider streaming for large datasets
- Use bidirectional streaming for real-time updates

## 📚 Further Reading

- [Official gRPC Documentation](https://grpc.io/docs/)
- [Protocol Buffers Developer Guide](https://developers.google.com/protocol-buffers/docs/overview)
- [Laravel Queue Documentation](https://laravel.com/docs/queues)

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
