<?php

namespace MTechStack\LaravelApiModelClient\Configuration;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Manages parameter validation strictness levels for OpenAPI schemas
 */
class ValidationStrictnessManager
{
    public const STRICT = 'strict';
    public const MODERATE = 'moderate';
    public const LENIENT = 'lenient';

    protected string $schemaName;
    protected array $schemaConfig;
    protected string $strictnessLevel;

    public function __construct(string $schemaName = null)
    {
        $this->schemaName = $schemaName ?? Config::get('api-client.default_schema', 'primary');
        $this->loadSchemaConfig();
    }

    /**
     * Load schema configuration
     */
    protected function loadSchemaConfig(): void
    {
        $schemas = Config::get('api-client.schemas', []);
        $this->schemaConfig = $schemas[$this->schemaName] ?? [];
        
        $validationConfig = $this->schemaConfig['validation'] ?? [];
        $this->strictnessLevel = $validationConfig['strictness'] ?? self::STRICT;
    }

    /**
     * Validate parameters based on strictness level
     */
    public function validateParameters(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        switch ($this->strictnessLevel) {
            case self::STRICT:
                return $this->validateStrict($data, $rules, $messages, $attributes);
            
            case self::MODERATE:
                return $this->validateModerate($data, $rules, $messages, $attributes);
            
            case self::LENIENT:
                return $this->validateLenient($data, $rules, $messages, $attributes);
            
            default:
                throw new \InvalidArgumentException("Invalid strictness level: {$this->strictnessLevel}");
        }
    }

    /**
     * Strict validation - all rules enforced, fail on unknown properties
     */
    protected function validateStrict(array $data, array $rules, array $messages, array $attributes): array
    {
        $validationConfig = $this->schemaConfig['validation'] ?? [];
        
        // Check for unknown properties if enabled
        if ($validationConfig['fail_on_unknown_properties'] ?? true) {
            $this->checkUnknownProperties($data, $rules);
        }

        // Validate all required fields strictly
        if ($validationConfig['fail_on_missing_required'] ?? true) {
            $this->enforceRequiredFields($data, $rules);
        }

        // Auto-cast types if enabled
        if ($validationConfig['auto_cast_types'] ?? true) {
            $data = $this->autoCastTypes($data, $rules);
        }

        // Validate formats strictly
        if ($validationConfig['validate_formats'] ?? true) {
            $rules = $this->enhanceFormatValidation($rules);
        }

        // Run Laravel validation
        $validator = Validator::make($data, $rules, $messages, $attributes);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'valid' => true,
            'data' => $data,
            'errors' => [],
            'warnings' => [],
            'strictness' => self::STRICT,
        ];
    }

    /**
     * Moderate validation - some flexibility, warnings for issues
     */
    protected function validateModerate(array $data, array $rules, array $messages, array $attributes): array
    {
        $validationConfig = $this->schemaConfig['validation'] ?? [];
        $warnings = [];

        // Check for unknown properties but only warn
        if ($validationConfig['fail_on_unknown_properties'] ?? false) {
            try {
                $this->checkUnknownProperties($data, $rules);
            } catch (\Exception $e) {
                $warnings[] = $e->getMessage();
            }
        }

        // Auto-cast types if enabled
        if ($validationConfig['auto_cast_types'] ?? true) {
            $data = $this->autoCastTypes($data, $rules);
        }

        // Make some rules optional in moderate mode
        $moderateRules = $this->relaxRulesForModerate($rules);

        // Run Laravel validation
        $validator = Validator::make($data, $moderateRules, $messages, $attributes);
        
        if ($validator->fails()) {
            // In moderate mode, convert some errors to warnings
            $errors = $validator->errors()->toArray();
            $criticalErrors = [];
            
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    if ($this->isCriticalError($error)) {
                        $criticalErrors[$field][] = $error;
                    } else {
                        $warnings[] = "Field '{$field}': {$error}";
                    }
                }
            }

            if (!empty($criticalErrors)) {
                $validator->errors()->replace($criticalErrors);
                throw new ValidationException($validator);
            }
        }

        return [
            'valid' => true,
            'data' => $data,
            'errors' => [],
            'warnings' => $warnings,
            'strictness' => self::MODERATE,
        ];
    }

    /**
     * Lenient validation - minimal validation, mostly warnings
     */
    protected function validateLenient(array $data, array $rules, array $messages, array $attributes): array
    {
        $validationConfig = $this->schemaConfig['validation'] ?? [];
        $warnings = [];

        // Auto-cast types if enabled
        if ($validationConfig['auto_cast_types'] ?? true) {
            $data = $this->autoCastTypes($data, $rules);
        }

        // Make most rules optional in lenient mode
        $lenientRules = $this->relaxRulesForLenient($rules);

        // Run Laravel validation
        $validator = Validator::make($data, $lenientRules, $messages, $attributes);
        
        if ($validator->fails()) {
            // In lenient mode, convert most errors to warnings
            $errors = $validator->errors()->toArray();
            $criticalErrors = [];
            
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    if ($this->isAbsolutelyCriticalError($error)) {
                        $criticalErrors[$field][] = $error;
                    } else {
                        $warnings[] = "Field '{$field}': {$error}";
                    }
                }
            }

            if (!empty($criticalErrors)) {
                $validator->errors()->replace($criticalErrors);
                throw new ValidationException($validator);
            }
        }

        return [
            'valid' => true,
            'data' => $data,
            'errors' => [],
            'warnings' => $warnings,
            'strictness' => self::LENIENT,
        ];
    }

    /**
     * Check for unknown properties in data
     */
    protected function checkUnknownProperties(array $data, array $rules): void
    {
        $knownFields = array_keys($rules);
        $unknownFields = array_diff(array_keys($data), $knownFields);

        if (!empty($unknownFields)) {
            throw new \InvalidArgumentException(
                'Unknown properties found: ' . implode(', ', $unknownFields)
            );
        }
    }

    /**
     * Enforce required fields
     */
    protected function enforceRequiredFields(array $data, array $rules): void
    {
        foreach ($rules as $field => $fieldRules) {
            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            if (in_array('required', $rulesArray) && !array_key_exists($field, $data)) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
    }

    /**
     * Auto-cast types based on validation rules
     */
    protected function autoCastTypes(array $data, array $rules): array
    {
        foreach ($data as $field => $value) {
            if (!isset($rules[$field]) || $value === null) {
                continue;
            }

            $fieldRules = is_string($rules[$field]) ? explode('|', $rules[$field]) : $rules[$field];
            
            foreach ($fieldRules as $rule) {
                if (is_string($rule)) {
                    $data[$field] = $this->castValueByRule($value, $rule);
                }
            }
        }

        return $data;
    }

    /**
     * Cast value based on validation rule
     */
    protected function castValueByRule($value, string $rule)
    {
        switch (true) {
            case str_starts_with($rule, 'integer'):
                return is_numeric($value) ? (int) $value : $value;
                
            case str_starts_with($rule, 'numeric'):
            case str_starts_with($rule, 'decimal'):
                return is_numeric($value) ? (float) $value : $value;
                
            case str_starts_with($rule, 'boolean'):
                if (is_string($value)) {
                    return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
                }
                return (bool) $value;
                
            case str_starts_with($rule, 'array'):
                return is_string($value) ? json_decode($value, true) ?? $value : $value;
                
            case str_starts_with($rule, 'string'):
                return (string) $value;
                
            default:
                return $value;
        }
    }

    /**
     * Enhance format validation rules
     */
    protected function enhanceFormatValidation(array $rules): array
    {
        $enhancedRules = [];

        foreach ($rules as $field => $fieldRules) {
            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            // Add stricter format validation
            foreach ($rulesArray as $rule) {
                if (str_starts_with($rule, 'email')) {
                    $rulesArray[] = 'email:rfc,dns';
                } elseif (str_starts_with($rule, 'url')) {
                    $rulesArray[] = 'active_url';
                } elseif (str_starts_with($rule, 'date')) {
                    $rulesArray[] = 'date_format:Y-m-d H:i:s';
                }
            }
            
            $enhancedRules[$field] = array_unique($rulesArray);
        }

        return $enhancedRules;
    }

    /**
     * Relax rules for moderate validation
     */
    protected function relaxRulesForModerate(array $rules): array
    {
        $relaxedRules = [];

        foreach ($rules as $field => $fieldRules) {
            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            // Remove some strict rules
            $rulesArray = array_filter($rulesArray, function ($rule) {
                return !in_array($rule, ['required_with', 'required_without', 'required_if']);
            });
            
            // Make some rules nullable
            if (in_array('required', $rulesArray)) {
                $rulesArray = array_diff($rulesArray, ['required']);
                $rulesArray[] = 'nullable';
            }
            
            $relaxedRules[$field] = array_values($rulesArray);
        }

        return $relaxedRules;
    }

    /**
     * Relax rules for lenient validation
     */
    protected function relaxRulesForLenient(array $rules): array
    {
        $lenientRules = [];

        foreach ($rules as $field => $fieldRules) {
            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            // Keep only basic type validation
            $basicRules = [];
            foreach ($rulesArray as $rule) {
                if (in_array($rule, ['string', 'integer', 'numeric', 'boolean', 'array'])) {
                    $basicRules[] = $rule;
                }
            }
            
            // Add nullable to everything
            $basicRules[] = 'nullable';
            
            $lenientRules[$field] = array_unique($basicRules);
        }

        return $lenientRules;
    }

    /**
     * Check if an error is critical in moderate mode
     */
    protected function isCriticalError(string $error): bool
    {
        $criticalPatterns = [
            'must be a valid email',
            'must be a valid URL',
            'must be a number',
            'must be an integer',
        ];

        foreach ($criticalPatterns as $pattern) {
            if (str_contains($error, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an error is absolutely critical in lenient mode
     */
    protected function isAbsolutelyCriticalError(string $error): bool
    {
        $absolutelyCriticalPatterns = [
            'must be a number',
            'must be an integer',
            'must be a boolean',
        ];

        foreach ($absolutelyCriticalPatterns as $pattern) {
            if (str_contains($error, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current strictness level
     */
    public function getStrictnessLevel(): string
    {
        return $this->strictnessLevel;
    }

    /**
     * Set strictness level
     */
    public function setStrictnessLevel(string $level): self
    {
        if (!in_array($level, [self::STRICT, self::MODERATE, self::LENIENT])) {
            throw new \InvalidArgumentException("Invalid strictness level: {$level}");
        }

        $this->strictnessLevel = $level;
        return $this;
    }

    /**
     * Get available strictness levels
     */
    public static function getAvailableLevels(): array
    {
        return [
            self::STRICT => 'Strict - All rules enforced, fail on unknown properties',
            self::MODERATE => 'Moderate - Some flexibility, warnings for non-critical issues',
            self::LENIENT => 'Lenient - Minimal validation, mostly warnings',
        ];
    }

    /**
     * Get validation configuration for current schema
     */
    public function getValidationConfig(): array
    {
        return $this->schemaConfig['validation'] ?? [];
    }

    /**
     * Update validation configuration
     */
    public function updateValidationConfig(array $config): self
    {
        $this->schemaConfig['validation'] = array_merge(
            $this->schemaConfig['validation'] ?? [],
            $config
        );

        if (isset($config['strictness'])) {
            $this->strictnessLevel = $config['strictness'];
        }

        return $this;
    }
}
