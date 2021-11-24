<?php

namespace N1Creator;

class Relationship
{
    public $name;
    public $type;
    public $model;
    public $foreignKey;
    public $ownerKey;
    public $pivot;
    public $table;
    public $foreignPivotKeyName;

    public function __construct($relationship = [])
    {
        if ($relationship) {
            $this->name = $relationship['name'];
            $this->type = $relationship['type'];
            $this->model = $relationship['model'];
            $this->foreignKey = $relationship['foreignKey'];
            $this->ownerKey = $relationship['ownerKey'];
            $this->pivot = $relationship['pivot'];
            $this->table = $relationship['table'];
            $this->foreignPivotKeyName = $relationship['foreignPivotKeyName'];
        }
    }
}
