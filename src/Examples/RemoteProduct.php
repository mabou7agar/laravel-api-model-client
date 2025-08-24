<?php

namespace MTechStack\LaravelApiModelClient\Examples;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\SyncWithApi;

class RemoteProduct extends ApiModel
{
    use SyncWithApi;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = 'products';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'sku',
        'stock',
        'category_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'float',
        'stock' => 'integer',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The API field mapping.
     *
     * @var array
     */
    protected $apiFieldMapping = [
        'product_name' => 'name',
        'product_description' => 'description',
        'product_price' => 'price',
        'product_sku' => 'sku',
        'product_stock' => 'stock',
        'category_id' => 'category_id',
    ];

    /**
     * Whether to merge API data with local database data.
     *
     * @var bool
     */
    protected $mergeWithLocalData = true;

    /**
     * The cache TTL in seconds.
     *
     * @var int
     */
    protected $cacheTtl = 3600; // 1 hour

    /**
     * Get the category that the product belongs to.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\BelongsToFromApi
     */
    public function category()
    {
        return $this->belongsToFromApi(RemoteCategory::class, 'category_id');
    }

    /**
     * Get the reviews for the product.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\HasManyFromApi
     */
    public function reviews()
    {
        return $this->hasManyFromApi(RemoteReview::class, 'product_id');
    }

    /**
     * Get the formatted price.
     *
     * @return string
     */
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Scope a query to only include active products.
     *
     * @param \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder $query
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include products with stock.
     *
     * @param \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder $query
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}
