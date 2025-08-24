<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Models\ApiModel;

trait HandlesApiModelCreation
{
    /**
     * Create a new model instance from API response data using reflection.
     * This method bypasses all __call method interference to ensure proper model instantiation.
     *
     * @param ApiModel $model The model instance to use as a template
     * @param array $responseData The API response data
     * @return ApiModel|null The created model instance or null on failure
     */
    protected function createModelFromApiResponse(ApiModel $model, array $responseData)
    {
        if (empty($responseData)) {
            return null;
        }

        try {
            // Create a fresh instance of the model class
            $modelClass = get_class($model);
            $newModel = new $modelClass();
            
            // Use reflection to call newFromApiResponse directly, bypassing all __call interference
            $reflection = new \ReflectionClass($newModel);
            if ($reflection->hasMethod('newFromApiResponse')) {
                $method = $reflection->getMethod('newFromApiResponse');
                return $method->invoke($newModel, $responseData);
            }
            
            // Fallback: create model with basic attributes if newFromApiResponse is not available
            return new $modelClass($responseData);
            
        } catch (\Exception $e) {
            // Log error but don't throw - return null to indicate failure
            error_log("Failed to create model from API response: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create multiple model instances from an array of API response items.
     *
     * @param ApiModel $model The model instance to use as a template
     * @param array $items Array of API response items
     * @return \Illuminate\Support\Collection Collection of created model instances
     */
    protected function createModelsFromApiResponseItems(ApiModel $model, array $items)
    {
        $models = [];
        
        foreach ($items as $item) {
            $createdModel = $this->createModelFromApiResponse($model, $item);
            if ($createdModel !== null) {
                $models[] = $createdModel;
            }
        }
        
        return collect($models);
    }
}
