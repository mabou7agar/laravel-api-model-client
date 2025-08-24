<?php

namespace MTechStack\LaravelApiModelClient\Clients;

use MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface;
use MTechStack\LaravelApiModelClient\Contracts\AuthStrategyInterface;
use MTechStack\LaravelApiModelClient\Exceptions\ApiException;
use Google\Protobuf\Internal\Message;
use Illuminate\Support\Facades\Log;

class GrpcApiClient implements ApiClientInterface
{
    /**
     * Base URL for gRPC requests.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Authentication strategy.
     *
     * @var \ApiModelRelations\Contracts\AuthStrategyInterface|null
     */
    protected $authStrategy;

    /**
     * gRPC channel.
     *
     * @var \Grpc\Channel
     */
    protected $channel;

    /**
     * Mapping of model classes to their corresponding gRPC service clients.
     *
     * @var array
     */
    protected $serviceMap = [];

    /**
     * Create a new gRPC API client instance.
     *
     * @param string $baseUrl
     * @param \ApiModelRelations\Contracts\AuthStrategyInterface|null $authStrategy
     */
    public function __construct(string $baseUrl = '', ?AuthStrategyInterface $authStrategy = null)
    {
        $this->baseUrl = $baseUrl;
        $this->authStrategy = $authStrategy;
    }

    /**
     * Initialize the gRPC channel.
     *
     * @return void
     */
    protected function initChannel()
    {
        if (!$this->channel) {
            $this->channel = new \Grpc\Channel(
                $this->baseUrl,
                [
                    'credentials' => \Grpc\ChannelCredentials::createInsecure(),
                    'grpc.max_receive_message_length' => -1,
                    'grpc.max_send_message_length' => -1,
                ]
            );
        }
    }

    /**
     * Get the gRPC service client for a specific model.
     *
     * @param string $modelClass
     * @return mixed
     * @throws \Exception
     */
    public function getServiceForModel(string $modelClass)
    {
        if (!isset($this->serviceMap[$modelClass])) {
            throw new ApiException("No gRPC service client registered for model {$modelClass}");
        }

        $serviceClass = $this->serviceMap[$modelClass]['client'];
        $this->initChannel();
        
        return new $serviceClass($this->baseUrl, [
            'credentials' => \Grpc\ChannelCredentials::createInsecure(),
        ]);
    }

    /**
     * Register a gRPC service client for a model.
     *
     * @param string $modelClass
     * @param string $serviceClientClass
     * @param array $methodMap
     * @return $this
     */
    public function registerServiceForModel(string $modelClass, string $serviceClientClass, array $methodMap)
    {
        $this->serviceMap[$modelClass] = [
            'client' => $serviceClientClass,
            'methods' => $methodMap
        ];

        return $this;
    }

    /**
     * Convert an array to a protobuf message.
     *
     * @param array $data
     * @param string $messageClass
     * @return \Google\Protobuf\Internal\Message
     */
    protected function arrayToMessage(array $data, string $messageClass)
    {
        $message = new $messageClass();
        
        foreach ($data as $key => $value) {
            $setter = 'set' . ucfirst($this->camelCase($key));
            if (method_exists($message, $setter)) {
                $message->$setter($value);
            }
        }
        
        return $message;
    }

    /**
     * Convert a protobuf message to an array.
     *
     * @param \Google\Protobuf\Internal\Message $message
     * @return array
     */
    protected function messageToArray(Message $message)
    {
        $result = [];
        $reflector = new \ReflectionClass($message);
        
        foreach ($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            
            // Look for getters
            if (strpos($name, 'get') === 0 && $name !== 'getIterator' && $name !== 'getSize') {
                $property = lcfirst(substr($name, 3));
                $value = $message->$name();
                
                if ($value instanceof Message) {
                    $result[$property] = $this->messageToArray($value);
                } else {
                    $result[$property] = $value;
                }
            }
        }
        
        return $result;
    }

    /**
     * Convert a string to camelCase.
     *
     * @param string $string
     * @return string
     */
    protected function camelCase(string $string)
    {
        $string = ucwords(str_replace(['-', '_'], ' ', $string));
        return lcfirst(str_replace(' ', '', $string));
    }

    /**
     * Get the method name and message class for a specific operation.
     *
     * @param string $modelClass
     * @param string $operation
     * @param string $id
     * @return array
     * @throws \Exception
     */
    protected function getMethodInfo(string $modelClass, string $operation, ?string $id = null)
    {
        if (!isset($this->serviceMap[$modelClass])) {
            throw new ApiException("No gRPC service client registered for model {$modelClass}");
        }

        $methodMap = $this->serviceMap[$modelClass]['methods'];
        
        if (!isset($methodMap[$operation])) {
            throw new ApiException("No gRPC method mapping for operation {$operation} on model {$modelClass}");
        }
        
        return $methodMap[$operation];
    }

    /**
     * Send a GET request to the API.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $headers
     * @return mixed
     * @throws \Exception
     */
    public function get(string $endpoint, array $queryParams = [], array $headers = [])
    {
        // Parse the endpoint to determine the model class and ID
        list($modelClass, $id) = $this->parseEndpoint($endpoint);
        
        try {
            $service = $this->getServiceForModel($modelClass);
            
            if ($id) {
                // Get a single item
                $methodInfo = $this->getMethodInfo($modelClass, 'get', $id);
                $requestClass = $methodInfo['request'];
                $method = $methodInfo['method'];
                
                $request = new $requestClass();
                $request->setId($id);
                
                list($response, $status) = $service->$method($request, $this->getMetadata())->wait();
                
                if ($status->code !== \Grpc\STATUS_OK) {
                    throw new ApiException("gRPC error: {$status->details}", $status->code);
                }
                
                return $this->messageToArray($response);
            } else {
                // Get all items
                $methodInfo = $this->getMethodInfo($modelClass, 'list');
                $requestClass = $methodInfo['request'];
                $method = $methodInfo['method'];
                
                $request = new $requestClass();
                
                // Apply query parameters if supported by the request
                foreach ($queryParams as $key => $value) {
                    $setter = 'set' . ucfirst($this->camelCase($key));
                    if (method_exists($request, $setter)) {
                        $request->$setter($value);
                    }
                }
                
                list($response, $status) = $service->$method($request, $this->getMetadata())->wait();
                
                if ($status->code !== \Grpc\STATUS_OK) {
                    throw new ApiException("gRPC error: {$status->details}", $status->code);
                }
                
                // Convert the response to an array
                $items = [];
                $getter = method_exists($response, 'getItems') ? 'getItems' : 'getData';
                
                if (method_exists($response, $getter)) {
                    foreach ($response->$getter() as $item) {
                        $items[] = $this->messageToArray($item);
                    }
                }
                
                return $items;
            }
        } catch (\Exception $e) {
            Log::error("gRPC GET error: {$e->getMessage()}", [
                'endpoint' => $endpoint,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new ApiException("gRPC error: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Send a POST request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return mixed
     * @throws \Exception
     */
    public function post(string $endpoint, array $data = [], array $headers = [])
    {
        // Parse the endpoint to determine the model class
        list($modelClass, $id) = $this->parseEndpoint($endpoint);
        
        try {
            $service = $this->getServiceForModel($modelClass);
            $methodInfo = $this->getMethodInfo($modelClass, 'create');
            $requestClass = $methodInfo['request'];
            $method = $methodInfo['method'];
            
            // Convert data to a protobuf message
            $request = $this->arrayToMessage($data, $requestClass);
            
            list($response, $status) = $service->$method($request, $this->getMetadata())->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new ApiException("gRPC error: {$status->details}", $status->code);
            }
            
            return $this->messageToArray($response);
        } catch (\Exception $e) {
            Log::error("gRPC POST error: {$e->getMessage()}", [
                'endpoint' => $endpoint,
                'data' => $data,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new ApiException("gRPC error: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Send a PUT request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return mixed
     * @throws \Exception
     */
    public function put(string $endpoint, array $data = [], array $headers = [])
    {
        // Parse the endpoint to determine the model class and ID
        list($modelClass, $id) = $this->parseEndpoint($endpoint);
        
        if (!$id) {
            throw new ApiException("ID is required for PUT requests");
        }
        
        try {
            $service = $this->getServiceForModel($modelClass);
            $methodInfo = $this->getMethodInfo($modelClass, 'update');
            $requestClass = $methodInfo['request'];
            $method = $methodInfo['method'];
            
            // Add ID to the data
            $data['id'] = $id;
            
            // Convert data to a protobuf message
            $request = $this->arrayToMessage($data, $requestClass);
            
            list($response, $status) = $service->$method($request, $this->getMetadata())->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new ApiException("gRPC error: {$status->details}", $status->code);
            }
            
            return $this->messageToArray($response);
        } catch (\Exception $e) {
            Log::error("gRPC PUT error: {$e->getMessage()}", [
                'endpoint' => $endpoint,
                'data' => $data,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new ApiException("gRPC error: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Send a PATCH request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return mixed
     * @throws \Exception
     */
    public function patch(string $endpoint, array $data = [], array $headers = [])
    {
        // gRPC doesn't have a direct equivalent to PATCH, so we'll use the update method
        return $this->put($endpoint, $data, $headers);
    }

    /**
     * Send a DELETE request to the API.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $headers
     * @return mixed
     * @throws \Exception
     */
    public function delete(string $endpoint, array $queryParams = [], array $headers = [])
    {
        // Parse the endpoint to determine the model class and ID
        list($modelClass, $id) = $this->parseEndpoint($endpoint);
        
        if (!$id) {
            throw new ApiException("ID is required for DELETE requests");
        }
        
        try {
            $service = $this->getServiceForModel($modelClass);
            $methodInfo = $this->getMethodInfo($modelClass, 'delete');
            $requestClass = $methodInfo['request'];
            $method = $methodInfo['method'];
            
            // Create delete request
            $request = new $requestClass();
            $request->setId($id);
            
            list($response, $status) = $service->$method($request, $this->getMetadata())->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new ApiException("gRPC error: {$status->details}", $status->code);
            }
            
            return $this->messageToArray($response);
        } catch (\Exception $e) {
            Log::error("gRPC DELETE error: {$e->getMessage()}", [
                'endpoint' => $endpoint,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new ApiException("gRPC error: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Parse an endpoint string to extract the model class and ID.
     *
     * @param string $endpoint
     * @return array
     */
    protected function parseEndpoint(string $endpoint)
    {
        $parts = explode('/', trim($endpoint, '/'));
        $modelClass = $parts[0];
        $id = isset($parts[1]) ? $parts[1] : null;
        
        return [$modelClass, $id];
    }

    /**
     * Get the metadata for gRPC requests.
     *
     * @return array
     */
    protected function getMetadata()
    {
        $metadata = [];
        
        if ($this->authStrategy) {
            $authHeader = $this->authStrategy->getAuthHeader();
            if ($authHeader) {
                list($key, $value) = explode(':', $authHeader, 2);
                $metadata[trim($key)] = [trim($value)];
            }
        }
        
        return $metadata;
    }

    /**
     * Set the authentication strategy.
     *
     * @param \ApiModelRelations\Contracts\AuthStrategyInterface $authStrategy
     * @return $this
     */
    public function setAuthStrategy(AuthStrategyInterface $authStrategy)
    {
        $this->authStrategy = $authStrategy;
        return $this;
    }

    /**
     * Set the base URL for API requests.
     *
     * @param string $baseUrl
     * @return $this
     */
    public function setBaseUrl(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->channel = null; // Reset channel so it will be reinitialized with the new URL
        return $this;
    }
}
