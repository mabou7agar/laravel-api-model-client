<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Model Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the API Model integration.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Queue Operations
    |--------------------------------------------------------------------------
    |
    | When set to true, API operations (save, update, delete) will be queued
    | instead of being executed synchronously. This improves application
    | responsiveness but may introduce eventual consistency.
    |
    */
    'queue_operations' => env('API_MODEL_QUEUE_OPERATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Retry Attempts
    |--------------------------------------------------------------------------
    |
    | The number of times to retry API operations before giving up.
    | This applies to both synchronous operations and queued jobs.
    |
    */
    'retry_attempts' => env('API_MODEL_RETRY_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Sync in Testing Environment
    |--------------------------------------------------------------------------
    |
    | When set to true, API operations will be performed even in the testing
    | environment. By default, API operations are disabled during testing.
    |
    */
    'sync_in_testing' => env('API_MODEL_SYNC_IN_TESTING', false),

    /*
    |--------------------------------------------------------------------------
    | Queue Name
    |--------------------------------------------------------------------------
    |
    | The name of the queue to use for API operations. This allows you to
    | process API operations on a separate queue from other jobs.
    |
    */
    'queue_name' => env('API_MODEL_QUEUE_NAME', 'api-sync'),
];
