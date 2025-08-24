<?php

namespace MTechStack\LaravelApiModelClient\Examples;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\SyncWithApi;

class RemoteUser extends ApiModel
{
    use SyncWithApi;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = 'users';

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
        'email',
        'username',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'active' => 'boolean',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The API field mapping.
     *
     * @var array
     */
    protected $apiFieldMapping = [
        'user_name' => 'name',
        'user_email' => 'email',
        'user_username' => 'username',
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
     * Get the reviews written by the user.
     *
     * @return \ApiModelRelations\Relations\HasManyFromApi
     */
    public function reviews()
    {
        return $this->hasManyFromApi(RemoteReview::class, 'user_id');
    }

    /**
     * Get the orders placed by the user.
     *
     * @return \ApiModelRelations\Relations\HasManyFromApi
     */
    public function orders()
    {
        return $this->hasManyFromApi(RemoteOrder::class, 'user_id');
    }

    /**
     * Scope a query to only include active users.
     *
     * @param \ApiModelRelations\Query\ApiQueryBuilder $query
     * @return \ApiModelRelations\Query\ApiQueryBuilder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include verified users.
     *
     * @param \ApiModelRelations\Query\ApiQueryBuilder $query
     * @return \ApiModelRelations\Query\ApiQueryBuilder
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }
}
