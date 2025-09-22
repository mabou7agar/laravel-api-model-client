<?php

namespace MTechStack\LaravelApiModelClient\Traits;

/**
 * Placeholder trait retained for backwards compatibility.
 *
 * The previous morphTo override had to be removed because returning loaded models
 * from a relationship method breaks Eloquent's expectations and can cause loops.
 */
trait MorphsToApiModels
{
    // Intentionally left blank.
}
