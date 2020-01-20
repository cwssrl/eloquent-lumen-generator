<?php

namespace Cws\EloquentModelGenerator\Helper;

use Illuminate\Support\Str;

/**
 * Class EmgHelper
 * @package Cws\EloquentModelGenerator\Helper
 */
class EmgHelper
{
    /**
     * @var string
     */
    public const DEFAULT_PRIMARY_KEY = 'id';

    /**
     * @param string $fullClassName
     * @return string
     */
    public function getShortClassName(string $fullClassName): string
    {
        $pieces = explode('\\', $fullClassName);

        return end($pieces);
    }

    /**
     * @param string $className
     * @return string
     */
    public function getDefaultTableName(string $className): string
    {
        return Str::plural(Str::snake($className));
    }

    /**
     * @param string $table
     * @return string
     */
    public function getDefaultForeignColumnName(string $table): string
    {
        return sprintf('%s_%s', Str::singular($table), self::DEFAULT_PRIMARY_KEY);
    }

    /**
     * @param string $tableOne
     * @param string $tableTwo
     * @return string
     */
    public function getDefaultJoinTableName(string $tableOne, string $tableTwo): string
    {
        $tables = [Str::singular($tableOne), Str::singular($tableTwo)];
        sort($tables);

        return sprintf(implode('_', $tables));
    }
}
