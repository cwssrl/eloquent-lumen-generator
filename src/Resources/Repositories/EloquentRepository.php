<?php

namespace App\Repositories;

use App\Exceptions\GenericException;

abstract class EloquentRepository implements RepositoryContract
{
    protected $model;

    protected $entity_name = 'entity';

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([
            $this->model,
            $method
        ], $parameters);
    }

    /**
     * Return Query Builder Object for custom queries on repository model
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQuery()
    {
        return $this->model->newQuery();
    }

    public function findOrThrowException($id)
    {
        $result = $this->newQuery()->find($id);

        if (!is_null($result))
            return $result;

        throw new GenericException(trans('exceptions.not-found', ['item' => trans_choice('items.item', 1), 'id' => $id]));
    }

    public function findWithTrashedOrThrowException($id)
    {
        $result = $this->newQuery()->withTrashed()->where('id', $id)->first();

        if (!is_null($result))
            return $result;

        throw new GenericException(trans('exceptions.not-found', ['item' => trans_choice('items.item', 1), 'id' => $id]));
    }

    public function firstOrThrowException()
    {
        $result = $this->newQuery()->first();

        if (!is_null($result))
            return $result;

        throw new GenericException(trans('exceptions.no-element'));
    }

    public function getAll($order_by = 'id', $sort = 'asc')
    {
        return $this->newQuery()->orderBy($order_by, $sort)->get();
    }

    public function getPaginated($per_page, $order_by = 'id', $sort = 'asc')
    {
        return $this->newQuery()->orderBy($order_by, $sort)->paginate($per_page);
    }

    public function getFields($fields)
    {
        return $this->newQuery()->select($fields)->get();
    }
}