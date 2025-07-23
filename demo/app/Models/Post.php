<?php

namespace App\Models;

use ApiModelRelations\Models\ApiModel;
use ApiModelRelations\Traits\HasApiRelationships;

class Post extends ApiModel
{
    use HasApiRelationships;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = '/posts';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'userId' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'body',
        'userId',
    ];

    /**
     * Cache TTL in seconds.
     *
     * @var int
     */
    protected $cacheTtl = 1800; // 30 minutes

    /**
     * Get the user that owns the post.
     *
     * @return \ApiModelRelations\Relations\BelongsToFromApi
     */
    public function user()
    {
        return $this->belongsToFromApi(User::class, 'userId');
    }

    /**
     * Get the comments for the post.
     *
     * @return \ApiModelRelations\Relations\HasManyFromApi
     */
    public function comments()
    {
        return $this->hasManyFromApi(Comment::class, 'postId');
    }
}
