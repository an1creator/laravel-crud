<?php

namespace N1Creator;

use N1Creator\Relationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class Actions
{
    protected $model;
    protected $relationship;

    public function __construct(Model $model, Relationship $relationship)
    {
        $this->model = $model;
        $this->relationship = $relationship;
    }

    public static function with(Model $model, Relationship $relationship)
    {
        return new static($model, $relationship);
    }

    public function createHasOne($values): Model
    {
        return $this->relationship->model::create(array_merge([$this->relationship->foreignKey => $this->model->id], $values));
    }

    public function updateHasOne($values): ?Model
    {
        $model = $this->relationship->model::where($this->relationship->foreignKey, $this->model->id)->first();
        if (!blank($model)) {
            $model->fill(array_merge([$this->relationship->foreignKey => $this->model->id], $values));
            $model->save();
        }

        return $model;
    }

    public function updateMorphTo($values): ?Model
    {
        $model = (new $this->relationship->model)::where(
            str_replace($this->relationship->name . '_', '', $this->relationship->foreignKey),
            $this->model->{$this->relationship->foreignKey}
        )->first();
        if (!blank($model)) {
            $model->fill($values);
            $model->save();
        }

        return $model;
    }

    public function updateBelongsToMany($values): void
    {
        $this->model->{$this->relationship->name}()->sync($values);
    }

    public function createBelongsToMany($values): void
    {
        $this->updateBelongsToMany($values);
    }

    public function updateBelongsToManyByGrouppedPivot($values): void
    {
        DB::table($this->relationship->table)->where($this->relationship->foreignPivotKeyName, $this->model->id)->delete();
        foreach ($values as $value) {
            foreach ($value->toArray() as $v) {
                DB::table($this->relationship->table)->insert(
                    array_merge(
                        [$this->relationship->foreignPivotKeyName => $this->model->id],
                        $v
                    )
                );
            }
        }
    }

    public function saveHasOne($values): Model
    {
        $model = $this->updateHasOne($values);
        if (blank($model)) {
            $model = $this->createHasOne($values);
        }

        return $model;
    }

    public function createBelongsTo($values): Model
    {
        $model = $this->relationship->model::create($values);
        $this->model->{$this->relationship->foreignKey} = $model->{$this->relationship->ownerKey};
        return $model;
    }

    public function createHasMany($values): void
    {
        foreach ($values as $value) {
            $this->relationship->model::create(array_merge([$this->relationship->foreignKey => $this->model->id], $value));
        }
    }

    public function updateHasMany($values): void
    {
        $exceptIds = [];
        foreach ($values as $value) {
            if (isset($value['id'])) {
                $exceptIds[] = $value['id'];
            }
        }

        if (!blank($exceptIds)) {
            $this->relationship->model::where([$this->relationship->foreignKey => $this->model->id])
                ->whereNotIn('id', $exceptIds)
                ->delete();

            foreach ($values as $value) {
                if (isset($value['id'])) {
                    $this->relationship->model::where([$this->relationship->foreignKey => $this->model->id])
                        ->where(['id' => $value['id']])
                        ->update(Arr::except($value, ['id']));
                } else {
                    $this->createHasMany([
                        $value
                    ]);
                }
            }
        } else {
            $this->relationship->model::where([$this->relationship->foreignKey => $this->model->id])
                ->delete();
            $this->createHasMany($values);
        }
    }
}
