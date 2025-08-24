<?php

namespace MTechStack\LaravelApiModelClient\Examples;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\SyncWithApi;

class RemoteOrderItem extends ApiModel
{
    use SyncWithApi;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = 'order-items';

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
        'order_id',
        'product_id',
        'quantity',
        'price',
        'total',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'total' => 'float',
        'options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The API field mapping.
     *
     * @var array
     */
    protected $apiFieldMapping = [
        'item_id' => 'id',
        'order_id' => 'order_id',
        'product_id' => 'product_id',
        'item_quantity' => 'quantity',
        'item_price' => 'price',
        'item_total' => 'total',
        'item_options' => 'options',
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
    protected $cacheTtl = 1800; // 30 minutes

    /**
     * Get the order that the item belongs to.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\BelongsToFromApi
     */
    public function order()
    {
        return $this->belongsToFromApi(RemoteOrder::class, 'order_id');
    }

    /**
     * Get the product for this order item.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\BelongsToFromApi
     */
    public function product()
    {
        return $this->belongsToFromApi(RemoteProduct::class, 'product_id');
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
     * Get the formatted total.
     *
     * @return string
     */
    public function getFormattedTotalAttribute()
    {
        return '$' . number_format($this->total, 2);
    }
}
