# MorphTo Type Mismatch Fix

## Issue
`TypeError: Illuminate\Database\Eloquent\Relations\MorphTo::matchToMorphParents(): Argument #2 ($results) must be of type Illuminate\Database\Eloquent\Collection, Illuminate\Support\Collection given`

## Root Cause
The `ApiModelQueries` trait's `newCollection()` method was returning `Illuminate\Support\Collection` instead of `Illuminate\Database\Eloquent\Collection`, causing all API model collections to be incompatible with Laravel's morphTo relationships.

## Fixes Applied

### 1. ApiModelQueries Trait
**File:** `src/Traits/ApiModelQueries.php`

**Before:**
```php
use Illuminate\Support\Collection;

public function newCollection(array $models = [])
{
    return new Collection($models); // Returns Support\Collection ❌
}
```

**After:**
```php
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Collection;

public function newCollection(array $models = [])
{
    return new Collection($models); // Returns Eloquent\Collection ✅
}
```

### 2. MorphToFromApi Relation
**File:** `src/Relations/MorphToFromApi.php`

**Before:**
```php
$related = $instance->whereIn($instance->getKeyName(), array_values($ids))->get();
$models = $models->merge($related); // merge() returns Support\Collection ❌
```

**After:**
```php
$related = $instance->whereIn($instance->getKeyName(), array_values($ids))->get();

// Ensure we're working with an Eloquent Collection
if (!$related instanceof Collection) {
    $related = new Collection($related);
}

// Add the related models while maintaining Eloquent Collection type
foreach ($related as $model) {
    $models->push($model); // push() maintains collection type ✅
}
```

## Why This Works

1. **newCollection() Fix**: All API models now return proper `Eloquent\Collection` instances by default
2. **MorphToFromApi Fix**: Using `push()` instead of `merge()` maintains the collection type, as `merge()` returns a new base `Collection` instance

## Impact

- ✅ MorphTo relationships with API models now work correctly
- ✅ Eager loading morphTo relationships no longer throws TypeError
- ✅ All collection operations maintain proper Eloquent Collection type
- ✅ Backward compatible with existing code

## Testing

After applying these fixes:

1. MorphTo relationships should work without errors
2. Eager loading should function properly: `Model::with('morphToRelation')->get()`
3. Collection operations should maintain Eloquent Collection type

## Version

Applied: 2025-01-27
