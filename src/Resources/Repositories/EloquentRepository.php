<?php

namespace App\Repositories;

use App\Exceptions\GenericException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->model)))
            $result = $this->newQuery()->withTrashed()->where('id', $id)->first();
        else
            $result = $this->newQuery()->where('id', $id)->first();
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
        if (isset($this->model->translatedAttributes) && in_array($sort, $this->model->translatedAttributes)) {
            $query = $this->joinWithTransTable($query);
        }
        if ($trashed && (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->model))))
            return $query->withTrashed()->orderBy($sort, $order)->get();
        else
            return $query->orderBy($sort, $order)->get();
    }

    public function getPaginated($per_page, $order = 'asc', $sort = 'id', Builder $query = null, $trashed = false)
    {
        $query = empty($query) ? $this->newQuery() : $query;
        if (isset($this->model->translatedAttributes) && in_array($sort, $this->model->translatedAttributes)) {
            $query = $this->joinWithTransTable($query);
        }
        if ($trashed && (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->model))))
            return $query->withTrashed()->orderBy($sort, $order)->paginate($per_page);
        else
            return $query->orderBy($sort, $order)->paginate($per_page);
    }

    private function joinWithTransTable($query)
    {
        $modelShortName = (new \ReflectionClass($this->model))->getShortName();
        $transTableName = $this->getTableNameByModelName($modelShortName . "Translation");
        $tableName = $this->getTableNameByModelName($modelShortName);
        return $query->join($transTableName, $transTableName . "." . str_singular($tableName) . "_id", "=", $tableName . ".id");
    }

    /**
     * Get Items by request parameters
     *
     * @param Request $request
     * @param string $relationToLoad Relation to load with
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection|mixed|static[]
     */
    public function getByRequest(Request $request, $relationToLoad = null)
    {
        $allFieldsQuery = $order = $sort = $paginate = $trashed = $queries = $queryableFields = $queryType = null;

        //get the values from query string
        $queries = $this->getQueryStringValues($request, $allFieldsQuery,
            $order, $sort, $paginate,
            $trashed, $queryableFields, $queryType);
        $queries = empty($queries) ? [] : $queries;
        //load query by field requested
        if ($request->has("id")) {
            $queries = array_merge($queries, ["id" => $request->get('id')]);
        }
        $query = $this->buildQueryByRequestFields($queryableFields, $queryType, $allFieldsQuery, $queries);
        if (!empty($relationToLoad))
            $query->with([$relationToLoad]);
        return empty($paginate) ? $this->getAll($order, $sort, $query, $trashed) :
            $this->getPaginated($paginate, $order, $sort, $query, $trashed);
    }

    /**
     * Build query by request parameters
     *
     * @param array $queryableFields
     * @param $queryType
     * @param null $allFieldQueries
     * @param array $queries
     * @return EloquentRepository|Builder
     */
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

    /**
     * Build a query with where on fields specified by queries
     *
     * @param Builder $query
     * @param $queryType
     * @param $queries
     * @param array $casts
     * @return $this|Builder|static
     */
    private function loadFieldsSpecificQuery(Builder $query, $queryType, $queries, array $casts)
    {
        if (empty($queries) || !count($queries))
            return $query;
        $translatedAttributes = (isset($this->model->translatedAttributes) && !empty($this->model->translatedAttributes))
            ? $this->model->translatedAttributes : [];
        $isOrQuery = ($queryType === "or");
        foreach ($queries as $field => $value) {
            $explodedField = explode("-", $field);
            $fieldName = $explodedField[0];
            //get the operator to use in query based on its type
            $operator = (isset($casts[$fieldName]) && $casts[$fieldName] === 'string') ? (config('database.default') === 'pgsql' ? "ilike" : "like") : "=";
            $isNullValue = ($value === "null");
            $isValueValidDatetime = self::isValidDate($value);
            $booleanValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $numericValue = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $isValidTimestamp = (is_numeric($value) && (int)$value == $value);
            $stringValue = trim($value);
            $stringValue = "%" . trim($stringValue, "%") . "%";
            $this->buildQueryClauseForField($casts, $translatedAttributes, $field, $operator, $value,
                $isNullValue, $booleanValue, $numericValue, $isValidTimestamp,
                $isValueValidDatetime, $stringValue, $isOrQuery, $query);
        }
        return $query;
    }

    private function buildQueryClauseForField($casts, $translatedAttributes, $currentField,
                                              $likeOperator, $value, $isNullValue, $booleanValue, $numericValue,
                                              $isValidTimestamp, $isValueValidDatetime, $ilikeValue, $isOrQuery, &$query)
    {
        $cast = null;
        if (isset($casts[$currentField])) {
            $cast = $casts[$currentField];
        } else {
            if (!is_null($currentField)) {
                $splittedField = explode(".", $currentField);
                $temp = last($splittedField);
                $cast = isset($casts[$temp]) ? $casts[$temp] : null;
                if (empty($cast)) {
                    $splittedField = explode("-", $currentField);
                    $temp = array_first($splittedField);
                    $cast = isset($casts[$temp]) ? $casts[$temp] : null;
                }
            }
        }
        if ($isNullValue) {
            $query = $isOrQuery ? $query->orWhereNull($currentField) : $query->whereNull($currentField);
        } elseif (!empty($cast)) {
            switch ($cast) {
                case "integer":
                    if ($numericValue !== null)
                        $query = $isOrQuery ? $query->orWhere($currentField, "=", intval($numericValue)) : $query->where($currentField, "=", intval($numericValue));
                    break;
                case "datetime":
                    if ($isValueValidDatetime)
                        $query = $isOrQuery ? $query->orWhere($currentField, "=", $value) : $query->where($currentField, "=", $value);
                    break;
                case "float":
                    if ($numericValue !== null)
                        $query = $isOrQuery ? $query->orWhere($currentField, "=", $numericValue) : $query->where($currentField, "=", $numericValue);
                    break;
                case "real":
                    if ($numericValue !== null)
                        $query = $isOrQuery ? $query->orWhere($currentField, "=", $numericValue) : $query->where($currentField, "=", $numericValue);
                    break;
                case "double":
                    if ($numericValue !== null)
                        $query = $isOrQuery ? $query->orWhere($currentField, "=", $numericValue) : $query->where($currentField, "=", $numericValue);
                    break;
                case "date":
                    if ($isValueValidDatetime)
                        $query = $isOrQuery ? $query->orWhere($currentField, "=", $numericValue) : $query->where($currentField, "=", $numericValue);
                    break;
                case "boolean":
                    if ($booleanValue !== null)
                        $query = $query->orWhere($currentField, "=", $booleanValue);
                    break;
                case "timestamp":
                    if ($isValidTimestamp)
                        $query = $isOrQuery ? $query->orWhere($currentField, "=", $numericValue) : $query->where($currentField, "=", $numericValue);
                    break;
                case "array":
                    $value = explode(",", $value);
                    $explodedFieldName = $splittedField = explode("-", $currentField);
                    $queryToUseInArray = $this->getQueryForArrayField($explodedFieldName);
                    $query = $isOrQuery ? $query->orWhere(function ($q) use ($currentField, $value, $queryToUseInArray) {
                        foreach ($value as $current)
                            $q = $q->whereRaw($queryToUseInArray . "ILIKE" . " %" . $current . "%");
                    }) : $query->Where(function ($q) use ($currentField, $value, $queryToUseInArray) {

                        foreach ($value as $current)
                            $q = $q->whereRaw($queryToUseInArray . "ILIKE" . " '%" . $current . "%'");
                    });

                    break;
                default:
                    $query = $isOrQuery ? $query->orWhere($currentField, $likeOperator, $ilikeValue) : $query->where($currentField, $likeOperator, $ilikeValue);
                    break;
            }
        } elseif (in_array($currentField, $translatedAttributes))
            $query = $isOrQuery ? $this->orWhereTranslationLike($query, $currentField, $ilikeValue) : $this->whereTranslationLike($query, $currentField, $ilikeValue);
    }

    private function getQueryForArrayField($explodedFieldName)
    {
        $lastField = array_pop($explodedFieldName);
        $name = implode("->'", $explodedFieldName);
        if (count($explodedFieldName) > 1) {
            $name .= "'";
            $name .= "#>> '{{$lastField}}' ";
        }
        else
        {
            $name .= "{$lastField} #>> '{}' ";
        }
        return $name;
    }

    /**
     * Process the input translated fields returning it in a processable format by translatable
     *
     * @param array $input
     */
    protected function processTransInput(array &$input)
    {
        $transAttr = isset($this->model->translatedAttributes) ? $this->model->translatedAttributes : null;
        if (is_array($transAttr) && count($transAttr)) {
            if (isset($input['translations']))
                $input = self::flipTranslationMap($input, $transAttr);
            else {
                $input = self::flipTranslationArray($input, $transAttr);
            }
        }
    }

    /**
     * Build a query for all queryable fields
     *
     * @param $query
     * @param $allFieldQueries
     * @param array $queryableFields
     * @param $casts
     * @return Builder|static
     */
    protected function loadAllFieldQuery($query, $allFieldQueries, array $queryableFields, $casts)
    {
        $query = empty($query) ? $this->newQuery() : $query;
        $isValueValidDatetime = self::isValidDate($allFieldQueries);
        $booleanValue = filter_var($allFieldQueries, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $numericValue = filter_var($allFieldQueries, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $isValidTimestamp = (is_numeric($allFieldQueries) && (int)$allFieldQueries == $allFieldQueries);
        $stringValue = trim($allFieldQueries);
        $stringValue = "%" . trim($stringValue, "%") . "%";
        //get model translated attributes
        $translatedAttributes = (isset($this->model->translatedAttributes) && !empty($this->model->translatedAttributes))
            ? $this->model->translatedAttributes : [];
        //for each different type we need to check if allfieldqueries has a valid value
        $likeOperator = (config('database.default') === 'pgsql' ? "ilike" : "like");
        //normalize the query value for each fields based on its type
        foreach ($queryableFields as $currentField) {
            $this->buildQueryClauseForField($casts, $translatedAttributes, $currentField, $likeOperator,
                $allFieldQueries, false, $booleanValue, $numericValue, $isValidTimestamp,
                $isValueValidDatetime, $stringValue, true, $query);
        }
        return $query;
    }

    /**
     * Get all parameters of request
     *
     * @param Request $request
     * @param $allFieldsQuery query to perform to all fields
     * @param $order asc or desc
     * @param $sort fields which by sort
     * @param $paginate number of elements in paging
     * @param $trashed if true trashed will be included in results too
     * @param $queryableFields model queryable fields
     * @param $queryType "or" or "and"
     * @return array|null queries for specific fields
     */
    private function getQueryStringValues(Request $request,
                                          &$allFieldsQuery,
                                          &$order,
                                          &$sort,
                                          &$paginate,
                                          &$trashed,
                                          &$queryableFields,
                                          &$queryType)
    {
        $order = $request->has('order') ? $request['order'] : 'asc';
        $sort = $request->has('sort') ? (key_exists($request["sort"], $this->model->getCasts()) ? $request['sort'] : "id") : 'id';
        $paginate = $request->has('paginate') ? $request['paginate'] : null;
        $trashed = $request->has('trashed') ? !empty($request['trashed']) : false;
        $queryType = $request->has('query_type') ? $request['query_type'] : "and";
        $allFieldsQuery = null;
        //get all queryable fields in model merging fillable and translatedAttributes
        $queryableFields = (isset($this->model->translatedAttributes) && !empty($this->model->translatedAttributes)) ?
            array_merge($this->model->translatedAttributes, $this->model->getFillable()) :
            $this->model->getFillable();
        if ($this->model->timestamps)
            $queryableFields = array_merge($queryableFields, ['created_at', 'updated_at']);
        if(key_exists("id", $this->model->getCasts()))
        {
            $queryableFields = array_merge($queryableFields, ['id']);
        }
        // check if we need to make a query for all fields
        if ($request->has('all_fields')) {
            $allFieldsQuery = $request['all_fields'];
            return null;
        } else {
            //load fields specific queries
            $queries = [];
            $queryableFields = array_flip($queryableFields);

            foreach ($request->keys() as $key) {
                $explodedField = explode("-", $key);
                if (array_key_exists($explodedField[0], $queryableFields))
                    //$queries[$key] = $request[$explodedField[0]];
                    $queries[$key] = $request[$key];
            }

            return $queries;
        }

    }

    /**
     * Get all items including only selected fields
     *
     * @param mixed $fields
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getFields($fields)
    {
        return $this->newQuery()->select($fields)->get();
    }

    /**
     * Create new model by input
     *
     * @param array $input
     * @return mixed
     */
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

    /**
     * Updates an existing model
     *
     * @param array $input
     * @param null $modelId
     * @param null $model
     * @throws \Exception
     * @return mixed|null
     */
    public function update(array $input, $modelId = null, $model = null)
    {
        try {
            \DB::beginTransaction();
            $this->model = empty($model) ? $this->findWithTrashedOrThrowException($modelId) : $model;
            //check if model has translated fields and if an update is needed
            $transAttr = isset($this->model->translatedAttributes) ? $this->model->translatedAttributes : null;
            if (is_array($transAttr) && count($transAttr)) {
                self::processTransInput($input);
                $this->checkIfNeedToDeleteTranslation($input, $this->model);
            }
            if ($this->model->update($input)) {
                \DB::commit();
                return $this->model;
            } else {
                throw new \Exception("Impossibile aggiornare");
            }
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error($e);
            throw new \Exception($e->getMessage());
        }
    }

    private function checkIfNeedToDeleteTranslation(array $input, $model)
    {
        if ($model->has("translations")) {
            $validLanguages = array_flip(config("translatable.locales"));
            $lang = array_intersect_key($validLanguages, $input);
            $oldTrans = $model->translations()->select("locale")->pluck("locale")->toArray();
            $oldTrans = count($oldTrans) ? array_flip($oldTrans) : [];
            $toRemove = array_diff($oldTrans, $lang);
            if (count($toRemove)) {
                $model->deleteTranslations(array_keys($toRemove));
            }
        }
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

    /**
     * Check if date is a valid one
     *
     * @param string $date
     * @param string $format
     * @return bool
     */
    private static function isValidDate(string $date, string $format = "d/m/Y H:i:s")
    {
        try {
            Carbon::createFromFormat($format, $date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}