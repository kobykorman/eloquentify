<?php

namespace KobyKorman\Eloquentize;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ModelMeta
{
    // The Eloquent model this meta wraps around.
    public Model $model;

    // The model's class basename (e.g. App\Models\User -> User)
    protected string $basename;

    // Column prefix for this model in SQL query results
    protected string $prefix;

    // Whether this meta represents the root model in the hierarchy
    protected bool $isRoot = false;

    // Whether this relation represents a "many" relationship
    public bool $isRelationTypeMany = false;

    /**
     * Relation metas nested on this model
     *
     * @var array<string, self>
     */
    public self|array $relations = [];

    public function __construct(string $class)
    {
        $this->model = new $class;
        $this->basename = class_basename($class);
        $this->prefix = Str::snake($this->basename).'_';
    }

    // Returns the prefixed primary key column name for this model
    public function idColumn(): string
    {
        return $this->prefix.$this->model->getKeyName();
    }

    // Marks this as the root meta from which tree building begins
    public function setRoot(): self
    {
        $this->isRoot = true;

        $this->prefix = '';

        $this->prefixRecursively($this);

        return $this;
    }

    // Recursively applies parent prefixes to descendant relation metas
    protected function prefixRecursively(self $parentMeta, array $visitedPath = []): void
    {
        foreach ($parentMeta->relations as $relationName => $relationMeta) {
            $relationClass = get_class($relationMeta->model);

            // Check if we're creating a circular reference in the current path
            if (in_array($relationClass, $visitedPath)) {
                throw new LogicException("Circular relationship detected for $relationClass in model hierarchy");
            }

            // Add this class to the current path for this branch
            $currentPath = array_merge($visitedPath, [$relationClass]);

            // Apply prefix
            $relationMeta->prefix = $parentMeta->prefix . $relationMeta->prefix;

            // Recurse into child relations with the updated path
            if ($relationMeta->relations) {
                $this->prefixRecursively($relationMeta, $currentPath);
            }
        }
    }

    /**
     * Nests relation metas on this model
     * 
     * @param  self[]  $relations
     */
    public function nest(self ...$relations): self
    {
        foreach ($relations as $relation) {
            $this->relations[$relation->getRelationName($this->model)] = $relation;
        }

        return $this;
    }

    /**
     * Determines relation name and type based on available model methods
     *
     * @throws LogicException
     */
    protected function getRelationName(Model $parentModel): string
    {
        $singularRelationName = Str::camel($this->basename);
        $pluralRelationName = Str::plural($singularRelationName);

        if (method_exists($parentModel, $pluralRelationName)) {
            $this->isRelationTypeMany = true;
            return $pluralRelationName;
        }

        if (method_exists($parentModel, $singularRelationName)) {
            return $singularRelationName;
        }

        throw new LogicException(
            "The parent Model $parentModel is missing a method for the relationship with $this->basename. " .
            "Searched for the methods $singularRelationName() or $pluralRelationName(). " .
            "Ensure that the relation is correctly defined in your Eloquent model hierarchy."
        );
    }

    // If a column belongs to this model, the unprefixed attribute name returns, false otherwise
    public function isColumnAttribute(string $column): string|false
    {
        // Checks root because it has no prefix
        if (($this->isRoot || $this->isColumnPrefixed($column))
            // Checks if the column is an attribute of one of the relations
            && ! collect($this->relations)->contains(fn($relation) => $relation->isColumnPrefixed($column))) {

            return str_replace($this->prefix, '', $column);
        }

        return false;
    }

    // Checks if a column name starts with this model's prefix
    protected function isColumnPrefixed(string $column): bool
    {
        return Str::startsWith($column, $this->prefix);
    }
}