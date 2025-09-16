<?php

namespace MTechStack\LaravelApiModelClient\Exceptions;

use Exception;

/**
 * Exception thrown when schema versioning operations fail
 */
class SchemaVersionException extends Exception
{
    protected ?string $schemaName = null;
    protected ?string $version = null;
    protected ?string $operation = null;

    public function __construct(string $message = '', ?string $schemaName = null, ?string $version = null, ?string $operation = null, int $code = 0, ?Exception $previous = null)
    {
        $this->schemaName = $schemaName;
        $this->version = $version;
        $this->operation = $operation;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the schema name
     */
    public function getSchemaName(): ?string
    {
        return $this->schemaName;
    }

    /**
     * Get the version
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Get the operation
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Create exception for version not found
     */
    public static function versionNotFound(string $schemaName, string $version): self
    {
        return new self(
            "Version '{$version}' not found for schema '{$schemaName}'",
            $schemaName,
            $version,
            'get_version'
        );
    }

    /**
     * Create exception for migration failure
     */
    public static function migrationFailed(string $schemaName, string $fromVersion, string $toVersion, string $reason): self
    {
        return new self(
            "Migration failed from '{$fromVersion}' to '{$toVersion}' for schema '{$schemaName}': {$reason}",
            $schemaName,
            "{$fromVersion} -> {$toVersion}",
            'migrate'
        );
    }

    /**
     * Create exception for backup failure
     */
    public static function backupFailed(string $schemaName, string $version, string $reason): self
    {
        return new self(
            "Backup failed for schema '{$schemaName}' version '{$version}': {$reason}",
            $schemaName,
            $version,
            'backup'
        );
    }

    /**
     * Create exception for validation failure
     */
    public static function validationFailed(string $schemaName, string $reason): self
    {
        return new self(
            "Schema validation failed for '{$schemaName}': {$reason}",
            $schemaName,
            null,
            'validate'
        );
    }
}
