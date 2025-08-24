<?php

namespace MTechStack\LaravelApiModelClient\Query;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ApiPaginator extends LengthAwarePaginator
{
    /**
     * The original API response.
     *
     * @var array
     */
    protected $apiResponse;

    /**
     * Create a new API paginator instance.
     *
     * @param mixed $items
     * @param int $total
     * @param int $perPage
     * @param int|null $currentPage
     * @param array $options
     * @param array $apiResponse
     * @return void
     */
    public function __construct($items, $total, $perPage, $currentPage = null, array $options = [], array $apiResponse = [])
    {
        parent::__construct($items, $total, $perPage, $currentPage, $options);
        $this->apiResponse = $apiResponse;
    }

    /**
     * Get the original API response.
     *
     * @return array
     */
    public function getApiResponse()
    {
        return $this->apiResponse;
    }

    /**
     * Create a new API paginator instance from an API response.
     *
     * @param \MTechStack\LaravelApiModelClient\Models\ApiModel $model
     * @param array $response
     * @param int $perPage
     * @param int|null $currentPage
     * @param array $options
     * @return static
     */
    public static function fromApiResponse($model, array $response, $perPage, $currentPage = null, array $options = [])
    {
        // Extract pagination metadata from the response
        $meta = $response['meta'] ?? [];
        $pagination = $meta['pagination'] ?? [];
        
        // Try to determine total count
        $total = $pagination['total'] ?? null;
        
        // If total is not available, try other common keys
        if ($total === null) {
            $possibleTotalKeys = ['total', 'total_count', 'count', 'total_items'];
            
            foreach ($possibleTotalKeys as $key) {
                if (isset($pagination[$key])) {
                    $total = $pagination[$key];
                    break;
                }
            }
        }
        
        // If still no total, use the count of items
        if ($total === null) {
            $items = static::extractItemsFromResponse($response);
            $total = count($items);
        }
        
        // Create models from the response items
        $items = static::createModelsFromResponse($model, $response);
        
        // Create the paginator
        return new static($items, $total, $perPage, $currentPage, $options, $response);
    }

    /**
     * Extract items from an API response.
     *
     * @param array $response
     * @return array
     */
    protected static function extractItemsFromResponse(array $response)
    {
        // If response has a data key, use that
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        
        // Check for other common keys
        $possibleKeys = ['items', 'results', 'records', 'content'];
        
        foreach ($possibleKeys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }
        
        // If response is already an array of items, return it
        if (isset($response[0])) {
            return $response;
        }
        
        // If we can't find the items, return an empty array
        return [];
    }

    /**
     * Create model instances from an API response.
     *
     * @param \MTechStack\LaravelApiModelClient\Models\ApiModel $model
     * @param array $response
     * @return \Illuminate\Support\Collection
     */
    protected static function createModelsFromResponse($model, array $response)
    {
        $items = static::extractItemsFromResponse($response);
        $models = [];
        
        foreach ($items as $item) {
            $instance = $model->newFromApiResponse($item);
            if ($instance !== null) {
                $models[] = $instance;
            }
        }
        
        return new Collection($models);
    }
}
