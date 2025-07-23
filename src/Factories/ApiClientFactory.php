<?php

namespace ApiModelRelations\Factories;

use ApiModelRelations\Clients\GrpcApiClient;
use ApiModelRelations\Contracts\ApiClientInterface;
use ApiModelRelations\Contracts\AuthStrategyInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ApiClientFactory
{
    /**
     * Create an API client instance based on configuration.
     *
     * @param string $baseUrl
     * @param \ApiModelRelations\Contracts\AuthStrategyInterface|null $authStrategy
     * @param string|null $protocol Override the default protocol from config
     * @return \ApiModelRelations\Contracts\ApiClientInterface
     */
    public static function create(
        string $baseUrl = '',
        ?AuthStrategyInterface $authStrategy = null,
        ?string $protocol = null
    ): ApiClientInterface {
        $protocol = $protocol ?? Config::get('api_model.protocol', 'rest');
        
        Log::debug("Creating API client with protocol: {$protocol}");
        
        switch ($protocol) {
            case 'grpc':
                $client = new GrpcApiClient($baseUrl, $authStrategy);
                
                // Register gRPC services from configuration
                $services = Config::get('api_model.grpc.services', []);
                foreach ($services as $modelClass => $serviceConfig) {
                    $client->registerServiceForModel(
                        $modelClass,
                        $serviceConfig['client'],
                        $serviceConfig['methods']
                    );
                }
                
                return $client;
                
            case 'rest':
            default:
                // Use the default REST client from the container
                $client = app('api-client');
                
                if ($baseUrl) {
                    $client->setBaseUrl($baseUrl);
                }
                
                if ($authStrategy) {
                    $client->setAuthStrategy($authStrategy);
                }
                
                return $client;
        }
    }
}
