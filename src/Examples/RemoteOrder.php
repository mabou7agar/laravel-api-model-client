<?php

namespace MTechStack\LaravelApiModelClient\Examples;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\SyncWithApi;

class RemoteOrder extends ApiModel
{
    use SyncWithApi;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = 'orders';

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
        'user_id',
        'status',
        'total',
        'shipping_address',
        'billing_address',
        'payment_method',
        'shipping_method',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'total' => 'float',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The API field mapping.
     *
     * @var array
     */
    protected $apiFieldMapping = [
        'order_id' => 'id',
        'order_status' => 'status',
        'order_total' => 'total',
        'customer_id' => 'user_id',
        'shipping_address_data' => 'shipping_address',
        'billing_address_data' => 'billing_address',
        'payment_method_code' => 'payment_method',
        'shipping_method_code' => 'shipping_method',
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
     * Get the user that placed the order.
     *
     * @return \ApiModelRelations\Relations\BelongsToFromApi
     */
    public function user()
    {
        return $this->belongsToFromApi(RemoteUser::class, 'user_id');
    }

    /**
     * Get the order items.
     *
     * @return \ApiModelRelations\Relations\HasManyFromApi
     */
    public function items()
    {
        return $this->hasManyFromApi(RemoteOrderItem::class, 'order_id');
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

    /**
     * Scope a query to only include orders with a specific status.
     *
     * @param \ApiModelRelations\Query\ApiQueryBuilder $query
     * @param string $status
     * @return \ApiModelRelations\Query\ApiQueryBuilder
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include completed orders.
     *
     * @param \ApiModelRelations\Query\ApiQueryBuilder $query
     * @return \ApiModelRelations\Query\ApiQueryBuilder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include pending orders.
     *
     * @param \ApiModelRelations\Query\ApiQueryBuilder $query
     * @return \ApiModelRelations\Query\ApiQueryBuilder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
