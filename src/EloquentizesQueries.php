<?php

namespace Korman\Eloquentize;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

trait EloquentizesQueries
{
    // The API's access point and a wrapper around the actual tree building.
    public static function eloquentize(Collection $result, array $relations): EloquentCollection
    {
        return (new Eloquentizer())->transform($result, static::nest(...$relations)->setRoot());
    }

    // Wraps ModelMeta::nest(). Avoids "new ModelMeta()" boilerplate.
    protected static function nest(string|ModelMeta ...$relations): ModelMeta
    {
        $meta = new ModelMeta(static::class);

        foreach ($relations as $relation) {
            $meta->nest(is_string($relation) ? new ModelMeta($relation) : $relation);
        }

        return $meta;
    }
}