<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Illuminate\Support\Facades\Event;

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
        static::registerModelEvents();
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
     *
     * @param  string  $event
     * @return void
     */
    protected static function registerModelEvent($event)
    {
        static::registerModelObserver($event);
    }

    /**
     * Register a model observer.
     *
     * @param  string  $event
     * @return void
     */
    protected static function registerModelObserver($event)
    {
        static::$dispatcher->listen("api-model.{$event}: " . static::class, function ($model, $data = null) use ($event) {
            if (method_exists($model, 'fireCustomModelEvent')) {
                return $model->fireCustomModelEvent($event, $data);
            }
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
        if (! $this->eventsEnabled) {
            return null;
        }

        $method = 'on' . ucfirst($event);

        if (method_exists($this, $method)) {
            $result = $this->{$method}($data);

            if (! is_null($result)) {
                return $result;
            }
        }

        return static::$dispatcher->until(
            "api-model.{$event}: " . static::class, [$this, $data]
        );
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
}
