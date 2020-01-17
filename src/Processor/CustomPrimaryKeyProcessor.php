<?php

namespace Cws\EloquentModelGenerator\Processor;

use Illuminate\Database\DatabaseManager;
use Cws\CodeGenerator\Model\DocBlockModel;
use Cws\CodeGenerator\Model\PropertyModel;
use Cws\EloquentModelGenerator\Config;
use Cws\EloquentModelGenerator\Model\EloquentModel;
use Cws\EloquentModelGenerator\TypeRegistry;

/**
 * Class CustomPrimaryKeyProcessor
 * @package Cws\EloquentModelGenerator\Processor
 */
class CustomPrimaryKeyProcessor implements ProcessorInterface
{
    protected \Illuminate\Database\DatabaseManager $databaseManager;

    protected \Cws\EloquentModelGenerator\TypeRegistry $typeRegistry;

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
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function process(EloquentModel $model, Config $config)
    {
        $schemaManager = $this->databaseManager->connection($config->get('connection'))->getDoctrineSchemaManager();
        $prefix        = $this->databaseManager->connection($config->get('connection'))->getTablePrefix();

        $tableDetails = $schemaManager->listTableDetails($prefix . $model->getTableName());
        $primaryKey = $tableDetails->getPrimaryKey();
        if ($primaryKey === null) {
            return;
        }

        $columns = $primaryKey->getColumns();
        if (count($columns) !== 1) {
            return;
        }

        $column = $tableDetails->getColumn($columns[0]);
        if ($column->getName() !== 'id') {
            $primaryKeyProperty = new PropertyModel('primaryKey', 'protected', $column->getName());
            $primaryKeyProperty->setDocBlock(
                new DocBlockModel('The primary key for the model.', '', '@var string')
            );
            $model->addProperty($primaryKeyProperty);
        }
        if ($column->getType()->getName() !== 'integer') {
            $keyTypeProperty = new PropertyModel(
                'keyType',
                'protected',
                $this->typeRegistry->resolveType($column->getType()->getName())
            );
            $keyTypeProperty->setDocBlock(
                new DocBlockModel('The "type" of the auto-incrementing ID.', '', '@var string')
            );
            $model->addProperty($keyTypeProperty);
        }
        if ($column->getAutoincrement() !== true) {
            $autoincrementProperty = new PropertyModel('incrementing', 'public', false);
            $autoincrementProperty->setDocBlock(
                new DocBlockModel('Indicates if the IDs are auto-incrementing.', '', '@var bool')
            );
            $model->addProperty($autoincrementProperty);
        }
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 6;
    }
}
