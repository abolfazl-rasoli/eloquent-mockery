<?php

namespace Imanghafoori\EloquentMockery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class FakeEloquentBuilder extends Builder
{
    public function __construct($model, $originalModel)
    {
        $this->query = new FakeBuilder($model);
        $this->model = $model;
        $this->originalModel = $originalModel;
    }

    public function get($columns = ['*'])
    {
        $models = [];
        foreach ($this->query->filterRows($this->originalModel) as $i => $row) {
            $model = new $this->originalModel;
            $model->exists = true;
            $row = $columns === ['*'] ? $row : Arr::only($row, $columns);
            $model->setRawAttributes($row);
            foreach (($this->originalModel)::$fakeRelations as $j => [$relName, $relModel, $relatedRow]) {
                $relModel = new $relModel;
                $relModel->setRawAttributes($relatedRow[$i]);
                $model->setRelation($relName, $relModel);
            }
            $models[] = $model;
        }

        return Collection::make($models);
    }

    public function first($columns = ['*'])
    {
        $filtered = $this->query->filterRows($this->originalModel);
        $data = $this->filterColumns($columns, $filtered)->first();

        if (! $data) {
            return null;
        }

        $this->originalModel::unguard();

        $model = new $this->originalModel($data);
        $model->exists = true;

        ($this->originalModel)::$firstModel = $model;

        return $model;
    }

    public function select($columns = ['*'])
    {
        return $this;
    }

    public function count($columns = '*')
    {
        return $this->query->filterRows($this->originalModel)->count();
    }

    public function orderBy($column, $direction = 'asc')
    {
        return $this;
    }

    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        return $this;
    }

    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this;
    }

    public function rightJoin($table, $first, $operator = null, $second = null)
    {
        return $this;
    }

    public function innerJoin()
    {
        return $this;
    }

    public function create($data = [])
    {
        $model = clone $this->model;
        $model->exists = true;
        ($this->originalModel)::$saveCalls[] = $data;

        return $model;
    }

    public function delete()
    {
        $count = parent::delete();
        if ($count === 1) {
            ($this->originalModel)::$deletedModels[] = $this->model;

            foreach (($this->originalModel)::$fakeRows as $i => $row) {
                if ($row['id'] === $this->model->id) {
                    unset(($this->originalModel)::$fakeRows[$i]);
                }
            }
        }
    }

    private function filterColumns($columns, $filtered)
    {
        if ($columns !== ['*']) {
            $filtered = $filtered->map(function ($item) use ($columns) {
                return Arr::only($item, $columns);
            });
        }

        return $filtered;
    }

    public function setModel(Model $model)
    {
        $this->model = $model;

        return $this;
    }
}
