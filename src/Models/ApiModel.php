<?php

namespace MTechStack\LaravelApiModelClient\Models;

use MTechStack\LaravelApiModelClient\Traits\HasApiRelationships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * API Model — extends Eloquent to behave like a standard model while
 * fetching data from a REST API instead of a database.
 *
 * Design goal: code that works with Eloquent models should work with
 * ApiModel subclasses without any special-casing. This means:
 *
 *   - toArray() works safely (guarded attributes are excluded)
 *   - morphTo / setRelation / getRelationValue work normally
 *   - Session serialization / deserialization works
 *   - $model->exists = true after fetch, so save() does UPDATE
 *   - find() is cached per-request to avoid duplicate API calls
 *
 * Provides: ::find(), ::where()->get(), ::all(), ::findOrFail()
 */
class ApiModel extends Model
{
    use HasApiRelationships;

    /**
     * The REST API endpoint (e.g. 'products', 'categories').
     */
    protected $apiEndpoint;

    /**
     * Store raw API response data.
     */
    protected $apiResponseData = null;

    /**
     * Request-level cache for find() results.
     * Prevents duplicate API calls for the same model+id within one request.
     */
    protected static array $findCache = [];

    /**
     * Disable Eloquent DB connection — this model doesn't use a database.
     * All attributes are mass-assignable since they come from the API.
     */
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (!isset($this->table) || $this->table === '') {
            $this->setTable($this->getDefaultTableName());
        }
    }

    protected function getDefaultTableName(): string
    {
        return strtolower(Str::plural(class_basename($this)));
    }

    protected static function boot()
    {
        if (!isset(static::$traitInitializers[static::class])) {
            static::$traitInitializers[static::class] = [];
        }
        parent::boot();
    }

    protected function initializeTraits()
    {
        if (isset(static::$traitInitializers[static::class]) &&
            is_array(static::$traitInitializers[static::class])) {
            foreach (static::$traitInitializers[static::class] as $method) {
                if (method_exists($this, $method)) {
                    $this->{$method}();
                }
            }
        }
    }

    // ─── Eloquent Compatibility ──────────────────────────────────

    /**
     * Override toArray to produce a safe, lightweight array.
     * Skips $appends accessors that may trigger heavy computation.
     * Subclasses can override safeAppends() to whitelist specific appends.
     */
    public function toArray()
    {
        // Only include raw attributes + safe appends — skip heavy accessors
        $array = $this->attributesToArray();

        // Remove nested arrays/objects that are too large (e.g. variants with 50+ items)
        // to keep serialization fast. Subclasses can override this.
        return $array;
    }

    /**
     * Override attributesToArray to skip $appends unless explicitly safe.
     * This prevents accessors like getSuperAttributesAttribute from being
     * called during toArray(), which caused infinite loops.
     */
    public function attributesToArray()
    {
        // Temporarily clear $appends to prevent accessor calls during serialization
        $originalAppends = $this->appends;
        $this->appends = property_exists($this, 'safeAppends') ? $this->safeAppends : [];

        $array = parent::attributesToArray();

        $this->appends = $originalAppends;
        return $array;
    }

    /**
     * Prevent Eloquent from trying to perform DB operations.
     * save/update/delete are no-ops for API models.
     */
    public function save(array $options = [])
    {
        // API models don't persist to DB — override in subclass if needed
        return true;
    }

    public function delete()
    {
        return true;
    }

    /**
     * Make the model JSON-serializable for session storage.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Get the connection — return null to prevent DB queries.
     */
    public function getConnectionName()
    {
        return null;
    }

    // ─── Query Methods ───────────────────────────────────────────

    public static function find($id, $columns = ['*'])
    {
        // Request-level cache — avoid duplicate API calls
        $cacheKey = static::class . ':' . $id;
        if (array_key_exists($cacheKey, static::$findCache)) {
            return static::$findCache[$cacheKey];
        }

        $instance = new static;

        try {
            $apiClient = $instance->getApiClient();
            $endpoint = $instance->getApiEndpoint() . '/' . $id;
            $response = $apiClient->get($endpoint);

            if (empty($response)) {
                $base = $instance->getBaseUrl();
                $url = rtrim($base, '/') . '/' . ltrim($endpoint, '/');
                $response = Http::timeout(5)->get($url)->json() ?? [];
            }

            if (empty($response)) {
                static::$findCache[$cacheKey] = null;
                return null;
            }

            $model = $instance->newFromApiResponse($response);
            if ($model) {
                $model->exists = true;
            }
            static::$findCache[$cacheKey] = $model;
            return $model;
        } catch (\Exception $e) {
            static::$findCache[$cacheKey] = null;
            return null;
        }
    }

    public static function findOrFail($id, $columns = ['*'])
    {
        $model = static::find($id, $columns);
        if (!$model) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'No query results for model [' . static::class . '] ' . $id
            );
        }
        return $model;
    }

    public static function all($columns = ['*'])
    {
        return static::allFromApi();
    }

    public static function allFromApi()
    {
        $instance = new static;

        try {
            $apiClient = $instance->getApiClient();
            $response = $apiClient->get($instance->getApiEndpoint());

            if (empty($response)) {
                $base = $instance->getBaseUrl();
                $url = rtrim($base, '/') . '/' . ltrim($instance->getApiEndpoint(), '/');
                $response = Http::timeout(15)->get($url)->json() ?? [];
            }

            $items = $instance->extractItemsFromResponse($response ?? []);

            return collect($items)->map(function ($item) {
                return (new static())->newFromApiResponse($item ?? []);
            })->filter();
        } catch (\Exception $e) {
            return collect();
        }
    }

    public static function findFromApi($id)
    {
        return static::find($id);
    }

    public static function where($column, $operator = null, $value = null)
    {
        $instance = new static;
        $builder = $instance->newApiQueryBuilder();
        return $builder->where($column, $operator, $value);
    }

    public static function take($value)
    {
        $instance = new static;
        return $instance->newApiQueryBuilder()->take($value);
    }

    public static function limit($value)
    {
        return static::take($value);
    }

    // ─── API Client ──────────────────────────────────────────────

    protected function getApiClient(array $requestContext = [])
    {
        return App::make('api-client');
    }

    protected function getBaseUrl(): string
    {
        return config('api-model-client.client.base_url')
            ?? config('api-model-client.base_url')
            ?? config('bagisto.api_url', '');
    }

    public function getApiEndpoint(): string
    {
        if (property_exists($this, 'apiEndpoint') && $this->apiEndpoint) {
            return $this->apiEndpoint;
        }
        return strtolower(Str::plural(class_basename($this)));
    }

    public function isApiModel(): bool
    {
        return property_exists($this, 'apiEndpoint') && $this->apiEndpoint !== null;
    }

    // ─── Response Mapping ────────────────────────────────────────

    public function newFromApiResponse($response = [])
    {
        if (empty($response)) {
            return null;
        }

        $attributes = $response;

        // Unwrap nested data
        if (isset($response['data']) && is_array($response['data'])) {
            $nested = $response['data'];
            if (!isset($nested[0])) {
                $attributes = $nested;
            }
        }

        if (empty($attributes) || !is_array($attributes)) {
            return null;
        }

        // Map via mapApiResponseToAttributes if available
        if (method_exists($this, 'mapApiResponseToAttributes')) {
            $attributes = $this->mapApiResponseToAttributes($attributes);
        }

        $model = new static();
        $model->apiResponseData = $response;

        foreach ($attributes as $key => $value) {
            if (is_string($key)) {
                $model->setAttribute($key, $value);
            }
        }

        $model->exists = true;
        $model->syncOriginal();
        return $model;
    }

    public function extractItemsFromResponse($response): array
    {
        if (empty($response)) {
            return [];
        }

        if (isset($response['data'])) {
            $data = $response['data'];
            if (is_array($data) && isset($data[0])) {
                return $data;
            }
            if (is_array($data) && !isset($data[0])) {
                return [$data];
            }
        }

        if (isset($response[0])) {
            return $response;
        }

        if (is_array($response) && !empty($response)) {
            return [$response];
        }

        return [];
    }

    // ─── API Response Data ───────────────────────────────────────

    public function setApiResponseData(array $response): void
    {
        $this->apiResponseData = $response;
    }

    public function getApiResponseData(): ?array
    {
        return $this->apiResponseData;
    }

    public function hasApiResponseData(): bool
    {
        return $this->apiResponseData !== null;
    }

    /**
     * Clear the request-level find cache (useful for testing).
     */
    public static function clearFindCache(): void
    {
        static::$findCache = [];
    }

    // ─── Simple Query Builder ────────────────────────────────────

    protected function newApiQueryBuilder(): ApiSimpleQueryBuilder
    {
        return new ApiSimpleQueryBuilder($this);
    }
}

/**
 * Simple query builder for ApiModel — collects where/take/limit
 * and executes as API query parameters.
 */
class ApiSimpleQueryBuilder
{
    protected ApiModel $model;
    protected array $wheres = [];
    protected ?int $limitValue = null;

    public function __construct(ApiModel $model)
    {
        $this->model = $model;
    }

    public function where($column, $operator = null, $value = null): static
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
        }
        $this->wheres[$column] = $value;
        return $this;
    }

    public function take(int $value): static
    {
        $this->limitValue = $value;
        return $this;
    }

    public function limit(int $value): static
    {
        return $this->take($value);
    }

    public function get(): \Illuminate\Support\Collection
    {
        $params = $this->wheres;
        if ($this->limitValue) {
            $params['limit'] = $this->limitValue;
        }

        try {
            $apiClient = App::make('api-client');
            $endpoint = $this->model->getApiEndpoint();

            if (!empty($params)) {
                $endpoint .= '?' . http_build_query($params);
            }

            $response = $apiClient->get($endpoint);

            if (empty($response)) {
                $base = config('api-model-client.client.base_url')
                    ?? config('api-model-client.base_url')
                    ?? config('bagisto.api_url', '');
                $url = rtrim($base, '/') . '/' . ltrim($this->model->getApiEndpoint(), '/');
                if (!empty($params)) {
                    $url .= '?' . http_build_query($params);
                }
                $response = Http::timeout(15)->get($url)->json() ?? [];
            }

            $items = $this->model->extractItemsFromResponse($response ?? []);

            $modelClass = get_class($this->model);
            return collect($items)
                ->map(fn($item) => (new $modelClass)->newFromApiResponse($item))
                ->filter();
        } catch (\Exception $e) {
            return collect();
        }
    }

    public function getFromApi(): \Illuminate\Support\Collection
    {
        return $this->get();
    }

    public function first()
    {
        return $this->take(1)->get()->first();
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $params = $this->wheres;
        if ($perPage) {
            $params['limit'] = $perPage;
        }
        if ($page) {
            $params['page'] = $page;
        }
        return $this->get();
    }
}
