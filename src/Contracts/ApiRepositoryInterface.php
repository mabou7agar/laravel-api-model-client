<?php

namespace ApiModelRelations\Contracts;

use Illuminate\Support\Collection;

interface ApiRepositoryInterface
{
    /**
     * Get all resources from the API.
     *
     * @param array $params
     * @return \Illuminate\Support\Collection
     */
    public function all(array $params = []): Collection;

    /**
     * Find a resource by its ID.
     *
     * @param mixed $id
     * @param array $params
     * @return array|null
     */
    public function find($id, array $params = []): ?array;

    /**
     * Create a new resource in the API.
     *
     * @param array $data
     * @return array
     */
    public function create(array $data): array;

    /**
     * Update a resource in the API.
     *
     * @param mixed $id
     * @param array $data
     * @return array
     */
    public function update($id, array $data): array;

    /**
     * Delete a resource from the API.
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id): bool;

    /**
     * Get resources related to the specified resource.
     *
     * @param mixed $id
     * @param string $relation
     * @param array $params
     * @return \Illuminate\Support\Collection
     */
    public function getRelated($id, string $relation, array $params = []): Collection;

    /**
     * Get the API endpoint for this repository.
     *
     * @return string
     */
    public function getEndpoint(): string;

    /**
     * Set the API endpoint for this repository.
     *
     * @param string $endpoint
     * @return $this
     */
    public function setEndpoint(string $endpoint);

    /**
     * Get the API client instance.
     *
     * @return \ApiModelRelations\Contracts\ApiClientInterface
     */
    public function getApiClient(): ApiClientInterface;

    /**
     * Set the API client instance.
     *
     * @param \ApiModelRelations\Contracts\ApiClientInterface $client
     * @return $this
     */
    public function setApiClient(ApiClientInterface $client);
}
