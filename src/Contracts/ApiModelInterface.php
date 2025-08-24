<?php

namespace MTechStack\LaravelApiModelClient\Contracts;

interface ApiModelInterface
{
    /**
     * Get the API endpoint for this model.
     *
     * @return string
     */
    public function getApiEndpoint(): string;

    /**
     * Get the primary key for API requests.
     *
     * @return string
     */
    public function getApiKeyName(): string;

    /**
     * Determine if the model should merge API data with local database data.
     *
     * @return bool
     */
    public function shouldMergeWithDatabase(): bool;

    /**
     * Get the model from the API by its primary key.
     *
     * @param mixed $id
     * @return static|null
     */
    public static function findFromApi($id);

    /**
     * Get all models from the API.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function allFromApi();

    /**
     * Save the model to the API.
     *
     * @return bool
     */
    public function saveToApi();

    /**
     * Delete the model from the API.
     *
     * @return bool
     */
    public function deleteFromApi();
}
