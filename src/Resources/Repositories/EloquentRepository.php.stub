<?php

namespace App\Repositories;

use App\Exceptions\GenericException;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
     * Find a model by its id, if does not exist returns null
     *
     * @param int $id
     * @return Collection|\Illuminate\Database\Eloquent\Model|mixed|null|static|static[]
     */
    public function find($id)
    {
        return $this->newQuery()->find($id);
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

    /**
     * Find a model by its id, if does not exist throw an exception
     *
     * @param int $id
     * @return Collection|\Illuminate\Database\Eloquent\Model|mixed|null|static|static[]
     * @throws GenericException
     */
    public function findOrThrowException($id)
    {
        $result = $this->newQuery()->find($id);

        if (!is_null($result))
            return $result;

        throw new GenericException(trans('exceptions.not-found', ['item' => trans_choice('items.item', 1), 'id' => $id]));
    }

    /**
     * Get the first model found on db, if no element is found throw an exception
     *
     * @return \Illuminate\Database\Eloquent\Model|mixed|null|object|static
     * @throws GenericException
     */
    public function firstOrThrowException()
    {
        $result = $this->newQuery()->first();

        if (!is_null($result))
            return $result;

        throw new GenericException(trans('exceptions.no-element'));
    }

    /**
     * Get Items by request parameters
     *
     * @param Request $request
     * @param string|null $relationToLoad
     * @param array|null $fieldsToSelect
     * @return LengthAwarePaginator|Collection|mixed|static[]
     */
    public function getByRequest(Request $request, $relationToLoad = null, array $fieldsToSelect = null)
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
        if (!empty($fieldsToSelect)) {
            $query = $query->select($fieldsToSelect);
        }
        if (!empty($relationToLoad))
            $query->with([$relationToLoad]);
        return empty($paginate) ? $this->getAll($order, $sort, $query, $trashed) :
            $this->getPaginated($paginate, $order, $sort, $query, $trashed);
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
    protected function getQueryStringValues(Request $request,
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
                $fieldName = explode("^", $explodedField[0]);
                $fieldName = count($fieldName) > 1 ? $fieldName[1] : $fieldName[0];
                if (array_key_exists($fieldName, $queryableFields))
                    //$queries[$key] = $request[$explodedField[0]];
                    $queries[$key] = $request[$key];
            }

            return $queries;
        }

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
    protected function buildQueryByRequestFields(array $queryableFields, $queryType, $allFieldQueries = null, array $queries = [])
    {
        //We wrap new repo new query into model new query to make repo clauses surrounded by parenthesis
        /** @var Builder $query */
        $query = $this->newQuery();

        if (empty($queryableFields) || (empty($allFieldQueries) && !count($queries)))
            return $query;
        //TODO Test queries and parenthesis
        $query = $query->where(function ($query) use ($queryableFields, $queries, $queryType) {
            $casts = [];
            /*if (isset($this->model->translatedAttributes)) {
                $temp = (get_class($this->model) . "Translation");
                if (class_exists($temp))
                    $casts = (new $temp)->getCasts();
            }*/
            $casts = array_merge($casts, $this->model->getCasts());
            //check if we need to query a value in all fields or if we have to build a query only for specific fields
            if (!empty($allFieldQueries))
                $query = $this->loadAllFieldQuery($query, $allFieldQueries, $queryableFields, $casts);
            else {
                $query = $this->loadFieldsSpecificQuery($query, $queryType, $queries, $casts);
            }
            return $query;
        });
        return $query;
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
     * Check if date is a valid one
     *
     * @param string $date
     * @param string $format
     * @return bool
     */
    private static function isValidDate(string $date = null, string $format = "d/m/Y H:i:s")
    {
        try {
            if (empty($date))
                return null;
            Carbon::createFromFormat($format, $date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function buildQueryClauseForField($casts, $translatedAttributes, $currentField,
                                              $likeOperator, $value, $isNullValue, $booleanValue, $numericValue,
                                              $isValidTimestamp, $isValueValidDatetime, $ilikeValue, $isOrQuery, &$query,
                                              $isNotQuery = false)
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
                    $temp = reset($splittedField);
                    $cast = isset($casts[$temp]) ? $casts[$temp] : null;
                }
            }
        }
        if ($isNullValue) {
            if ($isNotQuery) {
                $query = $isOrQuery ? $query->orWhereNotNull($currentField) : $query->whereNotNull($currentField);
            } else {
                $query = $isOrQuery ? $query->orWhereNull($currentField) : $query->whereNull($currentField);
            }
        } elseif (!empty($cast)) {
            $otherCastsOperator = $isNotQuery ? "<>" : "=";
            switch ($cast) {
                case "integer":
                    if ($numericValue !== null)
                        $query = $isOrQuery ?
                            $query->orWhere($currentField, $otherCastsOperator, intval($numericValue)) :
                            $query->where($currentField, $otherCastsOperator, intval($numericValue));
                    break;
                case "datetime":
                    if ($isValueValidDatetime)
                        $query = $isOrQuery ?
                            $query->orWhere($currentField, $otherCastsOperator, $value) :
                            $query->where($currentField, $otherCastsOperator, $value);
                    break;
                case "float":
                    if ($numericValue !== null)
                        $query = $isOrQuery ?
                            $query->orWhere($currentField, $otherCastsOperator, $numericValue) :
                            $query->where($currentField, $otherCastsOperator, $numericValue);
                    break;
                case "real":
                    if ($numericValue !== null)
                        $query = $isOrQuery ?
                            $query->orWhere($currentField, $otherCastsOperator, $numericValue) :
                            $query->where($currentField, $otherCastsOperator, $numericValue);
                    break;
                case "double":
                    if ($numericValue !== null)
                        $query = $isOrQuery ?
                            $query->orWhere($currentField, $otherCastsOperator, $numericValue) :
                            $query->where($currentField, $otherCastsOperator, $numericValue);
                    break;
                case "date":
                    if ($isValueValidDatetime)
                        $query = $isOrQuery ?
                            $query->orWhere($currentField, $otherCastsOperator, $numericValue) :
                            $query->where($currentField, $otherCastsOperator, $numericValue);
                    break;
                case "boolean":
                    if ($booleanValue !== null)
                        $query = $query->orWhere($currentField, $otherCastsOperator, $booleanValue);
                    break;
                case "timestamp":
                    if ($isValidTimestamp)
                        $query = $isOrQuery ?
                            $query->orWhere($currentField, $otherCastsOperator, $numericValue) :
                            $query->where($currentField, $otherCastsOperator, $numericValue);
                    break;
                case "array":
                    $value = explode(",", $value);
                    $explodedFieldName = $splittedField = explode("-", $currentField);
                    $queryToUseInArray = $this->getQueryForArrayField($explodedFieldName);
                    $query = $isOrQuery ? $query->orWhere(
                        function ($q) use ($currentField, $value, $queryToUseInArray, $likeOperator) {
                            foreach ($value as $current) {
                                $current = " '"
                                    . (stripos($likeOperator, "like") ? ("%" . $current . "%") : $current)
                                    . "'";
                                $q = $q->whereRaw($queryToUseInArray . " " . $likeOperator . $current);
                            }
                        }) : $query->Where(function ($q) use ($currentField, $value, $queryToUseInArray, $likeOperator) {

                        foreach ($value as $current) {
                            $current = " '"
                                . (stripos($likeOperator, "like") ? ("%" . $current . "%") : $current)
                                . "'";
                            $q = $q->whereRaw($queryToUseInArray . " " . $likeOperator . $current);
                        }
                    });

                    break;
                default:
                    $query = $isOrQuery ?
                        $query->orWhere($currentField, $likeOperator, $ilikeValue) :
                        $query->where($currentField, $likeOperator, $ilikeValue);
                    break;
            }
        } elseif (in_array($currentField, $translatedAttributes))
            $query = $isOrQuery ?
                $this->orWhereTranslationLike($query, $currentField, $ilikeValue, null, $likeOperator) :
                $this->whereTranslationLike($query, $currentField, $ilikeValue);
    }

    private function getQueryForArrayField($explodedFieldName)
    {
        /* $originalExplodedFieldName = $explodedFieldName;
         $lastField = array_pop($explodedFieldName);
         $name = implode("->'", $explodedFieldName);
         $originalLength = count($originalExplodedFieldName);
         if ($originalLength > 1) {
             $name .= $originalLength > 2 ? "'" : "";
             $name .= " #>> '{{$lastField}}' ";
         } else {
             $name .= "{$lastField} #>> '{}' ";
         }
         return $name;*/

        $originalExplodedFieldName = $explodedFieldName;
        $originalLength = count($originalExplodedFieldName);
        if ($originalLength > 1) {
            $firstName = array_shift($explodedFieldName);
            $lastField = implode(",", $explodedFieldName);
            $name = $firstName . " #>> '{{$lastField}}' ";
        } else {
            $name = "{$explodedFieldName[0]} #>> '{}' ";
        }
        return $name;
    }

    public function orWhereTranslationLike(Builder $query, $key, $value, $locale = null, $operator = "ILIKE")
    {
        $tableName = $this->getTableNameByModelName((new \ReflectionClass($this->model))->getShortName() . "Translation");
        return $query->orWhereHas('translations', function (Builder $query) use ($key, $value, $locale, $tableName, $operator) {
            $query->where($tableName . '.' . $key, $operator, $value);
            if ($locale) {
                $query->where($tableName . '.' . $this->getLocaleKey(), $operator, $locale);
            }
        });
    }

    private function getTableNameByModelName($modelName)
    {
        return Str::plural(Str::snake($modelName));
    }

    public function whereTranslationLike(Builder $query, $key, $value, $locale = null, $operator = "ILIKE")
    {
        $tableName = $this->getTableNameByModelName((new \ReflectionClass($this->model))->getShortName() . "Translation");
        return $query->whereHas('translations', function (Builder $query) use ($key, $value, $locale, $tableName, $operator) {
            $query->where($tableName . '.' . $key, $operator, $value);
            if ($locale) {
                $query->where($tableName . '.' . $this->getLocaleKey(), $operator, $locale);
            }
        });
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
    protected function loadFieldsSpecificQuery(Builder $query, $queryType, $queries, array $casts)
    {
        if (empty($queries) || !count($queries))
            return $query;
        $translatedAttributes = (isset($this->model->translatedAttributes) && !empty($this->model->translatedAttributes))
            ? $this->model->translatedAttributes : [];
        $isOrQuery = ($queryType === "or");
        foreach ($queries as $field => $value) {

            $parameters = $this->getQueryParameters($field, $value, $isOrQuery, $casts);
            $this->buildQueryClauseForField($casts,
                $translatedAttributes,
                $parameters["field_name"],
                $parameters["operator"],
                $parameters["value"],
                $parameters["is_null_value"],
                $parameters["boolean_value"],
                $parameters["numeric_value"],
                $parameters["is_valid_timestamp"],
                $parameters["is_value_valid_datetime"],
                $parameters["string_value"],
                $parameters["is_or_query"],
                $query,
                $parameters["is_not_query"]
            );
        }
        return $query;
    }

    private function getQueryParameters($inputField,
                                        $inputValue,
                                        $globalIsOrQuery,
                                        $casts
    )
    {
        $output = [];
        //a json field name could be multilevel, so we have to split it by "-"
        $fieldNameOnDb = explode("-", $inputField)[0];
        $fieldNameOnDb = explode("^", $fieldNameOnDb);
        $fieldNameOnDb = count($fieldNameOnDb) > 1 ? $fieldNameOnDb[1] : $fieldNameOnDb[0];
        //$output["field_name"] = $output["exploded_field"][0];
        //we can insert the and/or option before the name splitting by "^"
        $explodedFieldName = explode("^", $inputField);
        $output["is_or_query"] = $globalIsOrQuery;
        if (count($explodedFieldName) > 1) {
            $output["field_name"] = $explodedFieldName[1];
            $output["is_or_query"] = (strtolower($explodedFieldName[0]) === "or");
        } else {
            $output["field_name"] = $explodedFieldName[0];
        }

        $isLikeQuery = $this->startsWith($inputValue, "??");
        $isNotQuery = $this->startsWith($inputValue, "!!");
        if ($isLikeQuery || $isNotQuery) {
            $inputValue = substr($inputValue, 2);
        }
        $output["is_not_query"] = $isNotQuery;

        $output["value"] = $inputValue;
        //get the operator to use in query based on its type
        $output["operator"] = $isNotQuery ? "<>" : (($isLikeQuery && isset($casts[$fieldNameOnDb]) &&
            ($casts[$fieldNameOnDb] === 'string' || $casts[$fieldNameOnDb] === 'array')) ?
            (config('database.default') === 'pgsql' ? "ilike" : "like") : "=");
        $output["is_null_value"] = ($inputValue === "null");
        $output["is_value_valid_datetime"] = self::isValidDate($inputValue);
        $output["boolean_value"] = filter_var($inputValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $output["numeric_value"] = filter_var($inputValue, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $output["is_valid_timestamp"] = (is_numeric($inputValue) && (int)$inputValue == $inputValue);
        $stringValue = trim($inputValue);
        $output["string_value"] = $isLikeQuery ? ("%" . trim($stringValue, "%") . "%") : $stringValue;
        return $output;
    }

    /**
     * Get all models on db
     *
     * @param string $order
     * @param string $sort
     * @param Builder|null $query
     * @param bool $trashed
     * @return Collection|mixed|static[]
     */
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

    private function joinWithTransTable($query)
    {
        $modelShortName = (new \ReflectionClass($this->model))->getShortName();
        $transTableName = $this->getTableNameByModelName($modelShortName . "Translation");
        $tableName = $this->getTableNameByModelName($modelShortName);
        return $query->join($transTableName, $transTableName . "." . str_singular($tableName) . "_id", "=", $tableName . ".id");
    }

    /**
     * Get paginated elements
     *
     * @param int $per_page
     * @param string $order
     * @param string $sort
     * @param Builder|null $query
     * @param bool $trashed
     * @return LengthAwarePaginator|mixed
     */
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

    /**
     * Get all items including only selected fields
     *
     * @param mixed $fields
     * @return Collection|static[]
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
        //check if model has translated fields and if an update is needed
        $transAttr = isset($this->model->translatedAttributes) ? $this->model->translatedAttributes : null;
        if (is_array($transAttr) && count($transAttr))
            self::processTransInput($input);
        $this->model = $this->model->fill($input);
        //dd($this->model);
        if ($this->model->save())
            return $this->model;
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
     * Used to flip the translation array from input "translations" field to a format useful for translatable saving
     *
     * @param array $input
     * @param array|null $fieldNamesToFlip
     * @return array
     */
    public static function flipTranslationMap(array $input, array $fieldNamesToFlip = null)
    {
        /*** Input Example **/
        /*$inputMap =
            [{

                "artwork_id": 1213,
                "artwork_name": "Hirthe Vista",
                "description": null,
                "locale": "it"
            },
            {

                "artwork_id": 1213,
                "artwork_name": "Test",
                "description": "gatto",
                "locale": "en"
            }
    ]
        ];
        $fieldNamesToFlip = ["title", "content"];*/
        /*** End input Example **/
        $defLocale = config('translatable.locale');
        $allTranslations = $input['translations'];
        foreach ($allTranslations as $fieldName => $translations) {
            $currentLanguage = isset($translations['locale']) ? $translations['locale'] : $defLocale;
            unset($translations['locale']);
            foreach ($translations as $transKey => $transValue) {
                if ((empty($fieldNamesToFlip) || in_array($transKey, $fieldNamesToFlip))) {
                    if (isset($input[$currentLanguage])) {
                        $input[$currentLanguage] = array_merge($input[$currentLanguage], [$transKey => $transValue]);
                    } else {
                        $input[$currentLanguage] = [$transKey => $transValue];
                    }
                }
            }
        }

        unset($input['translations']);
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

        return $input;
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
     * Updates an existing model
     *
     * @param array $input
     * @param null $modelId
     * @param null $model
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

    /**
     * Find a model by its id, searching in soft deleted too, if does not exist throw an exception
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model|mixed|null|object|static
     * @throws GenericException
     */
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

    private function endsWith(string $searchInto, string $stringToLookFor)
    {
        $len = strlen($stringToLookFor);
        if ($len == 0) {
            return true;
        }
        return (substr($searchInto, -$len) === $stringToLookFor);
    }

    private function startsWith($string, $startString)
    {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }
}
