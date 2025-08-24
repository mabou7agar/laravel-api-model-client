<?php

namespace MTechStack\LaravelApiModelClient\Jobs;

use MTechStack\LaravelApiModelClient\Contracts\ApiModelInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncModelToApi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [5, 15, 30];

    /**
     * The model instance to sync.
     *
     * @var \MTechStack\LaravelApiModelClient\Contracts\ApiModelInterface
     */
    protected $model;

    /**
     * The operation type (create, update, delete).
     *
     * @var string
     */
    protected $operation;

    /**
     * Create a new job instance.
     *
     * @param \MTechStack\LaravelApiModelClient\Contracts\ApiModelInterface $model
     * @param string $operation
     * @return void
     */
    public function __construct(ApiModelInterface $model, string $operation = 'save')
    {
        $this->model = $model;
        $this->operation = $operation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $modelClass = get_class($this->model);
            $modelId = $this->model->getKey();
            
            Log::info("Starting API sync job", [
                'model' => $modelClass,
                'id' => $modelId,
                'operation' => $this->operation
            ]);
            
            $result = false;
            
            switch ($this->operation) {
                case 'save':
                case 'update':
                    $result = $this->model->saveToApi();
                    break;
                    
                case 'delete':
                    $result = $this->model->deleteFromApi();
                    break;
                    
                default:
                    throw new \InvalidArgumentException("Unknown operation: {$this->operation}");
            }
            
            if ($result) {
                Log::info("API sync successful", [
                    'model' => $modelClass,
                    'id' => $modelId,
                    'operation' => $this->operation
                ]);
            } else {
                Log::error("API sync failed", [
                    'model' => $modelClass,
                    'id' => $modelId,
                    'operation' => $this->operation,
                    'error' => $this->model->getLastApiError() ?? 'Unknown error'
                ]);
                
                // If we've reached max retries, notify someone or take other action
                if ($this->attempts() >= $this->tries) {
                    Log::critical("API sync failed after {$this->tries} attempts", [
                        'model' => $modelClass,
                        'id' => $modelId,
                        'operation' => $this->operation
                    ]);
                    
                    // Could add notification here
                } else {
                    // Retry the job with exponential backoff
                    $this->release($this->backoff[$this->attempts() - 1] ?? 60);
                }
            }
        } catch (\Exception $e) {
            Log::error("Exception during API sync: " . $e->getMessage(), [
                'model' => get_class($this->model),
                'id' => $this->model->getKey(),
                'operation' => $this->operation,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retry or fail based on the exception type
            if ($this->shouldRetry($e)) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            } else {
                $this->fail($e);
            }
        }
    }
    
    /**
     * Determine if the job should be retried based on the exception.
     *
     * @param \Exception $e
     * @return bool
     */
    protected function shouldRetry(\Exception $e)
    {
        // Retry on network errors, timeouts, etc.
        // Don't retry on validation errors, authentication failures, etc.
        $retryableExceptions = [
            \GuzzleHttp\Exception\ConnectException::class,
            \GuzzleHttp\Exception\ServerException::class,
            \GuzzleHttp\Exception\RequestException::class,
        ];
        
        foreach ($retryableExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }
        
        return false;
    }
}
