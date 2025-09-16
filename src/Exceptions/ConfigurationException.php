<?php

namespace MTechStack\LaravelApiModelClient\Exceptions;

use Exception;

/**
 * Exception thrown when configuration validation fails
 */
class ConfigurationException extends Exception
{
    protected array $errors = [];
    protected array $warnings = [];

    public function __construct(string $message = '', array $errors = [], array $warnings = [], int $code = 0, ?Exception $previous = null)
    {
        $this->errors = $errors;
        $this->warnings = $warnings;
        
        if (empty($message) && !empty($errors)) {
            $message = 'Configuration validation failed: ' . implode(', ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= ' and ' . (count($errors) - 3) . ' more errors';
            }
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if there are any errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are any warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get total count of issues
     */
    public function getTotalIssues(): int
    {
        return count($this->errors) + count($this->warnings);
    }

    /**
     * Create exception from validation result
     */
    public static function fromValidationResult(array $result): self
    {
        return new self(
            $result['message'] ?? '',
            $result['errors'] ?? [],
            $result['warnings'] ?? []
        );
    }
}
