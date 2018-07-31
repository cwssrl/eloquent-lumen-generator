<?php

namespace Cws\EloquentModelGenerator\Processor;

use Doctrine\DBAL\Schema\Table;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Illuminate\Database\Eloquent\Relations\HasOne as EloquentHasOne;
use Illuminate\Support\Str;
use Cws\CodeGenerator\Model\DocBlockModel;
use Cws\CodeGenerator\Model\MethodModel;
use Cws\CodeGenerator\Model\VirtualPropertyModel;
use Cws\EloquentModelGenerator\Config;
use Cws\EloquentModelGenerator\Exception\GeneratorException;
use Cws\EloquentModelGenerator\Helper\EmgHelper;
use Cws\EloquentModelGenerator\Model\BelongsTo;
use Cws\EloquentModelGenerator\Model\BelongsToMany;
use Cws\EloquentModelGenerator\Model\EloquentModel;
use Cws\EloquentModelGenerator\Model\HasMany;
use Cws\EloquentModelGenerator\Model\HasOne;
use Cws\EloquentModelGenerator\Model\Relation;

/**
 * Class RelationProcessor
 * @package Cws\EloquentModelGenerator\Processor
 */
class RelationProcessor implements ProcessorInterface
{
    /**
     * @var DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var EmgHelper
     */
    protected $helper;

    /**
     * FieldProcessor constructor.
     * @param DatabaseManager $databaseManager
     * @param EmgHelper $helper
     */
    public function __construct(DatabaseManager $databaseManager, EmgHelper $helper)
    {
        $this->databaseManager = $databaseManager;
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function process(EloquentModel $model, Config $config)
    {
        if (!ends_with($model->getTableName(), "_translations")) {

            $schemaManager = $this->databaseManager->connection($config->get('connection'))->getDoctrineSchemaManager();
            $prefix = $this->databaseManager->connection($config->get('connection'))->getTablePrefix();

            $foreignKeys = $schemaManager->listTableForeignKeys($prefix . $model->getTableName());
            foreach ($foreignKeys as $tableForeignKey) {
                $tableForeignColumns = $tableForeignKey->getForeignColumns();
                if (count($tableForeignColumns) !== 1) {
                    continue;
                }

                $relation = new BelongsTo(
                    $this->removePrefix($prefix, $tableForeignKey->getForeignTableName()),
                    $tableForeignKey->getLocalColumns()[0],
                    $tableForeignColumns[0]
                );
                $this->addRelation($model, $relation);
            }

            $tables = $schemaManager->listTables();
            $names = [];
            foreach ($tables as $table)
                array_push($names, $table->getName());

            foreach ($tables as $table) {
                if ($table->getName() === $prefix . $model->getTableName()) {
                    continue;
                }
                $foreignKeys = $table->getForeignKeys();
                foreach ($foreignKeys as $name => $foreignKey) {
                    if ($foreignKey->getForeignTableName() === $prefix . $model->getTableName()) {
                        $localColumns = $foreignKey->getLocalColumns();
                        if (count($localColumns) !== 1) {
                            continue;
                        }
                        $isTableNameARelationTableName = self::isTableNameARelationTableName($table->getName(), $names);
                        if (count($foreignKeys) === 2 && ((count($table->getColumns()) === 2) || ((count($table->getColumns()) > 2 && $isTableNameARelationTableName)))) {
                            $keys = array_keys($foreignKeys);
                            $key = array_search($name, $keys) === 0 ? 1 : 0;
                            $secondForeignKey = $foreignKeys[$keys[$key]];
                            $secondForeignTable = $this->removePrefix($prefix, $secondForeignKey->getForeignTableName());
                            $pivots = array_diff(array_keys($table->getColumns()),
                                ['created_at', 'updated_at', $secondForeignKey->getLocalColumns()[0], $localColumns[0]]);
                            $relation = new BelongsToMany(
                                $secondForeignTable,
                                $this->removePrefix($prefix, $table->getName()),
                                $localColumns[0],
                                $secondForeignKey->getLocalColumns()[0],
                                ($table->hasColumn('created_at') && $table->hasColumn('updated_at')),
                                $pivots

                            );
                            $this->addRelation($model, $relation);

                            break;
                        } else {
                            $tableName = $this->removePrefix($prefix, $foreignKey->getLocalTableName());
                            $foreignColumn = $localColumns[0];
                            $localColumn = $foreignKey->getForeignColumns()[0];

                            if ($this->isColumnUnique($table, $foreignColumn)) {
                                $relation = new HasOne($tableName, $foreignColumn, $localColumn);
                            } else {
                                $relation = new HasMany($tableName, $foreignColumn, $localColumn);
                            }

                            $this->addRelation($model, $relation);
                        }
                    }
                }
            }
        }
    }

    private function isTableNameARelationTableName($tableName, $allTablesName)
    {
        $singol = [];
        $containedInTableName = [];
        $singolarizedCurrentTable = Str::singular($tableName);
        foreach ($allTablesName as $p) {
            $sin = Str::singular($p);
            $singol[] = $sin;
            if (strpos($tableName, $sin) !== false && $sin !== $singolarizedCurrentTable)
                $containedInTableName[] = $sin;
        }
        $countContained = count($containedInTableName);
        if ($countContained < 2)
            return false;
        if ($countContained > 1 && (count(array_intersect($containedInTableName, $singol)) == $countContained)) {
            $first = explode("_", $containedInTableName[0]);
            $second = explode("_", $containedInTableName[1]);
            if (empty(array_intersect($first, $second)) && empty(array_intersect($second, $first)))
                return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 5;
    }

    /**
     * @param Table $table
     * @param string $column
     * @return bool
     */
    protected function isColumnUnique(Table $table, $column)
    {
        foreach ($table->getIndexes() as $index) {
            $indexColumns = $index->getColumns();
            if (count($indexColumns) !== 1) {
                continue;
            }
            $indexColumn = $indexColumns[0];
            if ($indexColumn === $column && $index->isUnique()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param EloquentModel $model
     * @param Relation $relation
     * @throws GeneratorException
     */
    protected function addRelation(EloquentModel $model, Relation $relation)
    {
        $relationClass = Str::singular(Str::studly($relation->getTableName()));
        if ($relation instanceof HasOne) {
            $name = Str::singular(Str::camel($relation->getTableName()));
            $docBlock = sprintf('@return \%s', EloquentHasOne::class);

            $virtualPropertyType = $relationClass;
        } elseif ($relation instanceof HasMany) {
            $name = $this->getValidMethodNameForHasMany($model, $relation); // Str::plural(Str::camel($relation->getTableName()));
            $docBlock = sprintf('@return \%s', EloquentHasMany::class);

            $virtualPropertyType = sprintf('%s[]', $relationClass);
        } elseif ($relation instanceof BelongsTo) {
            $relationKey = $this->resolveArgument(
                $relation->getForeignColumnName(),
                $this->helper->getDefaultForeignColumnName($relation->getTableName()));
            if (empty($relationKey))
                $name = Str::singular(Str::camel($relation->getTableName()));
            else {
                if (ends_with($relationKey, "_id"))
                    $relationKey = substr($relationKey, 0, -3);
                $name = Str::singular(Str::camel($relationKey));
            }
            $docBlock = sprintf('@return \%s', EloquentBelongsTo::class);

            $virtualPropertyType = $relationClass;
        } elseif ($relation instanceof BelongsToMany) {
            $name = Str::plural(Str::camel($relation->getTableName()));
            $docBlock = sprintf('@return \%s', EloquentBelongsToMany::class);

            $virtualPropertyType = sprintf('%s[]', $relationClass);
        } else {
            throw new GeneratorException('Relation not supported');
        }

        $method = new MethodModel($name);

        $method->setBody($this->createMethodBody($model, $relation));
        $method->setDocBlock(new DocBlockModel($docBlock));
        $model->addMethod($method);
        $model->addProperty(new VirtualPropertyModel($name, $virtualPropertyType));
    }

    private function getValidMethodNameForHasMany(EloquentModel $model, Relation $relation)
    {
        $name = Str::plural(Str::camel($relation->getTableName()));
        $thisModelName = Str::snake($model->getName()->getName());
        $foreignColumnName = $relation->getForeignColumnName();
        if (($thisModelName . "_id") === $foreignColumnName)
            if (!in_array($name, $model->getMethodNames()))
                return $name;
            else
                return ("HasMany" . $name);
        $foreignColumnName = Str::singular($relation->getTableName()) .
            Str::plural(Str::ucfirst((ends_with($foreignColumnName, "_id") ? substr($foreignColumnName, 0, -3) : $foreignColumnName)));
        return Str::plural(Str::camel($foreignColumnName));
    }

    /**
     * @param EloquentModel $model
     * @param Relation $relation
     * @return string
     */
    protected function createMethodBody(EloquentModel $model, Relation $relation)
    {
        $reflectionObject = new \ReflectionObject($relation);
        $name = Str::camel($reflectionObject->getShortName());

        $arguments = [
            $model->getNamespace()->getNamespace() . '\\' . Str::singular(Str::studly($relation->getTableName()))
        ];
        $timestamps = false;
        $pivots = null;
        if ($relation instanceof BelongsToMany) {
            $timestamps = $relation->getWithTimestamps();
            $pivots = $relation->getPivotsAsString();
            $defaultJoinTableName = $this->helper->getDefaultJoinTableName(
                $model->getTableName(),
                $relation->getTableName()
            );
            $joinTableName = $relation->getJoinTable() === $defaultJoinTableName
                ? null
                : $relation->getJoinTable();
            $arguments[] = $joinTableName;

            $arguments[] = $this->resolveArgument(
                $relation->getForeignColumnName(),
                $this->helper->getDefaultForeignColumnName($model->getTableName())
            );
            $arguments[] = $this->resolveArgument(
                $relation->getLocalColumnName(),
                $this->helper->getDefaultForeignColumnName($relation->getTableName())
            );
        } elseif ($relation instanceof HasMany) {
            $arguments[] = $this->resolveArgument(
                $relation->getForeignColumnName(),
                $this->helper->getDefaultForeignColumnName($model->getTableName())
            );
            $arguments[] = $this->resolveArgument(
                $relation->getLocalColumnName(),
                EmgHelper::DEFAULT_PRIMARY_KEY
            );
        } else {
            $arguments[] = $this->resolveArgument(
                $relation->getForeignColumnName(),
                $this->helper->getDefaultForeignColumnName($relation->getTableName())
            );
            $arguments[] = $this->resolveArgument(
                $relation->getLocalColumnName(),
                EmgHelper::DEFAULT_PRIMARY_KEY
            );
        }

        return sprintf('return $this->%s(%s)%s%s;', $name,
            $this->prepareArguments($arguments),
            $timestamps ? "->withTimestamps()" : "",
            empty($pivots) ? "" : ("->withPivot(" . $pivots . ")"));

    }

    /**
     * @param array $array
     * @return array
     */
    protected function prepareArguments(array $array)
    {
        $array = array_reverse($array);
        $milestone = false;
        foreach ($array as $key => &$item) {
            if (!$milestone) {
                if (!is_string($item)) {
                    unset($array[$key]);
                } else {
                    $milestone = true;
                }
            } else {
                if ($item === null) {
                    $item = 'null';

                    continue;
                }
            }
            $item = sprintf("'%s'", $item);
        }

        return implode(', ', array_reverse($array));
    }

    /**
     * @param string $actual
     * @param string $default
     * @return string|null
     */
    protected function resolveArgument($actual, $default)
    {
        return $actual === $default ? null : $actual;
    }

    /**
     * todo: move to helper
     * @param string $prefix
     * @param string $tableName
     * @return string
     */
    protected function addPrefix($prefix, $tableName)
    {
        return $prefix . $tableName;
    }

    /**
     * todo: move to helper
     * @param string $prefix
     * @param string $tableName
     * @return string
     */
    protected function removePrefix($prefix, $tableName)
    {
        return preg_replace("/^$prefix/", '', $tableName);
    }
}