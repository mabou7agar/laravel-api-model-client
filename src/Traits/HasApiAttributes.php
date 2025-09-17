<?php

namespace MTechStack\LaravelApiModelClient\Traits;

trait HasApiAttributes
{
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $apiCasts = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $apiHidden = [];

    /**
     * The attributes that should be visible in serialization.
     *
     * @var array
     */
    protected $apiVisible = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $apiAppends = [];

    /**
     * The attributes that are mass assignable for API operations.
     *
     * @var array
     */
    protected $apiFillable = [];

    /**
     * The attributes that aren't mass assignable for API operations.
     *
     * @var array
     */
    protected $apiGuarded = ['*'];

    /**
     * Mapping of API field names to model attribute names.
     *
     * @var array
     */
    protected $apiFieldMapping = [];

    /**
     * Get the attributes that should be cast for API operations.
     *
     * @return array
     */
    public function getApiCasts()
    {
        return $this->apiCasts;
    }

    /**
     * Get the attributes that should be hidden for API operations.
     *
     * @return array
     */
    public function getApiHidden()
    {
        return $this->apiHidden;
    }

    /**
     * Get the attributes that should be visible for API operations.
     *
     * @return array
     */
    public function getApiVisible()
    {
        return $this->apiVisible;
    }

    /**
     * Get the accessors to append for API operations.
     *
     * @return array
     */
    public function getApiAppends()
    {
        return $this->apiAppends;
    }

    /**
     * Get the fillable attributes for API operations.
     *
     * @return array
     */
    public function getApiFillable()
    {
        return $this->apiFillable;
    }

    /**
     * Get the guarded attributes for API operations.
     *
     * @return array
     */
    public function getApiGuarded()
    {
        return $this->apiGuarded;
    }

    /**
     * Get the field mapping for API operations.
     *
     * @return array
     */
    public function getApiFieldMapping()
    {
        return $this->apiFieldMapping;
    }

    /**
     * Map API response data to model attributes.
     *
     * @param array $data
     * @return array
     */
    protected function mapApiResponseToAttributes(array $data)
    {
        $attributes = [];
        $mapping = $this->getApiFieldMapping();

        foreach ($data as $apiField => $value) {
            // If there's a mapping for this field, use it; otherwise, use the original field name
            $attributeName = $mapping[$apiField] ?? $apiField;
            $attributes[$attributeName] = $value;
        }
        // CRITICAL FIX: Ensure nested data structures are preserved
        // Handle nested data (like data.variants from API responses)
        if (isset($data['data']) && is_array($data['data'])) {
            // Store the complete nested data structure
            $attributes['data'] = $data['data'];

            // CRITICAL FIX: Flatten ALL nested fields for easier access (both scalar and array fields)
            // This ensures basic fields like id, name, type are accessible as $model->id
            foreach ($data['data'] as $nestedKey => $nestedValue) {
                // Only set if not already mapped to avoid conflicts
                if (!isset($attributes[$nestedKey])) {
                    $attributes[$nestedKey] = $nestedValue;
                }
            }
        }

        // Preserve important top-level arrays that might be filtered out
        $importantFields = ['variants', 'images', 'videos', 'reviews', 'super_attributes', 'base_image', 'formatted_price', 'in_stock', 'is_saved', 'show_quantity_changer'];
        foreach ($importantFields as $field) {
            if (isset($data[$field]) && !isset($attributes[$field])) {
                $attributes[$field] = $data[$field];
            }
        }

        return $attributes;
    }

    /**
     * Map model attributes to API request data.
     *
     * @param array $attributes
     * @return array
     */
    protected function mapAttributesToApiRequest(array $attributes)
    {
        $data = [];
        $mapping = array_flip($this->getApiFieldMapping());

        foreach ($attributes as $attribute => $value) {
            // If there's a mapping for this attribute, use it; otherwise, use the original attribute name
            $apiField = $mapping[$attribute] ?? $attribute;
            $data[$apiField] = $value;
        }

        return $data;
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function castApiAttribute($key, $value)
    {
        $casts = $this->getApiCasts();

        if (!array_key_exists($key, $casts)) {
            return $value;
        }

        $castType = $casts[$key];

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
            case 'json':
                return is_array($value) ? $value : json_decode($value, true);
            case 'object':
                return is_object($value) ? $value : json_decode($value);
            case 'date':
            case 'datetime':
                return $this->asDateTime($value);
            default:
                return $value;
        }
    }

    /**
     * Cast API response data to their appropriate types.
     *
     * @param array $data
     * @return array
     */
    protected function castApiResponseData(array $data)
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->castApiAttribute($key, $value);
        }

        return $data;
    }
}
