<?php

namespace ApiModelRelations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class GenerateApiModelFromSwagger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-model:generate 
                            {--url= : URL to OpenAPI/Swagger JSON or YAML specification}
                            {--file= : Path to local OpenAPI/Swagger JSON or YAML file}
                            {--resource= : Specific resource to generate (e.g., "products")}
                            {--namespace=App\\Models\\Api : Namespace for generated models}
                            {--output=app/Models/Api : Output directory for generated models}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API model classes from OpenAPI/Swagger specification';

    /**
     * The parsed OpenAPI/Swagger specification.
     *
     * @var array
     */
    protected $specification = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Generating API models from OpenAPI/Swagger specification...');

        // Load the specification
        if (!$this->loadSpecification()) {
            return 1;
        }

        // Create output directory if it doesn't exist
        $outputDir = base_path($this->option('output'));
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // Generate models
        $resourceName = $this->option('resource');
        if ($resourceName) {
            $this->generateModelForResource($resourceName);
        } else {
            $this->generateAllModels();
        }

        $this->info('API model generation completed successfully!');
        return 0;
    }

    /**
     * Load the OpenAPI/Swagger specification.
     *
     * @return bool
     */
    protected function loadSpecification()
    {
        $url = $this->option('url');
        $file = $this->option('file');

        if (!$url && !$file) {
            $this->error('Either --url or --file option is required.');
            return false;
        }

        try {
            if ($url) {
                $response = Http::get($url);
                if ($response->failed()) {
                    $this->error("Failed to fetch specification from URL: {$response->status()}");
                    return false;
                }
                $content = $response->body();
            } else {
                if (!File::exists($file)) {
                    $this->error("Specification file not found: {$file}");
                    return false;
                }
                $content = File::get($file);
            }

            // Determine if it's JSON or YAML and parse accordingly
            if (Str::startsWith(trim($content), '{')) {
                $this->specification = json_decode($content, true);
            } else {
                // For YAML parsing, we need the symfony/yaml package
                if (!class_exists('Symfony\\Component\\Yaml\\Yaml')) {
                    $this->error('The symfony/yaml package is required to parse YAML specifications.');
                    $this->line('Please install it using: composer require symfony/yaml');
                    return false;
                }
                $this->specification = \Symfony\Component\Yaml\Yaml::parse($content);
            }

            if (empty($this->specification)) {
                $this->error('Failed to parse the specification.');
                return false;
            }

            $this->info('Specification loaded successfully.');
            return true;
        } catch (\Exception $e) {
            $this->error("Error loading specification: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Generate models for all resources in the specification.
     *
     * @return void
     */
    protected function generateAllModels()
    {
        $paths = $this->specification['paths'] ?? [];
        $resources = [];

        // Extract resource names from paths
        foreach ($paths as $path => $methods) {
            $segments = explode('/', trim($path, '/'));
            if (count($segments) > 0) {
                $resource = $segments[0];
                $resources[$resource] = true;
            }
        }

        if (empty($resources)) {
            $this->warn('No resources found in the specification.');
            return;
        }

        $this->info('Found ' . count($resources) . ' resources.');
        foreach (array_keys($resources) as $resource) {
            $this->generateModelForResource($resource);
        }
    }

    /**
     * Generate a model for a specific resource.
     *
     * @param string $resourceName
     * @return void
     */
    protected function generateModelForResource($resourceName)
    {
        $this->info("Generating model for resource: {$resourceName}");

        // Find schema for this resource
        $schema = $this->findSchemaForResource($resourceName);
        if (!$schema) {
            $this->warn("No schema found for resource: {$resourceName}");
            return;
        }

        // Generate model class
        $modelName = $this->getModelNameFromResource($resourceName);
        $namespace = $this->option('namespace');
        $fillable = $this->extractFillableFromSchema($schema);
        $casts = $this->extractCastsFromSchema($schema);
        $apiEndpoint = $resourceName;

        $modelContent = $this->generateModelContent($modelName, $namespace, $fillable, $casts, $apiEndpoint);

        // Save the model file
        $outputDir = base_path($this->option('output'));
        $filePath = "{$outputDir}/{$modelName}.php";
        File::put($filePath, $modelContent);

        $this->info("Model {$modelName} generated at {$filePath}");
    }

    /**
     * Find the schema for a specific resource.
     *
     * @param string $resourceName
     * @return array|null
     */
    protected function findSchemaForResource($resourceName)
    {
        // Try to find in components/schemas (OpenAPI 3.x)
        $schemas = $this->specification['components']['schemas'] ?? [];
        $singularResource = Str::singular($resourceName);
        $pluralResource = Str::plural($resourceName);
        
        // Try different naming conventions
        $possibleNames = [
            $singularResource,
            $pluralResource,
            Str::studly($singularResource),
            Str::studly($pluralResource),
        ];

        foreach ($possibleNames as $name) {
            if (isset($schemas[$name])) {
                return $schemas[$name];
            }
        }

        // Try to find in definitions (Swagger 2.x)
        $definitions = $this->specification['definitions'] ?? [];
        foreach ($possibleNames as $name) {
            if (isset($definitions[$name])) {
                return $definitions[$name];
            }
        }

        // Try to extract from paths
        $paths = $this->specification['paths'] ?? [];
        foreach ($paths as $path => $methods) {
            if (Str::contains($path, "/{$resourceName}/") || Str::endsWith($path, "/{$resourceName}")) {
                foreach ($methods as $method => $operation) {
                    if ($method === 'get' && isset($operation['responses']['200']['schema'])) {
                        return $operation['responses']['200']['schema'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get the model name from a resource name.
     *
     * @param string $resourceName
     * @return string
     */
    protected function getModelNameFromResource($resourceName)
    {
        return 'Remote' . Str::studly(Str::singular($resourceName));
    }

    /**
     * Extract fillable attributes from schema.
     *
     * @param array $schema
     * @return array
     */
    protected function extractFillableFromSchema($schema)
    {
        $properties = $schema['properties'] ?? [];
        $fillable = [];

        foreach ($properties as $name => $property) {
            // Skip read-only properties
            if (isset($property['readOnly']) && $property['readOnly'] === true) {
                continue;
            }

            // Skip system fields
            $systemFields = ['id', 'created_at', 'updated_at', 'deleted_at'];
            if (in_array($name, $systemFields)) {
                continue;
            }

            $fillable[] = $name;
        }

        return $fillable;
    }

    /**
     * Extract type casts from schema.
     *
     * @param array $schema
     * @return array
     */
    protected function extractCastsFromSchema($schema)
    {
        $properties = $schema['properties'] ?? [];
        $casts = [];

        foreach ($properties as $name => $property) {
            $type = $property['type'] ?? null;
            
            if ($type) {
                switch ($type) {
                    case 'integer':
                        $casts[$name] = 'integer';
                        break;
                    case 'number':
                        $casts[$name] = 'float';
                        break;
                    case 'boolean':
                        $casts[$name] = 'boolean';
                        break;
                    case 'string':
                        $format = $property['format'] ?? null;
                        if ($format === 'date-time' || $format === 'date') {
                            $casts[$name] = 'datetime';
                        }
                        break;
                    case 'array':
                    case 'object':
                        $casts[$name] = 'array';
                        break;
                }
            }
        }

        return $casts;
    }

    /**
     * Generate the model class content.
     *
     * @param string $modelName
     * @param string $namespace
     * @param array $fillable
     * @param array $casts
     * @param string $apiEndpoint
     * @return string
     */
    protected function generateModelContent($modelName, $namespace, $fillable, $casts, $apiEndpoint)
    {
        $fillableStr = implode(",\n        ", array_map(function ($item) {
            return "'{$item}'";
        }, $fillable));

        $castsStr = implode(",\n        ", array_map(function ($key, $value) {
            return "'{$key}' => '{$value}'";
        }, array_keys($casts), $casts));

        return <<<PHP
<?php

namespace {$namespace};

use ApiModelRelations\Models\ApiModel;
use ApiModelRelations\Traits\SyncWithApi;

class {$modelName} extends ApiModel
{
    use SyncWithApi;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected \$apiEndpoint = '{$apiEndpoint}';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected \$primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected \$fillable = [
        {$fillableStr}
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected \$casts = [
        {$castsStr}
    ];

    /**
     * Whether to merge API data with local database data.
     *
     * @var bool
     */
    protected \$mergeWithLocalData = true;

    /**
     * The cache TTL in seconds.
     *
     * @var int
     */
    protected \$cacheTtl = 3600; // 1 hour
}
PHP;
    }
}
