<?php

namespace Cws\EloquentModelGenerator\Processor;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Illuminate\Database\DatabaseManager;
use Cws\CodeGenerator\Model\DocBlockModel;
use Cws\CodeGenerator\Model\PropertyModel;
use Cws\CodeGenerator\Model\UseClassModel;
use Cws\CodeGenerator\Model\UseTraitModel;
use Cws\CodeGenerator\Model\VirtualPropertyModel;
use Cws\EloquentModelGenerator\Config;
use Cws\EloquentModelGenerator\Model\EloquentModel;
use Cws\EloquentModelGenerator\TypeRegistry;
use Illuminate\Support\Str;

/**
 * Class FieldProcessor
 * @package Cws\EloquentModelGenerator\Processor
 */
class FieldProcessor implements ProcessorInterface
{
    /**
     * @var DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var TypeRegistry
     */
    protected $typeRegistry;

    /**
     * FieldProcessor constructor.
     * @param DatabaseManager $databaseManager
     * @param TypeRegistry $typeRegistry
     */
    public function __construct(DatabaseManager $databaseManager, TypeRegistry $typeRegistry)
    {
        $this->databaseManager = $databaseManager;
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @inheritdoc
     */
    public function process(EloquentModel $model, Config $config)
    {
        $schemaManager = $this->databaseManager->connection($config->get('connection'))->getDoctrineSchemaManager();
        $prefix = $this->databaseManager->connection($config->get('connection'))->getTablePrefix();

        $tableDetails = $schemaManager->listTableDetails($prefix . $model->getTableName());
        $primaryColumnNames = $tableDetails->getPrimaryKey() ? $tableDetails->getPrimaryKey()->getColumns() : [];
        $timestampsColumns = ['created_at', 'updated_at'];
        $timestampsCounter = 0;
        $columnNames = [];
        $mappings = [];
        $rules = [];

        $fillableProperty = new PropertyModel('table', 'protected', $prefix . $model->getTableName());
        $fillableProperty->setDocBlock(new DocBlockModel('@var string'));

        $model->addProperty($fillableProperty);

        $this->processTranslation($model, $schemaManager);

        foreach ($tableDetails->getColumns() as $column) {
            $columnName = $column->getName();
            $model->addProperty(new VirtualPropertyModel(
                $columnName,
                $this->typeRegistry->resolveType($column->getType()->getName())
            ));

            if (!in_array($columnName, $primaryColumnNames) && !in_array($columnName, $timestampsColumns)) {
                $columnNames[] = $columnName;
                $mappings[$columnName] = $this->getValidMappingFromColumnType($column->getType()->getName());
                $rules[$columnName] = $this->getRules($column, $mappings[$columnName]);
            } else {
                if (in_array($columnName, $timestampsColumns))
                    $timestampsCounter++;
            }
        }

        $fillableProperty = new PropertyModel('fillable');
        $fillableProperty->setAccess('protected')
            ->setValue($columnNames)
            ->setDocBlock(new DocBlockModel('@var array'));
        $model->addProperty($fillableProperty);

        $fillableProperty = new PropertyModel('casts');
        $fillableProperty->setAccess('protected')
            ->setValue($mappings)
            ->setDocBlock(new DocBlockModel('@var array'));
        $model->addProperty($fillableProperty);

        $fillableProperty = new PropertyModel('rules');
        $fillableProperty->setAccess('public')->setStatic(true)
            ->setValue($rules)
            ->setDocBlock(new DocBlockModel('@var array'));
        $model->addProperty($fillableProperty);

        $this->checkTimestampsAndSoftDeletes($model, $columnNames, ($timestampsCounter < count($timestampsColumns)));

        return $this;
    }

    private function checkTimestampsAndSoftDeletes(&$model, $columnNames, $excludeTimestamps = false)
    {
        if (in_array('deleted_at', $columnNames)) {
            $model->addUses(new UseClassModel("Illuminate\Database\Eloquent\SoftDeletes"));
            $model->addTrait(new UseTraitModel("SoftDeletes"));
        }
        if ($excludeTimestamps) {
            $fillableProperty = new PropertyModel('timestamps', 'public', false);
            $fillableProperty->setDocBlock(new DocBlockModel('@var bool'));
            $model->addProperty($fillableProperty);
        }
    }

    private function getValidMappingFromColumnType($columnType)
    {
        switch ($columnType) {
            case "json":
                return "array";
            case "text":
                return "string";
            case "datetimetz":
                return "string";
            case "blob":
                return "string";
            case "decimal":
                return "float";
            case "guid":
                return "string";
            case "smallint":
                return "integer";
            case "bigint":
                return "integer";
            default:
                return $columnType;
                break;
        }
    }

    /**
     * @param Column $column
     */
    private function getRules(Column $column, $mapping)
    {
        //$this->typeRegistry->resolveType($column->getType()->getName())
        $rules = [];
        if ($column->getNotnull())
            array_push($rules, "required");
        else
            array_push($rules, "nullable");
        switch ($mapping) {
            case "string":
                $length = $column->getLength();
                if (is_numeric($length))
                    array_push($rules, "max:" . $length);
                break;
            case "integer":
                array_push($rules, "integer");
                break;
            case "float":
                array_push($rules, "numeric");
                break;
            case "boolean":
                array_push($rules, "boolean");
                break;
            case "date":
                array_push($rules, "date");
                break;
            case "datetime":
                array_push($rules, "date");
                break;
        }
        return implode("|", $rules);
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 5;
    }

    private function processTranslation(EloquentModel &$model, AbstractSchemaManager $schemaManager)
    {
        $translationTable = $this->checkIfHasTranslation($model, $schemaManager);
        if (!empty($translationTable)) {
            $this->getTranslatedAttributes($model, $translationTable);
            $model->addUses(new UseClassModel("Dimsav\Translatable\Translatable"));
            $model->addTrait(new UseTraitModel("Translatable"));
        }
    }

    private function checkIfHasTranslation(EloquentModel $model, AbstractSchemaManager $schemaManager)
    {
        $translationTableName = Str::singular($model->getTableName()) . "_translations";
        if ($schemaManager->tablesExist([$translationTableName]))
            return $schemaManager->listTableDetails($translationTableName);
    }

    private function getTranslatedAttributes(EloquentModel &$model, Table $translationTable)
    {
        $columns = [];
        $primaryColumnNames = $translationTable->getPrimaryKey() ? $translationTable->getPrimaryKey()->getColumns() : [];
        $foreignKeys = ($translationTable->getForeignKeys());
        if (count($foreignKeys)) {
            foreach ($foreignKeys as $fk) {
                $tableForeignColumns = $fk->getColumns();
                foreach ($tableForeignColumns as $columnName)
                    array_push($primaryColumnNames, $columnName);
            }
        }

        array_push($primaryColumnNames, "locale");
        foreach ($translationTable->getColumns() as $column) {
            if (!in_array($column->getName(), $primaryColumnNames))
                array_push($columns, $column->getName());
        }
        $fillableProperty = new PropertyModel('translatedAttributes');
        $fillableProperty->setAccess('public')
            ->setValue($columns)
            ->setDocBlock(new DocBlockModel('@var array'));
        $model->addProperty($fillableProperty);
    }
}
