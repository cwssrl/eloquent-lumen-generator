<?php

namespace Cws\EloquentModelGenerator;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Illuminate\Config\Repository as AppConfig;
use Illuminate\Database\DatabaseManager;

/**
 * Class TypeRegistry
 * @package Cws\EloquentModelGenerator
 */
class TypeRegistry
{
    protected array $types = [
        'array'        => 'array',
        'simple_array' => 'array',
        'json_array'   => 'string',
        'bigint'       => 'integer',
        'boolean'      => 'boolean',
        'datetime'     => 'string',
        'datetimetz'   => 'string',
        'date'         => 'string',
        'time'         => 'string',
        'decimal'      => 'float',
        'integer'      => 'int',
        'object'       => 'object',
        'smallint'     => 'integer',
        'string'       => 'string',
        'text'         => 'string',
        'binary'       => 'string',
        'blob'         => 'string',
        'float'        => 'float',
        'guid'         => 'string',
    ];

    protected \Illuminate\Database\DatabaseManager $databaseManager;

    /**
     * TypeRegistry constructor.
     * @param DatabaseManager $databaseManager
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    /**
     * @param string $type
     * @param string $value
     * @param string|null $connection
     */
    public function registerType($type, $value, $connection = null): void
    {
        $this->types[$type] = $value;

        $manager = $this->databaseManager->connection($connection)->getDoctrineSchemaManager();
        $manager->getDatabasePlatform()->registerDoctrineTypeMapping($type, $value);
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public function resolveType($type): string
    {
        return array_key_exists($type, $this->types) ? $this->types[$type] : 'mixed';
    }
}
