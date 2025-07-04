<?php

namespace Korman\Eloquentize;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Eloquentizer
{
    /**
     * Recursively transforms a flat query result into hierarchical Eloquent relations.
     *
     * @param Collection $result A Laravel collection containing raw query result rows.
     * @param ModelMeta $meta The metadata describing the parent model and its relations.
     *
     * @return EloquentCollection A collection of hydrated models with relations nested.
     */
    public function transform(Collection $result, ModelMeta $meta): EloquentCollection
    {
        // transform
        return $this->collectionToEloquentCollection($result
            // skip empty relations (e.g. left joins)
            ->filter(fn($row) => $row->{$meta->idColumn()} !== null)
            // group models
            ->groupBy($meta->idColumn())
            // instantiate each model and nest its relations
            ->map(function (Collection $modelRows) use ($meta) {
                // recursion inside
                return $this->nestRelations(
                    $meta->model->newFromBuilder($this->rowsToAttributes($modelRows, $meta)),
                    $modelRows,
                    $meta
                );
            }));
    }

    // Recursively nests relations. Inner method.
    protected function nestRelations(Model $hydratedModel, Collection $modelRows, ModelMeta $meta): Model
    {
        // recursive condition
        foreach ($meta->relations as $relationName => $relationMeta) {
            // recursive call
            $relation = $this->transform($modelRows, $relationMeta);

            $hydratedModel->setRelation(
                $relationName,
                $relationMeta->isRelationTypeMany ? $this->collectionToEloquentCollection($relation) : $relation->first())
            ;
        }

        return $hydratedModel;
    }

    // Turns result rows to model attributes by removing the model's prefix from the columns.
    protected function rowsToAttributes(Collection $modelRows, ModelMeta $meta): array
    {
        $attributes = [];
        foreach ($this->rowsToRow($modelRows) as $column => $value) {
            if ($attributeName = $meta->isColumnAttribute($column)) {
                $attributes[$attributeName] = $value;
            }
        }

        return $attributes;
    }

    // Collapses Model rows to a row. Makes sure stdClasses turn to arrays.
    protected function rowsToRow(Collection $modelRows): Collection
    {
        return $modelRows->map(fn($row) => (array) $row)->collapse();
    }

    // Turns a regular Collection to an Eloquent Collection with the keys reset to consecutive integers.
    protected function collectionToEloquentCollection(Collection $collection): EloquentCollection
    {
        return (new EloquentCollection($collection->all()))->values();
    }
}