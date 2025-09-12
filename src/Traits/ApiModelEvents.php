<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Illuminate\Support\Facades\Event;
use Illuminate\Events\Dispatcher;

/**
 * Trait for handling API model events
 */
trait ApiModelEvents
{
    /**
     * Whether events are enabled for this model.
     *
     * @var bool
     */
    protected $eventsEnabled = true;

    /**
     * The event prefix for this model.
     *
     * @var string|null
     */
    protected $eventPrefix;

    /**
     * Boot the API model events trait.
     *
     * @return void
     */
    protected static function bootApiModelEvents()
    {
        // Only register events if we're not in a testing environment
        // or if events are explicitly enabled
        try {
            // Check if Laravel app is available and properly initialized
            if (function_exists('app') && app()->bound('config')) {
                $environment = app()->environment();
                $eventsEnabled = config('api-model-client.events.enabled', true);
                
                if ($environment !== 'testing' || $eventsEnabled) {
                    static::registerModelEvents();
                }
            } else {
                // Graceful fallback for standalone usage
                static::registerModelEvents();
            }
        } catch (\Exception $e) {
            // Graceful fallback for environments where app() is not available
            static::registerModelEvents();
        }
    }

    /**
     * Register the model events.
     *
     * @return void
     */
    protected static function registerModelEvents()
    {
        foreach (static::getApiModelEvents() as $event) {
            static::registerModelEvent($event);
        }
    }

    /**
     * Get the model events to register.
     *
     * @return array
     */
    protected static function getApiModelEvents()
    {
        return [
            'retrieving',
            'retrieved',
            'creating',
            'created',
            'updating',
            'updated',
            'saving',
            'saved',
            'deleting',
            'deleted',
            'apiRequesting',
            'apiRequested',
            'apiFailed',
        ];
    }

    /**
     * Register a model event.
     * Compatible with Laravel's Eloquent Model signature.
     *
     * @param  string  $event
     * @param  \Closure|string|null  $callback
     * @return void
     */
    protected static function registerModelEvent($event, $callback = null)
    {
        if ($callback !== null) {
            // If a callback is provided, register it directly with the event dispatcher
            $eventName = "eloquent.{$event}: " . static::class;
            Event::listen($eventName, $callback);
        } else {
            // If no callback is provided, use our custom observer registration
            static::registerModelObserver($event);
        }
    }

    /**
     * Register a model observer.
     *
     * @param  string  $event
     * @return void
     */
    protected static function registerModelObserver($event)
    {
        $eventName = "api-model.{$event}: " . static::class;
        
        Event::listen($eventName, function ($model, $data = null) use ($event) {
            if (is_object($model) && method_exists($model, 'fireCustomModelEvent')) {
                return $model->fireCustomModelEvent($event, $data);
            }
            return null;
        });
    }

    /**
     * Fire a custom model event.
     *
     * @param  string  $event
     * @param  mixed  $data
     * @return mixed|null
     */
    protected function fireCustomModelEvent($event, $data = null)
    {
        if (! $this->supportsEvents()) {
            return null;
        }

        $method = 'on' . ucfirst($event);

        if (method_exists($this, $method)) {
            $result = $this->{$method}($data);

            if (! is_null($result)) {
                return $result;
            }
        }

        $eventName = "api-model.{$event}: " . static::class;
        
        // Laravel 8+ compatibility - use Event facade with proper error handling
        try {
            $dispatcher = $this->getApiEventDispatcher();
            if ($dispatcher && method_exists($dispatcher, 'until')) {
                return $dispatcher->until($eventName, [$this, $data]);
            }
            
            // Fallback to Event facade
            return Event::until($eventName, [$this, $data]);
        } catch (\Exception $e) {
            // Graceful fallback for compatibility issues
            return null;
        }
    }

    /**
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @param  mixed  $data
     * @return mixed
     */
    protected function fireApiModelEvent($event, $data = null)
    {
        if (! $this->eventsEnabled) {
            return true;
        }

        $result = $this->fireCustomModelEvent($event, $data);

        if (! is_null($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Get the event prefix for this model.
     *
     * @return string
     */
    public function getEventPrefix()
    {
        return $this->eventPrefix ?? 'api-model';
    }

    /**
     * Set the event prefix for this model.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function setEventPrefix($prefix)
    {
        $this->eventPrefix = $prefix;

        return $this;
    }

    /**
     * Enable or disable events for this model.
     *
     * @param  bool  $enabled
     * @return $this
     */
    public function setEventsEnabled($enabled = true)
    {
        $this->eventsEnabled = $enabled;

        return $this;
    }

    /**
     * Determine if events are enabled for this model.
     *
     * @return bool
     */
    public function areEventsEnabled()
    {
        return $this->eventsEnabled;
    }

    /**
     * Get the event dispatcher instance.
     * Laravel 8+ compatibility method.
     *
     * @return \Illuminate\Events\Dispatcher|null
     */
    protected function getApiEventDispatcher()
    {
        try {
            return app('events');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if the current Laravel version supports the event system properly.
     *
     * @return bool
     */
    protected function supportsEvents()
    {
        return $this->getApiEventDispatcher() !== null && $this->eventsEnabled;
    }
}
