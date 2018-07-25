<?php

namespace App\Repositories;

use App\Exceptions\GenericException;
use Illuminate\Http\Request;

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

    public function find($id)
    {
        return $this->newQuery()->find($id);
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

    public function getByRequest(Request $request)
    {
        $orderBy = $request->has('order_by') ? $request['order_by'] : 'id';
        $sort = $request->has('sort') ? $request['sort'] : 'asc';
        $paginate = $request->has('paginate') ? $request['paginate'] : null;
        return empty($paginate) ? $this->getAll($orderBy, $sort) : $this->getPaginated($paginate, $orderBy, $sort);
    }

    public function getFields($fields)
    {
        return $this->newQuery()->select($fields)->get();
    }

    public function create(array $input)
    {
        $classOf = get_class($this->model);
        $this->model = new $classOf();

        $transAttr = isset($this->model->translatedAttributes) ? $this->model->translatedAttributes : null;
        if (is_array($transAttr) && count($transAttr))
            $input = self::flipTranslationArray($input, $transAttr);

        $this->model = $this->model->fill($input);

        if ($this->model->save())
            return $this->model;
    }

    public function update(array $input, $modelId = null, $model = null)
    {
        $transAttr = isset($this->model->translatedAttributes) ? $this->model->translatedAttributes : null;
        if (is_array($transAttr) && count($transAttr))
            $input = self::flipTranslationArray($input, $transAttr);
        $this->model = empty($model) ? $this->findWithTrashedOrThrowException($modelId) : $model;
        if ($this->model->update($input))
            return $this->model;
    }

    /**
     * Used to flip the translation array from form to a format useful for translatable saving
     *
     * @param array $inputArray
     * @param array|null $fieldNamesToFlip
     * @return array
     */
    public static function flipTranslationArray(array $inputArray, array $fieldNamesToFlip = null)
    {
        /*** Input Example **/
        /*$inputArray = [
            "title" => [
                "it" => "ciao",
                "en" => "hi",
            ],
            "content" => [
                "it" => "ita",
                "en" => "eng",
                "pt" => "pts"
            ],
            "district_id" => "1"
        ];
        $fieldNamesToFlip = ["title", "content"];*/
        /*** End input Example **/
        foreach ($inputArray as $fieldName => $translations) {
            if ((empty($fieldNamesToFlip) || in_array($fieldName, $fieldNamesToFlip))) {
                if (is_array($translations))
                    foreach ($translations as $lang => $translatedValue) {
                        if (isset($inputArray[$lang])) {
                            $inputArray[$lang] = array_merge($inputArray[$lang], [$fieldName => $translatedValue]);
                        } else {
                            $inputArray[$lang] = [$fieldName => $translatedValue];
                        }
                    }
                else {
                    $defLocale = config('translatable.locale');
                    if (isset($inputArray[$defLocale])) {
                        $inputArray[$defLocale] = array_merge($inputArray[$defLocale], [$fieldName => $translations]);
                    } else {
                        $inputArray[$defLocale] = [$fieldName => $translations];
                    }
                }
                unset($inputArray[$fieldName]);
            }
        }

        /*** Output Example ***/
        /*
        array:4 [▼
            "district_id" => "1"
            "it" => array:2 [▼
                "title" => "ciao"
                "content" => "ita"
            ]
            "en" => array:2 [▼
                "title" => "hi"
                "content" => "eng"
            ]
            "pt" => array:1 [▼
                "content" => "pts"
            ]
        ]
        */
        /*** End Output Example ***/

        return $inputArray;
    }
}