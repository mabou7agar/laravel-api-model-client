<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait SyncWithApi
{
    /**
     * Sync the local database record with data from the API.
     *
     * @param array|null $apiData Optional API data to use instead of fetching
     * @param bool $save Whether to save the model after syncing
     * @return $this
     */
    public function syncFromApi($apiData = null, $save = true)
    {
        // If no API data provided, fetch it from the API
        if ($apiData === null) {
            try {
                $apiData = $this->fetchFromApi();
            } catch (\Exception $e) {
                if (config('api-model-relations.error_handling.log_errors', true)) {
                    Log::error('Error fetching data from API for sync', [
                        'model' => get_class($this),
                        'id' => $this->getKey(),
                        'exception' => $e->getMessage(),
                    ]);
                }
                
                return $this;
            }
        }

        // If no API data found, return
        if (empty($apiData)) {
            return $this;
        }

        // Map API fields to model attributes
        $this->mapApiDataToAttributes($apiData);

        // Save if requested
        if ($save && $this->exists) {
            $this->save();
        }

        return $this;
    }

    /**
     * Sync the API with data from the local database record.
     *
     * @param array|null $attributes Optional attributes to use instead of model attributes
     * @return bool
     */
    public function syncToApi($attributes = null)
    {
        // If no attributes provided, use the model's attributes
        if ($attributes === null) {
            $attributes = $this->getAttributes();
        }

        // Map model attributes to API fields
        $apiData = $this->mapAttributesToApiData($attributes);

        try {
            // Send data to API
            if ($this->getKey()) {
                // Update existing record
                $response = $this->updateToApi($apiData);
            } else {
                // Create new record
                $response = $this->createInApi($apiData);
                
                // If the API returns an ID, set it on the model
                if (isset($response[$this->getKeyName()])) {
                    $this->{$this->getKeyName()} = $response[$this->getKeyName()];
                }
            }
            
            return true;
        } catch (\Exception $e) {
            if (config('api-model-relations.error_handling.log_errors', true)) {
                Log::error('Error syncing data to API', [
                    'model' => get_class($this),
                    'id' => $this->getKey(),
                    'data' => $apiData,
                    'exception' => $e->getMessage(),
                ]);
            }
            
            return false;
        }
    }

    /**
     * Sync all local records with the API.
     *
     * @param bool $createMissing Whether to create records that exist in API but not locally
     * @param bool $removeOrphaned Whether to remove local records that don't exist in API
     * @return array Statistics about the sync operation
     */
    public static function syncAllWithApi($createMissing = true, $removeOrphaned = false)
    {
        $model = new static;
        $stats = [
            'updated' => 0,
            'created' => 0,
            'deleted' => 0,
            'failed' => 0,
        ];

        try {
            // Get all records from API
            $apiRecords = $model->allFromApi();
            
            // Index API records by primary key for easy lookup
            $apiRecordsById = [];
            $primaryKey = $model->getKeyName();
            
            foreach ($apiRecords as $record) {
                if (isset($record[$primaryKey])) {
                    $apiRecordsById[$record[$primaryKey]] = $record;
                }
            }
            
            // Get all local records
            $localRecords = $model->all();
            $processedIds = [];
            
            // Update existing local records
            foreach ($localRecords as $localRecord) {
                $id = $localRecord->getKey();
                $processedIds[] = $id;
                
                if (isset($apiRecordsById[$id])) {
                    // Record exists in both places, update local from API
                    try {
                        $localRecord->syncFromApi($apiRecordsById[$id]);
                        $stats['updated']++;
                    } catch (\Exception $e) {
                        $stats['failed']++;
                        if (config('api-model-relations.error_handling.log_errors', true)) {
                            Log::error('Error syncing record from API', [
                                'model' => get_class($model),
                                'id' => $id,
                                'exception' => $e->getMessage(),
                            ]);
                        }
                    }
                } elseif ($removeOrphaned) {
                    // Record exists locally but not in API, delete it
                    try {
                        $localRecord->delete();
                        $stats['deleted']++;
                    } catch (\Exception $e) {
                        $stats['failed']++;
                        if (config('api-model-relations.error_handling.log_errors', true)) {
                            Log::error('Error deleting orphaned record', [
                                'model' => get_class($model),
                                'id' => $id,
                                'exception' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
            
            // Create missing records if requested
            if ($createMissing) {
                foreach ($apiRecordsById as $id => $apiRecord) {
                    if (!in_array($id, $processedIds)) {
                        // Record exists in API but not locally, create it
                        try {
                            $newRecord = new static;
                            $newRecord->syncFromApi($apiRecord);
                            $stats['created']++;
                        } catch (\Exception $e) {
                            $stats['failed']++;
                            if (config('api-model-relations.error_handling.log_errors', true)) {
                                Log::error('Error creating record from API', [
                                    'model' => get_class($model),
                                    'id' => $id,
                                    'exception' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            if (config('api-model-relations.error_handling.log_errors', true)) {
                Log::error('Error during bulk sync with API', [
                    'model' => get_class($model),
                    'exception' => $e->getMessage(),
                ]);
            }
        }
        
        return $stats;
    }

    /**
     * Map API data to model attributes.
     *
     * @param array $apiData
     * @return void
     */
    protected function mapApiDataToAttributes(array $apiData)
    {
        // Get field mapping if defined
        $fieldMapping = $this->getApiFieldMapping();
        
        foreach ($apiData as $apiField => $value) {
            // Check if there's a mapping for this field
            $modelField = $fieldMapping[$apiField] ?? $apiField;
            
            // Only set if the field exists on the model
            if ($this->isFillable($modelField) && !$this->isGuarded($modelField)) {
                $this->setAttribute($modelField, $value);
            }
        }
    }

    /**
     * Map model attributes to API data.
     *
     * @param array $attributes
     * @return array
     */
    protected function mapAttributesToApiData(array $attributes)
    {
        $apiData = [];
        
        // Get field mapping if defined
        $fieldMapping = $this->getApiFieldMapping();
        $reverseMapping = array_flip($fieldMapping);
        
        foreach ($attributes as $modelField => $value) {
            // Skip the primary key if it's empty (for new records)
            if ($modelField === $this->getKeyName() && empty($value)) {
                continue;
            }
            
            // Check if there's a mapping for this field
            $apiField = $reverseMapping[$modelField] ?? $modelField;
            
            // Add to API data
            $apiData[$apiField] = $value;
        }
        
        return $apiData;
    }

    /**
     * Get the API field mapping.
     *
     * @return array
     */
    protected function getApiFieldMapping()
    {
        return $this->apiFieldMapping ?? [];
    }

    /**
     * Fetch data from the API for this model.
     *
     * @return array|null
     */
    protected function fetchFromApi()
    {
        $key = $this->getKey();
        
        if (!$key) {
            return null;
        }
        
        return $this->findFromApi($key);
    }
}
