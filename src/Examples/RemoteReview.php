<?php

namespace MTechStack\LaravelApiModelClient\Examples;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\SyncWithApi;

class RemoteReview extends ApiModel
{
    use SyncWithApi;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = 'reviews';

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
        'product_id',
        'user_id',
        'rating',
        'title',
        'comment',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'rating' => 'integer',
        'approved' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The API field mapping.
     *
     * @var array
     */
    protected $apiFieldMapping = [
        'review_title' => 'title',
        'review_comment' => 'comment',
        'review_rating' => 'rating',
        'product_id' => 'product_id',
        'user_id' => 'user_id',
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
     * Get the product that the review belongs to.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\BelongsToFromApi
     */
    public function product()
    {
        return $this->belongsToFromApi(RemoteProduct::class, 'product_id');
    }

    /**
     * Get the user that wrote the review.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\BelongsToFromApi
     */
    public function user()
    {
        return $this->belongsToFromApi(RemoteUser::class, 'user_id');
    }

    /**
     * Scope a query to only include approved reviews.
     *
     * @param \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder $query
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    /**
     * Scope a query to only include reviews with a minimum rating.
     *
     * @param \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder $query
     * @param int $rating
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public function scopeMinRating($query, $rating)
    {
        return $query->where('rating', '>=', $rating);
    }
}
