<?php

namespace ApiModelRelations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class GenerateApiModelDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-model:docs 
                            {--model= : Specific model to document (fully qualified class name)}
                            {--directory= : Directory containing API models to document}
                            {--namespace=App\\Models\\Api : Namespace to search for models}
                            {--output=docs/api-models : Output directory for documentation}
                            {--format=markdown : Output format (markdown or html)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate documentation for API models';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Generating API model documentation...');

        // Create output directory if it doesn't exist
        $outputDir = base_path($this->option('output'));
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // Determine which models to document
        $model = $this->option('model');
        $directory = $this->option('directory');
        $namespace = $this->option('namespace');

        if ($model) {
            // Document a specific model
            $this->documentModel($model, $outputDir);
        } elseif ($directory) {
            // Document all models in a directory
            $this->documentModelsInDirectory($directory, $namespace, $outputDir);
        } else {
            $this->error('Either --model or --directory option is required.');
            return 1;
        }

        // Generate index file
        $this->generateIndexFile($outputDir);

        $this->info('API model documentation generated successfully!');
        return 0;
    }

    /**
     * Document a specific model.
     *
     * @param string $modelClass
     * @param string $outputDir
     * @return void
     */
    protected function documentModel($modelClass, $outputDir)
    {
        try {
            if (!class_exists($modelClass)) {
                $this->error("Model class {$modelClass} not found.");
                return;
            }

            $this->info("Documenting model: {$modelClass}");
            $documentation = $this->generateModelDocumentation($modelClass);
            
            $className = class_basename($modelClass);
            $format = $this->option('format');
            $fileName = "{$outputDir}/{$className}.{$format}";
            
            File::put($fileName, $documentation);
            $this->info("Documentation generated at {$fileName}");
        } catch (\Exception $e) {
            $this->error("Error documenting model {$modelClass}: {$e->getMessage()}");
        }
    }

    /**
     * Document all models in a directory.
     *
     * @param string $directory
     * @param string $namespace
     * @param string $outputDir
     * @return void
     */
    protected function documentModelsInDirectory($directory, $namespace, $outputDir)
    {
        $path = base_path($directory);
        if (!File::isDirectory($path)) {
            $this->error("Directory {$path} not found.");
            return;
        }

        $files = File::files($path);
        $modelClasses = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $className = $file->getBasename('.php');
                $modelClass = $namespace . '\\' . $className;
                
                if (class_exists($modelClass)) {
                    $modelClasses[] = $modelClass;
                }
            }
        }

        $this->info("Found " . count($modelClasses) . " model classes.");
        
        foreach ($modelClasses as $modelClass) {
            $this->documentModel($modelClass, $outputDir);
        }
    }

    /**
     * Generate documentation for a model.
     *
     * @param string $modelClass
     * @return string
     */
    protected function generateModelDocumentation($modelClass)
    {
        $reflection = new ReflectionClass($modelClass);
        $className = $reflection->getShortName();
        $format = $this->option('format');
        
        if ($format === 'markdown') {
            return $this->generateMarkdownDocumentation($reflection);
        } else {
            return $this->generateHtmlDocumentation($reflection);
        }
    }

    /**
     * Generate markdown documentation for a model.
     *
     * @param \ReflectionClass $reflection
     * @return string
     */
    protected function generateMarkdownDocumentation(ReflectionClass $reflection)
    {
        $className = $reflection->getShortName();
        $namespace = $reflection->getNamespaceName();
        $isApiModel = $reflection->isSubclassOf('ApiModelRelations\\Models\\ApiModel');
        
        $doc = "# {$className}\n\n";
        $doc .= "**Namespace:** `{$namespace}`\n\n";
        $doc .= "**Extends:** " . ($isApiModel ? "`ApiModel`" : $reflection->getParentClass()->getName()) . "\n\n";
        
        // Get class docblock
        $classDocComment = $reflection->getDocComment();
        if ($classDocComment) {
            $doc .= "## Description\n\n";
            $doc .= $this->formatDocComment($classDocComment) . "\n\n";
        }
        
        // API Endpoint
        if ($isApiModel) {
            try {
                $apiEndpointProp = $reflection->getProperty('apiEndpoint');
                $apiEndpointProp->setAccessible(true);
                $defaultInstance = $reflection->newInstanceWithoutConstructor();
                $apiEndpoint = $apiEndpointProp->getValue($defaultInstance);
                
                $doc .= "## API Endpoint\n\n";
                $doc .= "`{$apiEndpoint}`\n\n";
            } catch (\Exception $e) {
                // Property might not be accessible
            }
        }
        
        // Properties
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        if (count($properties) > 0) {
            $doc .= "## Properties\n\n";
            
            foreach ($properties as $property) {
                $propertyName = $property->getName();
                $propertyVisibility = $property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private');
                
                $doc .= "### `{$propertyName}`\n\n";
                $doc .= "**Visibility:** {$propertyVisibility}\n\n";
                
                $propertyDocComment = $property->getDocComment();
                if ($propertyDocComment) {
                    $doc .= $this->formatDocComment($propertyDocComment) . "\n\n";
                }
            }
        }
        
        // Methods
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        if (count($methods) > 0) {
            $doc .= "## Methods\n\n";
            
            foreach ($methods as $method) {
                // Skip inherited methods from parent classes
                if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }
                
                $methodName = $method->getName();
                $doc .= "### `{$methodName}()`\n\n";
                
                $methodDocComment = $method->getDocComment();
                if ($methodDocComment) {
                    $doc .= $this->formatDocComment($methodDocComment) . "\n\n";
                }
                
                // Parameters
                $parameters = $method->getParameters();
                if (count($parameters) > 0) {
                    $doc .= "**Parameters:**\n\n";
                    
                    foreach ($parameters as $parameter) {
                        $paramName = $parameter->getName();
                        $paramType = $parameter->hasType() ? $parameter->getType()->getName() : 'mixed';
                        $isOptional = $parameter->isOptional();
                        $defaultValue = $isOptional ? ' = ' . var_export($parameter->getDefaultValue(), true) : '';
                        
                        $doc .= "- `{$paramType} \${$paramName}{$defaultValue}`\n";
                    }
                    
                    $doc .= "\n";
                }
                
                // Return type
                if ($method->hasReturnType()) {
                    $returnType = $method->getReturnType()->getName();
                    $doc .= "**Returns:** `{$returnType}`\n\n";
                }
            }
        }
        
        // Relationships
        $relationships = $this->findRelationshipMethods($reflection);
        if (count($relationships) > 0) {
            $doc .= "## Relationships\n\n";
            
            foreach ($relationships as $method) {
                $methodName = $method->getName();
                $doc .= "### `{$methodName}()`\n\n";
                
                $methodDocComment = $method->getDocComment();
                if ($methodDocComment) {
                    $doc .= $this->formatDocComment($methodDocComment) . "\n\n";
                }
                
                // Try to determine relationship type
                $relationshipType = $this->determineRelationshipType($method);
                if ($relationshipType) {
                    $doc .= "**Type:** {$relationshipType}\n\n";
                }
            }
        }
        
        return $doc;
    }

    /**
     * Generate HTML documentation for a model.
     *
     * @param \ReflectionClass $reflection
     * @return string
     */
    protected function generateHtmlDocumentation(ReflectionClass $reflection)
    {
        $className = $reflection->getShortName();
        $namespace = $reflection->getNamespaceName();
        $isApiModel = $reflection->isSubclassOf('ApiModelRelations\\Models\\ApiModel');
        
        $html = "<!DOCTYPE html>\n";
        $html .= "<html lang=\"en\">\n";
        $html .= "<head>\n";
        $html .= "    <meta charset=\"UTF-8\">\n";
        $html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "    <title>{$className} - API Model Documentation</title>\n";
        $html .= "    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css\" rel=\"stylesheet\">\n";
        $html .= "</head>\n";
        $html .= "<body>\n";
        $html .= "    <div class=\"container mt-4\">\n";
        $html .= "        <h1>{$className}</h1>\n";
        $html .= "        <p class=\"lead\"><strong>Namespace:</strong> {$namespace}</p>\n";
        $html .= "        <p><strong>Extends:</strong> " . ($isApiModel ? "ApiModel" : $reflection->getParentClass()->getName()) . "</p>\n";
        
        // Get class docblock
        $classDocComment = $reflection->getDocComment();
        if ($classDocComment) {
            $html .= "        <h2>Description</h2>\n";
            $html .= "        <div class=\"card mb-4\">\n";
            $html .= "            <div class=\"card-body\">\n";
            $html .= "                " . nl2br(htmlspecialchars($this->formatDocComment($classDocComment))) . "\n";
            $html .= "            </div>\n";
            $html .= "        </div>\n";
        }
        
        // API Endpoint
        if ($isApiModel) {
            try {
                $apiEndpointProp = $reflection->getProperty('apiEndpoint');
                $apiEndpointProp->setAccessible(true);
                $defaultInstance = $reflection->newInstanceWithoutConstructor();
                $apiEndpoint = $apiEndpointProp->getValue($defaultInstance);
                
                $html .= "        <h2>API Endpoint</h2>\n";
                $html .= "        <div class=\"card mb-4\">\n";
                $html .= "            <div class=\"card-body\">\n";
                $html .= "                <code>{$apiEndpoint}</code>\n";
                $html .= "            </div>\n";
                $html .= "        </div>\n";
            } catch (\Exception $e) {
                // Property might not be accessible
            }
        }
        
        // Properties
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        if (count($properties) > 0) {
            $html .= "        <h2>Properties</h2>\n";
            
            foreach ($properties as $property) {
                $propertyName = $property->getName();
                $propertyVisibility = $property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private');
                
                $html .= "        <div class=\"card mb-3\">\n";
                $html .= "            <div class=\"card-header\">\n";
                $html .= "                <code>{$propertyName}</code>\n";
                $html .= "                <span class=\"badge bg-secondary float-end\">{$propertyVisibility}</span>\n";
                $html .= "            </div>\n";
                
                $propertyDocComment = $property->getDocComment();
                if ($propertyDocComment) {
                    $html .= "            <div class=\"card-body\">\n";
                    $html .= "                " . nl2br(htmlspecialchars($this->formatDocComment($propertyDocComment))) . "\n";
                    $html .= "            </div>\n";
                }
                
                $html .= "        </div>\n";
            }
        }
        
        // Methods
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        if (count($methods) > 0) {
            $html .= "        <h2>Methods</h2>\n";
            
            foreach ($methods as $method) {
                // Skip inherited methods from parent classes
                if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }
                
                $methodName = $method->getName();
                $html .= "        <div class=\"card mb-3\">\n";
                $html .= "            <div class=\"card-header\">\n";
                $html .= "                <code>{$methodName}()</code>\n";
                $html .= "            </div>\n";
                $html .= "            <div class=\"card-body\">\n";
                
                $methodDocComment = $method->getDocComment();
                if ($methodDocComment) {
                    $html .= "                <div class=\"mb-3\">\n";
                    $html .= "                    " . nl2br(htmlspecialchars($this->formatDocComment($methodDocComment))) . "\n";
                    $html .= "                </div>\n";
                }
                
                // Parameters
                $parameters = $method->getParameters();
                if (count($parameters) > 0) {
                    $html .= "                <h5>Parameters</h5>\n";
                    $html .= "                <ul>\n";
                    
                    foreach ($parameters as $parameter) {
                        $paramName = $parameter->getName();
                        $paramType = $parameter->hasType() ? $parameter->getType()->getName() : 'mixed';
                        $isOptional = $parameter->isOptional();
                        $defaultValue = $isOptional ? ' = ' . var_export($parameter->getDefaultValue(), true) : '';
                        
                        $html .= "                    <li><code>{$paramType} \${$paramName}{$defaultValue}</code></li>\n";
                    }
                    
                    $html .= "                </ul>\n";
                }
                
                // Return type
                if ($method->hasReturnType()) {
                    $returnType = $method->getReturnType()->getName();
                    $html .= "                <h5>Returns</h5>\n";
                    $html .= "                <code>{$returnType}</code>\n";
                }
                
                $html .= "            </div>\n";
                $html .= "        </div>\n";
            }
        }
        
        // Relationships
        $relationships = $this->findRelationshipMethods($reflection);
        if (count($relationships) > 0) {
            $html .= "        <h2>Relationships</h2>\n";
            
            foreach ($relationships as $method) {
                $methodName = $method->getName();
                $html .= "        <div class=\"card mb-3\">\n";
                $html .= "            <div class=\"card-header\">\n";
                $html .= "                <code>{$methodName}()</code>\n";
                $html .= "            </div>\n";
                $html .= "            <div class=\"card-body\">\n";
                
                $methodDocComment = $method->getDocComment();
                if ($methodDocComment) {
                    $html .= "                <div class=\"mb-3\">\n";
                    $html .= "                    " . nl2br(htmlspecialchars($this->formatDocComment($methodDocComment))) . "\n";
                    $html .= "                </div>\n";
                }
                
                // Try to determine relationship type
                $relationshipType = $this->determineRelationshipType($method);
                if ($relationshipType) {
                    $html .= "                <p><strong>Type:</strong> {$relationshipType}</p>\n";
                }
                
                $html .= "            </div>\n";
                $html .= "        </div>\n";
            }
        }
        
        $html .= "    </div>\n";
        $html .= "    <script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js\"></script>\n";
        $html .= "</body>\n";
        $html .= "</html>";
        
        return $html;
    }

    /**
     * Generate an index file for all documented models.
     *
     * @param string $outputDir
     * @return void
     */
    protected function generateIndexFile($outputDir)
    {
        $format = $this->option('format');
        $files = File::files($outputDir);
        $models = [];
        
        foreach ($files as $file) {
            if ($file->getExtension() === $format && $file->getBasename() !== "index.{$format}") {
                $models[] = $file->getBasename(".{$format}");
            }
        }
        
        if ($format === 'markdown') {
            $content = "# API Model Documentation\n\n";
            $content .= "## Models\n\n";
            
            foreach ($models as $model) {
                $content .= "- [{$model}]({$model}.{$format})\n";
            }
        } else {
            $content = "<!DOCTYPE html>\n";
            $content .= "<html lang=\"en\">\n";
            $content .= "<head>\n";
            $content .= "    <meta charset=\"UTF-8\">\n";
            $content .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
            $content .= "    <title>API Model Documentation</title>\n";
            $content .= "    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css\" rel=\"stylesheet\">\n";
            $content .= "</head>\n";
            $content .= "<body>\n";
            $content .= "    <div class=\"container mt-4\">\n";
            $content .= "        <h1>API Model Documentation</h1>\n";
            $content .= "        <h2>Models</h2>\n";
            $content .= "        <ul class=\"list-group\">\n";
            
            foreach ($models as $model) {
                $content .= "            <li class=\"list-group-item\"><a href=\"{$model}.{$format}\">{$model}</a></li>\n";
            }
            
            $content .= "        </ul>\n";
            $content .= "    </div>\n";
            $content .= "    <script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js\"></script>\n";
            $content .= "</body>\n";
            $content .= "</html>";
        }
        
        File::put("{$outputDir}/index.{$format}", $content);
        $this->info("Generated index file at {$outputDir}/index.{$format}");
    }

    /**
     * Format a doc comment by removing comment markers and indentation.
     *
     * @param string $docComment
     * @return string
     */
    protected function formatDocComment($docComment)
    {
        // Remove comment markers
        $text = preg_replace('/^\s*\/\*+|\s*\*+\/\s*$/', '', $docComment);
        
        // Remove leading asterisks and spaces from each line
        $lines = explode("\n", $text);
        $formattedLines = [];
        
        foreach ($lines as $line) {
            $formattedLine = preg_replace('/^\s*\*\s?/', '', $line);
            $formattedLines[] = $formattedLine;
        }
        
        return trim(implode("\n", $formattedLines));
    }

    /**
     * Find relationship methods in a model.
     *
     * @param \ReflectionClass $reflection
     * @return array
     */
    protected function findRelationshipMethods(ReflectionClass $reflection)
    {
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $relationshipMethods = [];
        
        foreach ($methods as $method) {
            // Skip inherited methods from parent classes
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            
            // Check if the method returns a relationship
            $docComment = $method->getDocComment();
            if ($docComment && (
                strpos($docComment, 'HasManyFromApi') !== false ||
                strpos($docComment, 'BelongsToFromApi') !== false ||
                strpos($docComment, 'HasOneFromApi') !== false ||
                strpos($docComment, 'BelongsToManyFromApi') !== false
            )) {
                $relationshipMethods[] = $method;
            }
        }
        
        return $relationshipMethods;
    }

    /**
     * Determine the type of relationship from a method.
     *
     * @param \ReflectionMethod $method
     * @return string|null
     */
    protected function determineRelationshipType(ReflectionMethod $method)
    {
        $docComment = $method->getDocComment();
        
        if (strpos($docComment, 'HasManyFromApi') !== false) {
            return 'HasManyFromApi';
        } elseif (strpos($docComment, 'BelongsToFromApi') !== false) {
            return 'BelongsToFromApi';
        } elseif (strpos($docComment, 'HasOneFromApi') !== false) {
            return 'HasOneFromApi';
        } elseif (strpos($docComment, 'BelongsToManyFromApi') !== false) {
            return 'BelongsToManyFromApi';
        }
        
        return null;
    }
}
