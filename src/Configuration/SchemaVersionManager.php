<?php

namespace MTechStack\LaravelApiModelClient\Configuration;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\Exceptions\SchemaVersionException;

/**
 * Schema versioning and migration manager for OpenAPI schemas
 */
class SchemaVersionManager
{
    protected string $storagePath;
    protected array $config;
    protected OpenApiSchemaParser $parser;

    public function __construct()
    {
        $this->config = Config::get('api-client.versioning', []);
        $this->storagePath = $this->config['storage_path'] ?? storage_path('api-client/schemas');
        $this->parser = new OpenApiSchemaParser();
        
        $this->ensureStorageDirectoryExists();
    }

    /**
     * Create a new version of a schema
     */
    public function createVersion(string $schemaName, string $schemaContent, ?string $version = null): string
    {
        if (!$this->isVersioningEnabled()) {
            throw new SchemaVersionException('Schema versioning is disabled');
        }

        $version = $version ?? $this->generateVersionIdentifier();
        $versionPath = $this->getVersionPath($schemaName, $version);

        // Create schema directory if it doesn't exist
        $schemaDir = dirname($versionPath);
        if (!File::isDirectory($schemaDir)) {
            File::makeDirectory($schemaDir, 0755, true);
        }

        // Validate schema before storing
        $this->validateSchema($schemaContent);

        // Store the schema version
        File::put($versionPath, $schemaContent);

        // Create metadata file
        $this->createVersionMetadata($schemaName, $version, $schemaContent);

        // Cleanup old versions if retention is enabled
        $this->cleanupOldVersions($schemaName);

        Log::info("Created schema version", [
            'schema' => $schemaName,
            'version' => $version,
            'path' => $versionPath,
        ]);

        return $version;
    }

    /**
     * Get a specific version of a schema
     */
    public function getVersion(string $schemaName, string $version): ?string
    {
        $versionPath = $this->getVersionPath($schemaName, $version);
        
        if (!File::exists($versionPath)) {
            return null;
        }

        return File::get($versionPath);
    }

    /**
     * Get the latest version of a schema
     */
    public function getLatestVersion(string $schemaName): ?array
    {
        $versions = $this->listVersions($schemaName);
        
        if (empty($versions)) {
            return null;
        }

        $latestVersion = end($versions);
        $content = $this->getVersion($schemaName, $latestVersion['version']);
        
        return [
            'version' => $latestVersion['version'],
            'content' => $content,
            'metadata' => $latestVersion,
        ];
    }

    /**
     * List all versions of a schema
     */
    public function listVersions(string $schemaName): array
    {
        $schemaDir = $this->getSchemaDirectory($schemaName);
        
        if (!File::isDirectory($schemaDir)) {
            return [];
        }

        $versions = [];
        $files = File::files($schemaDir);

        foreach ($files as $file) {
            if ($file->getExtension() === 'json' || $file->getExtension() === 'yaml' || $file->getExtension() === 'yml') {
                $version = $file->getFilenameWithoutExtension();
                $metadata = $this->getVersionMetadata($schemaName, $version);
                
                $versions[] = [
                    'version' => $version,
                    'created_at' => $metadata['created_at'] ?? null,
                    'size' => $file->getSize(),
                    'path' => $file->getPathname(),
                    'metadata' => $metadata,
                ];
            }
        }

        // Sort by creation time
        usort($versions, function ($a, $b) {
            return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
        });

        return $versions;
    }

    /**
     * Compare two schema versions
     */
    public function compareVersions(string $schemaName, string $version1, string $version2): array
    {
        $schema1 = $this->getVersion($schemaName, $version1);
        $schema2 = $this->getVersion($schemaName, $version2);

        if (!$schema1 || !$schema2) {
            throw new SchemaVersionException('One or both schema versions not found');
        }

        $compareStrategy = $this->config['compare_strategy'] ?? 'hash';

        switch ($compareStrategy) {
            case 'hash':
                return $this->compareByHash($schema1, $schema2, $version1, $version2);
            
            case 'content':
                return $this->compareByContent($schema1, $schema2, $version1, $version2);
            
            case 'timestamp':
                return $this->compareByTimestamp($schemaName, $version1, $version2);
            
            default:
                throw new SchemaVersionException("Invalid compare strategy: {$compareStrategy}");
        }
    }

    /**
     * Migrate from one schema version to another
     */
    public function migrate(string $schemaName, string $fromVersion, string $toVersion): array
    {
        if (!$this->isVersioningEnabled()) {
            throw new SchemaVersionException('Schema versioning is disabled');
        }

        $migrationStrategy = $this->config['migration_strategy'] ?? 'backup_and_replace';
        
        switch ($migrationStrategy) {
            case 'backup_and_replace':
                return $this->migrateBackupAndReplace($schemaName, $fromVersion, $toVersion);
            
            case 'merge':
                return $this->migrateMerge($schemaName, $fromVersion, $toVersion);
            
            case 'manual':
                return $this->migrateManual($schemaName, $fromVersion, $toVersion);
            
            default:
                throw new SchemaVersionException("Invalid migration strategy: {$migrationStrategy}");
        }
    }

    /**
     * Backup current schema and replace with new version
     */
    protected function migrateBackupAndReplace(string $schemaName, string $fromVersion, string $toVersion): array
    {
        // Create backup of current version
        $backupVersion = $this->createBackup($schemaName, $fromVersion);
        
        // Get new schema content
        $newContent = $this->getVersion($schemaName, $toVersion);
        if (!$newContent) {
            throw new SchemaVersionException("Target version {$toVersion} not found");
        }

        // Validate new schema
        $this->validateSchema($newContent);

        // Update current schema configuration
        $this->updateSchemaConfiguration($schemaName, $newContent);

        Log::info("Schema migration completed", [
            'schema' => $schemaName,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'backup_version' => $backupVersion,
            'strategy' => 'backup_and_replace',
        ]);

        return [
            'success' => true,
            'strategy' => 'backup_and_replace',
            'backup_version' => $backupVersion,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
        ];
    }

    /**
     * Merge schemas (basic implementation)
     */
    protected function migrateMerge(string $schemaName, string $fromVersion, string $toVersion): array
    {
        // This is a basic merge implementation
        // In a real-world scenario, you'd want more sophisticated merging logic
        
        $fromContent = $this->getVersion($schemaName, $fromVersion);
        $toContent = $this->getVersion($schemaName, $toVersion);

        if (!$fromContent || !$toContent) {
            throw new SchemaVersionException('Source or target version not found');
        }

        $fromSchema = json_decode($fromContent, true);
        $toSchema = json_decode($toContent, true);

        if (!$fromSchema || !$toSchema) {
            throw new SchemaVersionException('Invalid JSON in schema versions');
        }

        // Simple merge: use target schema as base and preserve some from source
        $mergedSchema = array_merge($fromSchema, $toSchema);
        
        // Create new version with merged content
        $mergedVersion = $this->generateVersionIdentifier() . '_merged';
        $mergedContent = json_encode($mergedSchema, JSON_PRETTY_PRINT);
        
        $this->createVersion($schemaName, $mergedContent, $mergedVersion);

        Log::info("Schema merge migration completed", [
            'schema' => $schemaName,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'merged_version' => $mergedVersion,
            'strategy' => 'merge',
        ]);

        return [
            'success' => true,
            'strategy' => 'merge',
            'merged_version' => $mergedVersion,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
        ];
    }

    /**
     * Manual migration (returns instructions)
     */
    protected function migrateManual(string $schemaName, string $fromVersion, string $toVersion): array
    {
        $comparison = $this->compareVersions($schemaName, $fromVersion, $toVersion);
        
        return [
            'success' => false,
            'strategy' => 'manual',
            'message' => 'Manual migration required',
            'instructions' => [
                'Review the differences between versions',
                'Manually update your schema configuration',
                'Test the changes in a development environment',
                'Apply changes to production when ready',
            ],
            'comparison' => $comparison,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
        ];
    }

    /**
     * Create a backup of a schema version
     */
    public function createBackup(string $schemaName, string $version): string
    {
        if (!$this->isBackupEnabled()) {
            throw new SchemaVersionException('Schema backup is disabled');
        }

        $content = $this->getVersion($schemaName, $version);
        if (!$content) {
            throw new SchemaVersionException("Version {$version} not found for backup");
        }

        $backupVersion = $version . '_backup_' . $this->generateVersionIdentifier();
        $this->createVersion($schemaName, $content, $backupVersion);

        return $backupVersion;
    }

    /**
     * Restore a schema from backup
     */
    public function restoreFromBackup(string $schemaName, string $backupVersion): array
    {
        $content = $this->getVersion($schemaName, $backupVersion);
        if (!$content) {
            throw new SchemaVersionException("Backup version {$backupVersion} not found");
        }

        // Validate backup content
        $this->validateSchema($content);

        // Update current schema configuration
        $this->updateSchemaConfiguration($schemaName, $content);

        Log::info("Schema restored from backup", [
            'schema' => $schemaName,
            'backup_version' => $backupVersion,
        ]);

        return [
            'success' => true,
            'restored_from' => $backupVersion,
            'schema' => $schemaName,
        ];
    }

    /**
     * Delete old versions based on retention policy
     */
    public function cleanupOldVersions(string $schemaName): int
    {
        if (!$this->isBackupEnabled()) {
            return 0;
        }

        $retentionDays = $this->config['backup_retention_days'] ?? 30;
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        
        $versions = $this->listVersions($schemaName);
        $deletedCount = 0;

        foreach ($versions as $version) {
            $createdAt = $version['created_at'] ? Carbon::parse($version['created_at']) : null;
            
            if ($createdAt && $createdAt->lt($cutoffDate)) {
                $this->deleteVersion($schemaName, $version['version']);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            Log::info("Cleaned up old schema versions", [
                'schema' => $schemaName,
                'deleted_count' => $deletedCount,
                'retention_days' => $retentionDays,
            ]);
        }

        return $deletedCount;
    }

    /**
     * Delete a specific version
     */
    public function deleteVersion(string $schemaName, string $version): bool
    {
        $versionPath = $this->getVersionPath($schemaName, $version);
        $metadataPath = $this->getVersionMetadataPath($schemaName, $version);

        $deleted = false;

        if (File::exists($versionPath)) {
            File::delete($versionPath);
            $deleted = true;
        }

        if (File::exists($metadataPath)) {
            File::delete($metadataPath);
        }

        return $deleted;
    }

    /**
     * Generate a version identifier
     */
    protected function generateVersionIdentifier(): string
    {
        $format = $this->config['version_format'] ?? 'Y-m-d_H-i-s';
        return Carbon::now()->format($format);
    }

    /**
     * Get the path for a specific schema version
     */
    protected function getVersionPath(string $schemaName, string $version): string
    {
        return $this->getSchemaDirectory($schemaName) . "/{$version}.json";
    }

    /**
     * Get the directory for a schema
     */
    protected function getSchemaDirectory(string $schemaName): string
    {
        return $this->storagePath . "/{$schemaName}";
    }

    /**
     * Create version metadata
     */
    protected function createVersionMetadata(string $schemaName, string $version, string $content): void
    {
        $metadata = [
            'version' => $version,
            'schema_name' => $schemaName,
            'created_at' => Carbon::now()->toISOString(),
            'size' => strlen($content),
            'hash' => hash('sha256', $content),
            'php_version' => PHP_VERSION,
            'package_version' => $this->getPackageVersion(),
        ];

        $metadataPath = $this->getVersionMetadataPath($schemaName, $version);
        File::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Get version metadata
     */
    protected function getVersionMetadata(string $schemaName, string $version): array
    {
        $metadataPath = $this->getVersionMetadataPath($schemaName, $version);
        
        if (!File::exists($metadataPath)) {
            return [];
        }

        $content = File::get($metadataPath);
        return json_decode($content, true) ?: [];
    }

    /**
     * Get the path for version metadata
     */
    protected function getVersionMetadataPath(string $schemaName, string $version): string
    {
        return $this->getSchemaDirectory($schemaName) . "/{$version}.meta.json";
    }

    /**
     * Validate schema content
     */
    protected function validateSchema(string $content): void
    {
        try {
            // Try to parse as JSON first
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new SchemaVersionException('Invalid JSON: ' . json_last_error_msg());
            }

            // Validate OpenAPI structure
            if (!isset($decoded['openapi']) && !isset($decoded['swagger'])) {
                throw new SchemaVersionException('Not a valid OpenAPI/Swagger schema');
            }

        } catch (\Exception $e) {
            throw new SchemaVersionException("Schema validation failed: {$e->getMessage()}");
        }
    }

    /**
     * Update schema configuration with new content
     */
    protected function updateSchemaConfiguration(string $schemaName, string $content): void
    {
        // This would update the actual schema configuration
        // Implementation depends on how schemas are stored and used
        // For now, we'll just log the action
        
        Log::info("Schema configuration updated", [
            'schema' => $schemaName,
            'content_size' => strlen($content),
        ]);
    }

    /**
     * Compare schemas by hash
     */
    protected function compareByHash(string $schema1, string $schema2, string $version1, string $version2): array
    {
        $hash1 = hash('sha256', $schema1);
        $hash2 = hash('sha256', $schema2);

        return [
            'method' => 'hash',
            'identical' => $hash1 === $hash2,
            'version1' => ['version' => $version1, 'hash' => $hash1],
            'version2' => ['version' => $version2, 'hash' => $hash2],
        ];
    }

    /**
     * Compare schemas by content
     */
    protected function compareByContent(string $schema1, string $schema2, string $version1, string $version2): array
    {
        $json1 = json_decode($schema1, true);
        $json2 = json_decode($schema2, true);

        $differences = [];
        
        if ($json1 && $json2) {
            $differences = $this->findArrayDifferences($json1, $json2);
        }

        return [
            'method' => 'content',
            'identical' => empty($differences),
            'differences' => $differences,
            'version1' => $version1,
            'version2' => $version2,
        ];
    }

    /**
     * Compare schemas by timestamp
     */
    protected function compareByTimestamp(string $schemaName, string $version1, string $version2): array
    {
        $meta1 = $this->getVersionMetadata($schemaName, $version1);
        $meta2 = $this->getVersionMetadata($schemaName, $version2);

        $time1 = isset($meta1['created_at']) ? Carbon::parse($meta1['created_at']) : null;
        $time2 = isset($meta2['created_at']) ? Carbon::parse($meta2['created_at']) : null;

        return [
            'method' => 'timestamp',
            'version1' => ['version' => $version1, 'created_at' => $time1?->toISOString()],
            'version2' => ['version' => $version2, 'created_at' => $time2?->toISOString()],
            'newer_version' => $time1 && $time2 ? ($time1->gt($time2) ? $version1 : $version2) : null,
        ];
    }

    /**
     * Find differences between two arrays
     */
    protected function findArrayDifferences(array $array1, array $array2, string $path = ''): array
    {
        $differences = [];

        // Check for keys in array1 that are not in array2 or have different values
        foreach ($array1 as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : $key;
            
            if (!array_key_exists($key, $array2)) {
                $differences[] = [
                    'type' => 'removed',
                    'path' => $currentPath,
                    'old_value' => $value,
                ];
            } elseif (is_array($value) && is_array($array2[$key])) {
                $nestedDiffs = $this->findArrayDifferences($value, $array2[$key], $currentPath);
                $differences = array_merge($differences, $nestedDiffs);
            } elseif ($value !== $array2[$key]) {
                $differences[] = [
                    'type' => 'changed',
                    'path' => $currentPath,
                    'old_value' => $value,
                    'new_value' => $array2[$key],
                ];
            }
        }

        // Check for keys in array2 that are not in array1
        foreach ($array2 as $key => $value) {
            if (!array_key_exists($key, $array1)) {
                $currentPath = $path ? "{$path}.{$key}" : $key;
                $differences[] = [
                    'type' => 'added',
                    'path' => $currentPath,
                    'new_value' => $value,
                ];
            }
        }

        return $differences;
    }

    /**
     * Ensure storage directory exists
     */
    protected function ensureStorageDirectoryExists(): void
    {
        if (!File::isDirectory($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }

    /**
     * Check if versioning is enabled
     */
    protected function isVersioningEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Check if backup is enabled
     */
    protected function isBackupEnabled(): bool
    {
        return $this->config['backup_enabled'] ?? true;
    }

    /**
     * Get package version
     */
    protected function getPackageVersion(): string
    {
        // This would typically read from composer.json or a version file
        return '1.0.0';
    }
}
