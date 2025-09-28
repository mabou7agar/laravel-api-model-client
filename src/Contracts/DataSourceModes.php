<?php

namespace MTechStack\LaravelApiModelClient\Contracts;

/**
 * Data Source Mode Constants
 *
 * Defines the available data source modes for hybrid data management.
 */
interface DataSourceModes
{
    /**
     * Data source modes
     */
    const MODE_API_ONLY = 'api_only';
    const MODE_DB_ONLY = 'db_only';
    const MODE_HYBRID = 'hybrid';
    const MODE_API_FIRST = 'api_first';
    const MODE_DUAL_SYNC = 'dual_sync';
}
