<?php

namespace N1Creator;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use N1Creator\Relationships;
use Illuminate\Support\Collection;

class Crud
{
    protected $model;

    protected $relationships;

    protected $additionalAttributes = [];

    protected $except = [];

    protected $prefilled = [];

    public function __construct($model)
    {
        $this->model = $model;
        $this->relationships = new Relationships($this->model);
    }

    public static function model(Model $model)
    {
        return new static($model);
    }

    public function create()
    {
        return $this->preFillAndExceptAttributes($this->getAttributes(), $this->prefilled);
    }

    protected function preFillAndExceptAttributes($attributes, $prefilled)
    {
        if (!blank($prefilled)) {
            foreach ($prefilled as $key => $value) {
                if (is_array($value) && isset($attributes[$key]) && is_array($attributes[$key])) {
                    $attributes[$key] = $this->preFillAndExceptAttributes($attributes[$key], $value);
                } else {
                    $attributes[$key] = $value;
                }
            }
        }

        if (is_array($attributes)) {
            foreach (array_keys($attributes) as $key) {
                if (in_array($key, $this->except)) {
                    unset($attributes[$key]);
                }
            }
        }

        return $attributes;
    }

    protected function getAttributes()
    {
        return array_merge($this->getModelColumns(), $this->getAdditionalColumns());
    }

    public function store($values): Model
    {
        $this->relationships->byType(['BelongsTo'])->createRelations($values);

        $this->model->fill($values);
        $this->model->save();

        $this->relationships->load($this->model);

        $this->relationships->byType(['HasOne', 'HasMany', 'BelongsToMany'])->createRelations($values);

        return $this->model;
    }

    public function edit()
    {
        $attributes = $this->getAttributes();
        foreach (array_keys($attributes) as $attribute) {
            $modelAttribute = $this->model->getAttribute($attribute);
            if (!blank($modelAttribute)) {
                $attributes[$attribute] = $this->model->getAttribute($attribute);
            }
        }

        $relations = $this->relationships->keys();
        foreach ($relations as $relation) {
            $this->model->with($relation);
            $attributes[$relation] = $this->model->{$relation};
        }

        return $this->prefillAndExceptAttributes($attributes, $this->prefilled);
    }

    public function update($values): Model
    {
        $this->relationships->byType(['HasOne', 'HasMany', 'MorphTo'])->updateRelations($values);

        $this->relationships->byType(['BelongsToMany'])->updateRelations($values);

        $this->model->fill($values);
        $this->model->save();

        return $this->model;
    }

    protected function getModelColumns(): array
    {
        $columns = [];
        $table = $this->model->getTable();
        $attributes = array_diff($this->model->getFillable(), $this->model->getHidden());
        $casts = $this->model->getCasts();

        foreach ($attributes as $attribute) {
            if (!in_array($attribute, $this->except)) {
                if (isset($casts[$attribute])) {
                    $type = $casts[$attribute];
                } else {
                    $type = Schema::getColumnType($table, $attribute);
                }

                switch ($type) {
                    case 'string':
                        $columns[$attribute] = '';
                        break;
                    case 'bigint':
                        $columns[$attribute] = 0;
                        break;
                    case 'integer':
                        $columns[$attribute] = 0;
                        break;
                    case 'boolean':
                        $columns[$attribute] = false;
                        break;
                    default:
                        $columns[$attribute] = null;
                        break;
                }
            }
        }

        return $columns;
    }

    protected function getAdditionalColumns(): array
    {
        $columns = [];
        foreach ($this->additionalAttributes as $additionalAttributeKey => $additionalAttributeValue) {
            if (!in_array($additionalAttributeKey, $this->except)) {
                $columns[$additionalAttributeKey] = $additionalAttributeValue;
            }
        }

        return $columns;
    }

    public function appendToModel($append): Crud
    {
        $this->additionalAttributes = array_merge($this->additionalAttributes, $append);

        return $this;
    }

    public function exceptAttributes($except): Crud
    {
        $this->except = $except;

        return $this;
    }

    public function setPrefilled($attributes): Crud
    {
        $this->prefilled = $attributes;

        return $this;
    }

    public function permissions()
    {
        $attributes = [];
        foreach (array_diff($this->model->getFillable(), $this->model->getHidden()) as $attribute) {
            if (!in_array($attribute, $this->except)) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }
}
