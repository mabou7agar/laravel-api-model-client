<?php

namespace App\Models;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasApiRelationships;

class User extends ApiModel
{
    use HasApiRelationships;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = '/users';

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
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'website',
    ];

    /**
     * Cache TTL in seconds.
     *
     * @var int
     */
    protected $cacheTtl = 3600; // 1 hour

    /**
     * Get the posts for the user.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\HasManyFromApi
     */
    public function posts()
    {
        return $this->hasManyFromApi(Post::class, 'userId');
    }

    /**
     * Get the todos for the user.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\HasManyFromApi
     */
    public function todos()
    {
        return $this->hasManyFromApi(Todo::class, 'userId');
    }

    /**
     * Get the albums for the user.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\HasManyFromApi
     */
    public function albums()
    {
        return $this->hasManyFromApi(Album::class, 'userId');
    }

    /**
     * Get the photos for the user through albums.
     *
     * @return \MTechStack\LaravelApiModelClient\Relations\HasManyThroughFromApi
     */
    public function photos()
    {
        return $this->hasManyThroughFromApi(Photo::class, Album::class, 'userId', 'albumId', 'id');
    }
}
