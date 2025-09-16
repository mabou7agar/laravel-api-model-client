<?php

namespace MTechStack\LaravelApiModelClient\OpenApi\Traits;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use Illuminate\Support\Facades\Log;

/**
 * Trait for extracting endpoint information from OpenAPI specifications
 */
trait ExtractsEndpoints
{
    /**
     * Extract endpoint information
     */
    protected function extractEndpoints(): void
    {
        $this->endpoints = [];

        if (!$this->openApiSpec->paths) {
            Log::warning("No paths found in OpenAPI schema");
            return;
        }

        foreach ($this->openApiSpec->paths as $path => $pathItem) {
            if ($pathItem instanceof Reference) {
                $pathItem = $pathItem->resolve();
            }

            if (!$pathItem instanceof PathItem) {
                continue;
            }

            $this->extractPathOperations($path, $pathItem);
        }

        Log::info("Extracted endpoints", ['count' => count($this->endpoints)]);
    }

    /**
     * Extract operations from a path item
     */
    protected function extractPathOperations(string $path, PathItem $pathItem): void
    {
        $methods = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];

        foreach ($methods as $method) {
            $operation = $pathItem->{$method};
            
            if ($operation instanceof Operation) {
                $this->extractOperation($path, $method, $operation);
            }
        }
    }

    /**
     * Extract single operation details
     */
    protected function extractOperation(string $path, string $method, Operation $operation): void
    {
        $operationId = $operation->operationId ?? $this->generateOperationId($method, $path);
        
        $endpoint = [
            'operation_id' => $operationId,
            'path' => $path,
            'method' => strtoupper($method),
            'summary' => $operation->summary ?? '',
            'description' => $operation->description ?? '',
            'tags' => $operation->tags ?? [],
            'parameters' => $this->extractParameters($operation),
            'request_body' => $this->extractRequestBody($operation),
            'responses' => $this->extractResponses($operation),
            'security' => $operation->security ?? [],
            'deprecated' => $operation->deprecated ?? false,
        ];

        $this->endpoints[$operationId] = $endpoint;
    }

    /**
     * Extract parameters from operation
     */
    protected function extractParameters(Operation $operation): array
    {
        $parameters = [];

        if ($operation->parameters) {
            foreach ($operation->parameters as $parameter) {
                if ($parameter instanceof Reference) {
                    $parameter = $parameter->resolve();
                }

                if ($parameter instanceof Parameter) {
                    $parameters[] = $this->extractParameter($parameter);
                }
            }
        }

        return $parameters;
    }

    /**
     * Extract single parameter details
     */
    protected function extractParameter(Parameter $parameter): array
    {
        return [
            'name' => $parameter->name,
            'in' => $parameter->in,
            'description' => $parameter->description ?? '',
            'required' => $parameter->required ?? false,
            'deprecated' => $parameter->deprecated ?? false,
            'schema' => $this->extractSchemaInfo($parameter->schema),
            'style' => $parameter->style ?? null,
            'explode' => $parameter->explode ?? null,
            'example' => $parameter->example ?? null,
        ];
    }

    /**
     * Extract request body information
     */
    protected function extractRequestBody(Operation $operation): ?array
    {
        if (!$operation->requestBody) {
            return null;
        }

        $requestBody = $operation->requestBody;
        if ($requestBody instanceof Reference) {
            $requestBody = $requestBody->resolve();
        }

        $content = [];
        if ($requestBody->content) {
            foreach ($requestBody->content as $mediaType => $mediaTypeObject) {
                $content[$mediaType] = [
                    'schema' => $this->extractSchemaInfo($mediaTypeObject->schema),
                    'example' => $mediaTypeObject->example ?? null,
                    'examples' => $mediaTypeObject->examples ?? [],
                ];
            }
        }

        return [
            'description' => $requestBody->description ?? '',
            'required' => $requestBody->required ?? false,
            'content' => $content,
        ];
    }

    /**
     * Extract response information
     */
    protected function extractResponses(Operation $operation): array
    {
        $responses = [];

        if ($operation->responses) {
            foreach ($operation->responses as $statusCode => $response) {
                if ($response instanceof Reference) {
                    $response = $response->resolve();
                }

                $content = [];
                if ($response->content) {
                    foreach ($response->content as $mediaType => $mediaTypeObject) {
                        $content[$mediaType] = [
                            'schema' => $this->extractSchemaInfo($mediaTypeObject->schema),
                            'example' => $mediaTypeObject->example ?? null,
                        ];
                    }
                }

                $responses[$statusCode] = [
                    'description' => $response->description ?? '',
                    'content' => $content,
                    'headers' => $response->headers ?? [],
                ];
            }
        }

        return $responses;
    }

    /**
     * Extract basic information from OpenAPI spec
     */
    protected function extractInfo(): array
    {
        $info = $this->openApiSpec->info ?? null;
        
        if (!$info) {
            return [];
        }

        return [
            'title' => $info->title ?? '',
            'description' => $info->description ?? '',
            'version' => $info->version ?? '',
            'terms_of_service' => $info->termsOfService ?? '',
            'contact' => $info->contact ? [
                'name' => $info->contact->name ?? '',
                'url' => $info->contact->url ?? '',
                'email' => $info->contact->email ?? '',
            ] : null,
            'license' => $info->license ? [
                'name' => $info->license->name ?? '',
                'url' => $info->license->url ?? '',
            ] : null,
        ];
    }

    /**
     * Extract server information
     */
    protected function extractServers(): array
    {
        $servers = [];

        if ($this->openApiSpec->servers) {
            foreach ($this->openApiSpec->servers as $server) {
                $variables = [];
                if (isset($server->variables) && is_array($server->variables)) {
                    $variables = $server->variables;
                } elseif (isset($server->variables) && is_object($server->variables)) {
                    // Convert object to array
                    foreach ($server->variables as $name => $variable) {
                        $variables[$name] = [
                            'enum' => $variable->enum ?? [],
                            'default' => $variable->default ?? '',
                            'description' => $variable->description ?? '',
                        ];
                    }
                }
                
                $servers[] = [
                    'url' => $server->url ?? '',
                    'description' => $server->description ?? '',
                    'variables' => $variables,
                ];
            }
        }

        return $servers;
    }

    /**
     * Extract security information
     */
    protected function extractSecurity(): array
    {
        $security = [];

        if ($this->openApiSpec->components && $this->openApiSpec->components->securitySchemes) {
            foreach ($this->openApiSpec->components->securitySchemes as $name => $scheme) {
                if ($scheme instanceof Reference) {
                    $scheme = $scheme->resolve();
                }

                $security[$name] = [
                    'type' => isset($scheme->type) ? (string)$scheme->type : '',
                    'description' => isset($scheme->description) ? (string)$scheme->description : '',
                    'name' => isset($scheme->name) ? (string)$scheme->name : '',
                    'in' => isset($scheme->in) ? (string)$scheme->in : '',
                    'scheme' => isset($scheme->scheme) ? (string)$scheme->scheme : '',
                    'bearerFormat' => isset($scheme->bearerFormat) ? (string)$scheme->bearerFormat : '',
                ];
            }
        }

        return $security;
    }

    /**
     * Generate operation ID from method and path
     */
    protected function generateOperationId(string $method, string $path): string
    {
        $cleanPath = preg_replace('/[^a-zA-Z0-9]/', '_', $path);
        return strtolower($method) . '_' . trim($cleanPath, '_');
    }
}
