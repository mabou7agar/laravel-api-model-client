<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use MTechStack\LaravelApiModelClient\Contracts\DataSourceModes;

/**
 * Hybrid Data Source Trait
 *
 * Provides intelligent switching between database and API data sources
 * based on configuration modes. Overrides all standard Eloquent methods
 * to work seamlessly with both local database and remote API.
 *
 * Modes:
 * - 'api_only': All operations use API exclusively
 * - 'db_only': All operations use database exclusively
 * - 'hybrid': Check database first, fallback to API
 * - 'api_first': Check API first, sync to database
 * - 'dual_sync': Keep both database and API in sync
 */
trait HybridDataSource
{

    /**
     * Get the current data source mode for this model.
     *
     * @return string
     */
    public function getDataSourceMode(): string
    {
        // Check model-specific configuration first
        if (property_exists($this, 'dataSourceMode')) {
            return $this->dataSourceMode;
        }

        // Check model-specific config
        $modelClass = get_class($this);
        $modelName = class_basename($modelClass);
        $configKey = 'api-model-client.models.' . strtolower($modelName) . '.data_source_mode';

        if (Config::has($configKey)) {
            return Config::get($configKey);
        }

        // Check global configuration
        return Config::get('api-model-client.data_source_mode', DataSourceModes::MODE_HYBRID);
    }

    /**
     * Check if database operations are enabled for current mode.
     *
     * @return bool
     */
    protected function isDatabaseEnabled(): bool
    {
        $mode = $this->getDataSourceMode();
        return in_array($mode, [DataSourceModes::MODE_DB_ONLY, DataSourceModes::MODE_HYBRID, DataSourceModes::MODE_DUAL_SYNC]);
    }

    /**
     * Check if API operations are enabled for current mode.
     *
     * @return bool
     */
    protected function isApiEnabled(): bool
    {
        $mode = $this->getDataSourceMode();
        return in_array($mode, [DataSourceModes::MODE_API_ONLY, DataSourceModes::MODE_HYBRID, DataSourceModes::MODE_API_FIRST, DataSourceModes::MODE_DUAL_SYNC]);
    }

    /**
     * Override Eloquent's find method with hybrid logic.
     *
     * @param mixed $id
     * @param array $columns
     * @return static|null
     */
    public static function find($id, $columns = ['*'])
    {
        $instance = new static();
        $mode = $instance->getDataSourceMode();

        switch ($mode) {
            case DataSourceModes::MODE_API_ONLY:
                return static::findFromApiOnly($id, $columns);

            case DataSourceModes::MODE_DB_ONLY:
                return static::findFromDatabase($id, $columns);

            case DataSourceModes::MODE_HYBRID:
                return static::findHybrid($id, $columns);

            case DataSourceModes::MODE_API_FIRST:
                return static::findApiFirst($id, $columns);

            case DataSourceModes::MODE_DUAL_SYNC:
                return static::findDualSync($id, $columns);

            default:
                return static::findHybrid($id, $columns);
        }
    }

    /**
     * Override Eloquent's all method with hybrid logic.
     *
     * @param array $columns
     * @return Collection
     */
    public static function all($columns = ['*'])
    {
        $instance = new static();
        $mode = $instance->getDataSourceMode();

        switch ($mode) {
            case DataSourceModes::MODE_API_ONLY:
                return static::allFromApiOnly($columns);

            case DataSourceModes::MODE_DB_ONLY:
                return static::allFromDatabase($columns);

            case DataSourceModes::MODE_HYBRID:
                return static::allHybrid($columns);

            case DataSourceModes::MODE_API_FIRST:
                return static::allApiFirst($columns);

            case DataSourceModes::MODE_DUAL_SYNC:
                return static::allDualSync($columns);

            default:
                return static::allHybrid($columns);
        }
    }

    /**
     * Override Eloquent's save method with hybrid logic.
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options = [])
    {
        $mode = $this->getDataSourceMode();

        switch ($mode) {
            case DataSourceModes::MODE_API_ONLY:
                return $this->saveToApiOnly($options);

            case DataSourceModes::MODE_DB_ONLY:
                return $this->saveToDatabase($options);

            case DataSourceModes::MODE_HYBRID:
                return $this->saveHybrid($options);

            case DataSourceModes::MODE_API_FIRST:
                return $this->saveApiFirst($options);

            case DataSourceModes::MODE_DUAL_SYNC:
                return $this->saveDualSync($options);

            default:
                return $this->saveHybrid($options);
        }
    }

    /**
     * Override Eloquent's delete method with hybrid logic.
     *
     * @return bool|null
     */
    public function delete()
    {
        $mode = $this->getDataSourceMode();

        switch ($mode) {
            case DataSourceModes::MODE_API_ONLY:
                return $this->deleteFromApiOnly();

            case DataSourceModes::MODE_DB_ONLY:
                return $this->deleteFromDatabase();

            case DataSourceModes::MODE_HYBRID:
                return $this->deleteHybrid();

            case DataSourceModes::MODE_API_FIRST:
                return $this->deleteApiFirst();

            case DataSourceModes::MODE_DUAL_SYNC:
                return $this->deleteDualSync();

            default:
                return $this->deleteHybrid();
        }
    }

    /**
     * Override Eloquent's create method with hybrid logic.
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes = [])
    {
        $instance = new static();
        $mode = $instance->getDataSourceMode();

        switch ($mode) {
            case DataSourceModes::MODE_API_ONLY:
                return static::createInApiOnly($attributes);

            case DataSourceModes::MODE_DB_ONLY:
                return static::createInDatabase($attributes);

            case DataSourceModes::MODE_HYBRID:
                return static::createHybrid($attributes);

            case DataSourceModes::MODE_API_FIRST:
                return static::createApiFirst($attributes);

            case DataSourceModes::MODE_DUAL_SYNC:
                return static::createDualSync($attributes);

            default:
                return static::createHybrid($attributes);
        }
    }

    /**
     * Find using API only.
     *
     * @param mixed $id
     * @param array $columns
     * @return static|null
     */
    protected static function findFromApiOnly($id, $columns = ['*'])
    {
        try {
            return static::findFromApi($id);
        } catch (\Exception $e) {
            Log::error("API find failed for " . static::class . " ID: {$id}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find using hybrid approach (database first, API fallback).
     *
     * @param mixed $id
     * @param array $columns
     * @return static|null
     */
    protected static function findHybrid($id, $columns = ['*'])
    {
        // Try database first using direct Eloquent query to avoid recursion
        $model = static::findFromDatabase($id, $columns);

        if ($model) {
            return $model;
        }

        // Fallback to API
        try {
            $apiModel = static::findFromApi($id);

            if ($apiModel) {
                // Optionally sync to database for future queries
                $apiModel->syncToDatabase();
                return $apiModel;
            }
        } catch (\Exception $e) {
            Log::warning("API fallback failed for " . static::class . " ID: {$id}", [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Find using API first approach.
     *
     * @param mixed $id
     * @param array $columns
     * @return static|null
     */
    protected static function findApiFirst($id, $columns = ['*'])
    {
        try {
            $apiModel = static::findFromApi($id);

            if ($apiModel) {
                // Sync to database
                $apiModel->syncToDatabase();
                return $apiModel;
            }
        } catch (\Exception $e) {
            Log::warning("API first failed for " . static::class . " ID: {$id}", [
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to database
        return static::findFromDatabase($id, $columns);
    }

    /**
     * Find using dual sync approach.
     *
     * @param mixed $id
     * @param array $columns
     * @return static|null
     */
    protected static function findDualSync($id, $columns = ['*'])
    {
        $dbModel = static::findFromDatabase($id, $columns);
        $apiModel = null;

        try {
            $apiModel = static::findFromApi($id);
        } catch (\Exception $e) {
            Log::warning("API sync failed for " . static::class . " ID: {$id}", [
                'error' => $e->getMessage()
            ]);
        }

        // Return the most recent data and sync both sources
        if ($apiModel && $dbModel) {
            // Compare timestamps and return newer data
            $apiUpdated = $apiModel->updated_at ?? $apiModel->created_at;
            $dbUpdated = $dbModel->updated_at ?? $dbModel->created_at;

            if ($apiUpdated > $dbUpdated) {
                $apiModel->syncToDatabase();
                return $apiModel;
            } else {
                $dbModel->syncToApi();
                return $dbModel;
            }
        } elseif ($apiModel) {
            $apiModel->syncToDatabase();
            return $apiModel;
        } elseif ($dbModel) {
            $dbModel->syncToApi();
            return $dbModel;
        }

        return null;
    }

    /**
     * Get all records using API only.
     *
     * @param array $columns
     * @return Collection
     */
    protected static function allFromApiOnly($columns = ['*'])
    {
        try {
            return static::allFromApi();
        } catch (\Exception $e) {
            Log::error("API all failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get all records using hybrid approach.
     *
     * @param array $columns
     * @return Collection
     */
    protected static function allHybrid($columns = ['*'])
    {
        // Try database first
        $dbModels = static::allFromDatabase($columns);

        if ($dbModels->isNotEmpty()) {
            return $dbModels;
        }

        // Fallback to API
        try {
            $apiModels = static::allFromApi();

            if ($apiModels->isNotEmpty()) {
                // Optionally sync to database
                static::syncCollectionToDatabase($apiModels);
                return $apiModels;
            }
        } catch (\Exception $e) {
            Log::warning("API fallback failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        return new Collection();
    }

    /**
     * Get all records using API first approach.
     *
     * @param array $columns
     * @return Collection
     */
    protected static function allApiFirst($columns = ['*'])
    {
        try {
            $apiModels = static::allFromApi();

            if ($apiModels->isNotEmpty()) {
                // Sync to database
                static::syncCollectionToDatabase($apiModels);
                return $apiModels;
            }
        } catch (\Exception $e) {
            Log::warning("API first failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to database
        return static::allFromDatabase($columns);
    }

    /**
     * Get all records using dual sync approach.
     *
     * @param array $columns
     * @return Collection
     */
    protected static function allDualSync($columns = ['*'])
    {
        $dbModels = static::allFromDatabase($columns);
        $apiModels = new Collection();

        try {
            $apiModels = static::allFromApi();
        } catch (\Exception $e) {
            Log::warning("API sync failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        // Merge and sync both collections
        if ($apiModels->isNotEmpty()) {
            static::syncCollectionToDatabase($apiModels);
        }

        if ($dbModels->isNotEmpty()) {
            static::syncCollectionToApi($dbModels);
        }

        // Return the API data as it's typically more current
        return $apiModels->isNotEmpty() ? $apiModels : $dbModels;
    }

    /**
     * Save to API only.
     *
     * @param array $options
     * @return bool
     */
    protected function saveToApiOnly(array $options = [])
    {
        try {
            return $this->saveToApi($options);
        } catch (\Exception $e) {
            Log::error("API save failed for " . static::class, [
                'error' => $e->getMessage(),
                'attributes' => $this->getAttributes()
            ]);
            return false;
        }
    }

    /**
     * Save using hybrid approach.
     *
     * @param array $options
     * @return bool
     */
    protected function saveHybrid(array $options = [])
    {
        $dbSaved = false;
        $apiSaved = false;

        // Try database first
        try {
            $dbSaved = $this->saveToDatabase($options);
        } catch (\Exception $e) {
            Log::warning("Database save failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        // Try API as fallback or additional sync
        try {
            $apiSaved = $this->saveToApi($options);
        } catch (\Exception $e) {
            Log::warning("API save failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        return $dbSaved || $apiSaved;
    }

    /**
     * Save using API first approach.
     *
     * @param array $options
     * @return bool
     */
    protected function saveApiFirst(array $options = [])
    {
        try {
            $apiSaved = $this->saveToApi($options);

            if ($apiSaved) {
                // Sync to database
                $this->saveToDatabase($options);
                return true;
            }
        } catch (\Exception $e) {
            Log::warning("API first save failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to database
        return $this->saveToDatabase($options);
    }

    /**
     * Save using dual sync approach.
     *
     * @param array $options
     * @return bool
     */
    protected function saveDualSync(array $options = [])
    {
        $dbSaved = false;
        $apiSaved = false;

        // Save to both sources
        try {
            $dbSaved = $this->saveToDatabase($options);
        } catch (\Exception $e) {
            Log::error("Database save failed in dual sync for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        try {
            $apiSaved = $this->saveToApi($options);
        } catch (\Exception $e) {
            Log::error("API save failed in dual sync for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        return $dbSaved && $apiSaved;
    }

    /**
     * Sync model to database.
     *
     * @return bool
     */
    protected function syncToDatabase(): bool
    {
        try {
            return $this->saveToDatabase();
        } catch (\Exception $e) {
            Log::error("Database sync failed for " . static::class, [
                'error' => $e->getMessage(),
                'attributes' => $this->getAttributes()
            ]);
            return false;
        }
    }

    /**
     * Sync model to API.
     * Compatible with SyncWithApi trait signature.
     *
     * @param array|null $attributes Optional attributes to use instead of model attributes
     * @return bool
     */
    protected function syncToApi($attributes = null): bool
    {
        try {
            if ($attributes !== null) {
                // Temporarily store current attributes
                $originalAttributes = $this->getAttributes();
                $this->fill($attributes);
                $result = $this->saveToApi();
                // Restore original attributes if save failed
                if (!$result) {
                    $this->fill($originalAttributes);
                }
                return $result;
            }

            return $this->saveToApi();
        } catch (\Exception $e) {
            Log::error("API sync failed for " . static::class, [
                'error' => $e->getMessage(),
                'attributes' => $attributes ?? $this->getAttributes()
            ]);
            return false;
        }
    }

    /**
     * Sync collection to database.
     *
     * @param Collection $models
     * @return void
     */
    protected static function syncCollectionToDatabase(Collection $models): void
    {
        foreach ($models as $model) {
            $model->syncToDatabase();
        }
    }

    /**
     * Sync collection to API.
     *
     * @param Collection $models
     * @return void
     */
    protected static function syncCollectionToApi(Collection $models): void
    {
        foreach ($models as $model) {
            $model->syncToApi();
        }
    }

    /**
     * Find a record from database with table existence check.
     *
     * @param mixed $id
     * @param array $columns
     * @return static|null
     */
    protected static function findFromDatabase($id, $columns = ['*'])
    {
        if (!static::tableExists()) {
            return null;
        }

        try {
            $instance = new static();
            return $instance->newQuery()->find($id, $columns);
        } catch (\Exception $e) {
            Log::warning("Database find failed for " . static::class . " ID: {$id}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all records from database with table existence check.
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected static function allFromDatabase($columns = ['*'])
    {
        if (!static::tableExists()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        try {
            $instance = new static();
            return $instance->newQuery()->get($columns);
        } catch (\Exception $e) {
            Log::warning("Database all failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Database\Eloquent\Collection();
        }
    }

    /**
     * Save model to database only.
     *
     * @param array $options
     * @return bool
     */
    protected function saveToDatabase(array $options = []): bool
    {
        if (!static::tableExists()) {
            return false;
        }

        try {
            // Use Eloquent's save method directly
            return $this->saveToEloquent($options);
        } catch (\Exception $e) {
            Log::warning("Database save failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete model from database only.
     *
     * @return bool
     */
    protected function deleteFromDatabase(): bool
    {
        if (!static::tableExists()) {
            return false;
        }

        try {
            // Use Eloquent's delete method directly
            return $this->deleteFromEloquent();
        } catch (\Exception $e) {
            Log::warning("Database delete failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create model in database only.
     *
     * @param array $attributes
     * @return static|null
     */
    protected static function createInDatabase(array $attributes = [])
    {
        if (!static::tableExists()) {
            return null;
        }

        try {
            $instance = new static($attributes);
            if ($instance->saveToDatabase()) {
                return $instance;
            }
            return null;
        } catch (\Exception $e) {
            Log::warning("Database create failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if the model's table exists in the database.
     *
     * @return bool
     */
    protected static function tableExists(): bool
    {
        try {
            $instance = new static();
            $tableName = $instance->getTable();
            return Schema::hasTable($tableName);
        } catch (\Exception $e) {
            Log::warning("Table existence check failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Save using Eloquent directly (bypassing trait methods).
     *
     * @param array $options
     * @return bool
     */
    protected function saveToEloquent(array $options = []): bool
    {
        // Get the parent class methods directly
        $reflection = new \ReflectionClass(get_parent_class($this));
        if ($reflection->hasMethod('save')) {
            return parent::save($options);
        }

        // Fallback to basic Eloquent save
        return $this->exists ? $this->performUpdate($this->newQuery()) : $this->performInsert($this->newQuery());
    }

    /**
     * Delete using Eloquent directly (bypassing trait methods).
     *
     * @return bool
     */
    protected function deleteFromEloquent(): bool
    {
        // Get the parent class methods directly
        $reflection = new \ReflectionClass(get_parent_class($this));
        if ($reflection->hasMethod('delete')) {
            return parent::delete();
        }

        // Fallback to basic Eloquent delete
        return $this->newQuery()->where($this->getKeyName(), $this->getKey())->delete();
    }

    /**
     * These methods should be implemented by the API model.
     * Since ApiModel already has public implementations, we don't need abstract methods.
     * The trait will use the existing public methods directly.
     */
}
