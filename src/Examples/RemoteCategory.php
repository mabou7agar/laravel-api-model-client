<?php

namespace ApiModelRelations\Examples;

use ApiModelRelations\Models\ApiModel;
use ApiModelRelations\Traits\SyncWithApi;

class RemoteCategory extends ApiModel
{
    use SyncWithApi;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = 'categories';

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
        'slug',
        'parent_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
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
        'category_name' => 'name',
        'category_description' => 'description',
        'category_slug' => 'slug',
        'parent_category_id' => 'parent_id',
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
    protected $cacheTtl = 7200; // 2 hours

    /**
     * Get the parent category.
     *
     * @return \ApiModelRelations\Relations\BelongsToFromApi
     */
    public function parent()
    {
        return $this->belongsToFromApi(RemoteCategory::class, 'parent_id');
    }

    /**
     * Get the child categories.
     *
     * @return \ApiModelRelations\Relations\HasManyFromApi
     */
    public function children()
    {
        return $this->hasManyFromApi(RemoteCategory::class, 'parent_id');
    }

    /**
     * Get the products in this category.
     *
     * @return \ApiModelRelations\Relations\HasManyFromApi
     */
    public function products()
    {
        return $this->hasManyFromApi(RemoteProduct::class, 'category_id');
    }

    /**
     * Scope a query to only include active categories.
     *
     * @param \ApiModelRelations\Query\ApiQueryBuilder $query
     * @return \ApiModelRelations\Query\ApiQueryBuilder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include root categories.
     *
     * @param \ApiModelRelations\Query\ApiQueryBuilder $query
     * @return \ApiModelRelations\Query\ApiQueryBuilder
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
