<?php

namespace App\Models;

use ApiModelRelations\Models\ApiModel;
use ApiModelRelations\Traits\HasApiRelationships;

class Comment extends ApiModel
{
    use HasApiRelationships;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = '/comments';

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
        'postId' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'body',
        'postId',
    ];

    /**
     * Cache TTL in seconds.
     *
     * @var int
     */
    protected $cacheTtl = 1800; // 30 minutes

    /**
     * Get the post that owns the comment.
     *
     * @return \ApiModelRelations\Relations\BelongsToFromApi
     */
    public function post()
    {
        return $this->belongsToFromApi(Post::class, 'postId');
    }
}
