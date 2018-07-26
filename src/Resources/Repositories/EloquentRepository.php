<?php

namespace App\Repositories;

use App\Exceptions\GenericException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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

    public function getAll($order = 'asc', $sort = 'id', Builder $query = null, $trashed = false)
    {
        $query = empty($query) ? $this->newQuery() : $query;
        if ($trashed)
            return $query->withTrashed()->orderBy($sort, $order)->get();
        else
            return $query->orderBy($sort, $order)->get();
    }

    public function getPaginated($per_page, $order = 'asc', $sort = 'id', Builder $query = null, $trashed = false)
    {
        $query = empty($query) ? $this->newQuery() : $query;
        if ($trashed)
            return $query->withTrashed()->orderBy($sort, $order)->paginate($per_page);
        else
            return $query->orderBy($sort, $order)->paginate($per_page);
    }

    public function getByRequest(Request $request)
    {
        $allFieldsQuery = $order = $sort = $paginate = $trashed = $queries = $queryableFields = $queryType = null;

        $queries = $this->getQueryStringValues($request, $allFieldsQuery,
            $order, $sort, $paginate,
            $trashed, $queryableFields, $queryType);
        $queries = empty($queries) ? [] : $queries;
        $query = $this->buildQueryByRequestFields($queryableFields, $queryType, $allFieldsQuery, $queries);
        return empty($paginate) ? $this->getAll($order, $sort, $query, $trashed) :
            $this->getPaginated($paginate, $order, $sort, $query, $trashed);
    }

    private function buildQueryByRequestFields(array $queryableFields, $queryType, $allFieldQueries = null, array $queries = [])
    {
        /** @var Builder $query */
        $query = $this->newQuery();

        if (empty($queryableFields) || (empty($allFieldQueries) && !count($queries)))
            return $query;
        $casts = $this->model->getCasts();
        if (!empty($allFieldQueries))
            $query = $this->loadAllFieldQuery($query, $allFieldQueries, $queryableFields, $casts);
        else {
            $query = $this->loadFieldsSpecificQuery($query, $queryType, $queries, $casts);
        }
        return $query;
    }

    private function loadFieldsSpecificQuery(Builder $query, $queryType, $queries, array $casts)
    {
        if (empty($queries) || !count($queries))
            return $query;

        foreach ($queries as $field => $value) {
            $operator = (isset($casts[$field]) && $casts[$field] === 'string') ? "ilike" : "=";
            $query = $queryType === 'or' ? $query->orWhere($field, $operator, $value) :
                $query->where($field, $operator, $value);
        }
        return $query;
    }

    private function loadAllFieldQuery($query, $allFieldQueries, array $queryableFields, $casts)
    {
        $query = empty($query) ? $this->newQuery() : $query;
        $isValueValidDatetime = self::isValidDate($allFieldQueries);
        $booleanValue = filter_var($allFieldQueries, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $numericValue = filter_var($allFieldQueries, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $isValidTimestamp = (is_numeric($allFieldQueries) && (int)$allFieldQueries == $allFieldQueries);
        foreach ($queryableFields as $currentField) {
            if (isset($casts[$currentField])) {
                switch ($casts[$currentField]) {
                    case "integer":
                        if ($numericValue !== null)
                            $query = $query->orWhere($currentField, "=", $numericValue);
                        break;
                    case "datetime":
                        if ($isValueValidDatetime)
                            $query = $query->orWhere($currentField, "=", $allFieldQueries);
                        break;
                    case "float":
                        if ($numericValue !== null)
                            $query = $query->orWhere($currentField, "=", $numericValue);
                        break;
                    case "real":
                        if ($numericValue !== null)
                            $query = $query->orWhere($currentField, "=", $numericValue);
                        break;
                    case "double":
                        if ($numericValue !== null)
                            $query = $query->orWhere($currentField, "=", $numericValue);
                        break;
                    case "date":
                        if ($isValueValidDatetime)
                            $query = $query->orWhere($currentField, "=", $allFieldQueries);
                        break;
                    case "boolean":
                        if ($booleanValue !== null)
                            $query = $query->orWhere($currentField, "=", $booleanValue);
                        break;
                    case "timestamp":
                        if ($isValidTimestamp)
                            $query = $query->orWhere($currentField, "=", $allFieldQueries);
                        break;
                    default:
                        $query = $query->orWhere($currentField, 'ilike', $allFieldQueries);
                        break;
                }
            }
        }
        return $query;
    }

    /**
     * @param Request $request
     * @param $allFieldsQuery
     * @param $order
     * @param $sort
     * @param $paginate
     * @param $trashed
     * @return array|null
     */
    private
    function getQueryStringValues(Request $request,
                                  &$allFieldsQuery,
                                  &$order,
                                  &$sort,
                                  &$paginate,
                                  &$trashed,
                                  &$queryableFields,
                                  &$queryType)
    {
        $order = $request->has('order') ? $request['order'] : 'asc';
        $sort = $request->has('sort') ? $request['sort'] : 'id';
        $paginate = $request->has('paginate') ? $request['paginate'] : null;
        $trashed = $request->has('trashed') ? !empty($request['trashed']) : false;
        $queryType = $request->has('query_type') ? !empty($request['query_type']) : "or";
        $allFieldsQuery = null;
        $queryableFields = (isset($this->model->translatedAttributes) && !empty($this->model->translatedAttributes)) ?
            array_merge($this->model->translatedAttributes, $this->model->getFillable()) :
            $this->model->getFillable();
        if ($this->model->timestamps)
            $queryableFields = array_merge($queryableFields, ['created_at', 'updated_at']);
        // check if we need to make a query for all fields
        if ($request->has('all_fields')) {
            $allFieldsQuery = $request['all_fields'];
            return null;
        } else {
            //load fields specific queries
            $queries = [];
            $queryableFields = array_flip($queryableFields);

            foreach ($request->keys() as $key) {
                if (array_key_exists($key, $queryableFields))
                    $queries[$key] = $request[$key];
            }
            return $queries;
        }

    }

    public
    function getFields($fields)
    {
        return $this->newQuery()->select($fields)->get();
    }

    public
    function create(array $input)
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

    public
    function update(array $input, $modelId = null, $model = null)
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
    public
    static function flipTranslationArray(array $inputArray, array $fieldNamesToFlip = null)
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

    private
    static function isValidDate(string $date, string $format = "d/m/Y H:i:s")
    {
        try {
            Carbon::createFromFormat($format, $date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}