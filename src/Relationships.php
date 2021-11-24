<?php

namespace N1Creator;

use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Collection;
use N1Creator\Actions;

class Relationships
{
    private $model;
    private $relationships;
    private $selected = [];

    public function __construct($model)
    {
        $this->load($model);
    }

    protected function boot(): void
    {
        $this->relationships = new Collection;

        $relationNames = ['HasOne', 'HasMany', 'BelongsTo', 'BelongsToMany', 'MorphToMany', 'MorphTo'];
        foreach ((new ReflectionClass($this->model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $returnType = $method->getReturnType();

            if (blank($returnType) || !in_array(class_basename($returnType->getName()), $relationNames)) {
                continue;
            }

            $model = $method->invoke($this->model);

            if (!is_object($model) || !($model instanceof Relation)) {
                continue;
            }

            $reflectionModel = new ReflectionClass($model);
            $ownerKey = null;
            if ($reflectionModel->hasMethod('getOwnerKey'))
                $ownerKey = $model->getOwnerKey();
            else {
                $segments = explode('.', $model->getQualifiedParentKeyName());
                $ownerKey = $segments[count($segments) - 1];
            }

            $foreignKey = null;
            if ($reflectionModel->hasMethod('getForeignKey')) {
                $foreignKey = $model->getForeignKey();
            } else if ($reflectionModel->hasMethod('getForeignKeyName')) {
                $foreignKey = $model->getForeignKeyName();
            } else if ($reflectionModel->hasMethod('getRelatedPivotKeyName')) {
                $foreignKey = $model->getRelatedPivotKeyName();
            }

            $rel = new Relationship([
                'name' => $method->getName(),
                'type' => $reflectionModel->getShortName(),
                'model' => (new ReflectionClass($model->getRelated()))->getName(),
                'foreignKey' => $foreignKey,
                'ownerKey' => $ownerKey,
                'table' => $reflectionModel->hasMethod('getTable') ? $model->getTable() : '',
                'foreignPivotKeyName' => $reflectionModel->hasMethod('getForeignPivotKeyName') ? $model->getForeignPivotKeyName() : '',
                'pivot' => $reflectionModel->hasMethod('getPivotColumns') ? $model->getPivotColumns() : []
            ]);

            $this->relationships->put($rel->name, $rel);
        }
    }

    public function load($model): void
    {
        $this->model = $model;
        $this->boot();
    }

    public function byForeignKey($key)
    {
        $relationships = new Collection;

        foreach ($this->relationships as $name => $relationship) {
            if ($relationship->foreignKey === $key) {
                $relationships->put($name, $relationship);
            }
        }

        $this->selected = $relationships;

        return $this;
    }

    public function byType($types)
    {
        if (is_string($types)) {
            $types = [$types];
        }

        $relationships = new Collection;

        foreach ($this->relationships as $name => $relationship) {
            if (in_array($relationship->type, $types)) {
                $relationships->put($name, $relationship);
            }
        }

        $this->selected = $relationships;

        return $this;
    }

    public function byName($name)
    {
        $relationships = new Collection;
        foreach ($this->relationships as $relationshipName => $relationship) {
            if ($relationshipName === $name) {
                $relationships->put($name, $relationship);
            }
        }

        $this->selected = $relationships;

        return $this;
    }

    public function get()
    {
        return $this->selected;
    }

    public function flush()
    {
        $this->selected = [];

        return $this;
    }

    public function all()
    {
        return $this->relationships->all();
    }

    public function keys()
    {
        return $this->relationships->keys()->toArray();
    }

    protected function createRelation($relationship, $values): void
    {
        $relation = Actions::with($this->model, $relationship);
        $type = 'create' . $relationship->type;
        $relation->$type($values);
    }

    public function createRelations($values): void
    {
        foreach ($this->selected as $relationshipName => $relationship) {
            if (isset($values[$relationshipName])) {
                $this->createRelation($relationship, $values[$relationshipName]);
            }
        }
    }

    public function updateRelations($values): void
    {
        foreach ($this->selected as $relationshipName => $relationship) {
            if (isset($values[$relationshipName])) {
                $this->updateRelation($relationship, $values[$relationshipName]);
            }
        }
    }

    public function updateRelation($relationship, $values): void
    {
        $relation = Actions::with($this->model, $relationship);
        $type = 'update' . $relationship->type;
        $relation->$type($values);
    }
}
