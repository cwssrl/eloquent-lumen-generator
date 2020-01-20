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
    public function getShortClassName($fullClassName): string
    {
        $pieces = explode('\\', $fullClassName);

        return end($pieces);
    }

    /**
     * @param string $className
     * @return string
     */
    public function getDefaultTableName($className): string
    {
        return Str::plural(Str::snake($className));
    }

    /**
     * @param string $table
     * @return string
     */
    public function getDefaultForeignColumnName($table): string
    {
        return sprintf('%s_%s', Str::singular($table), self::DEFAULT_PRIMARY_KEY);
    }

    /**
     * @param string $tableOne
     * @param string $tableTwo
     * @return string
     */
    public function getDefaultJoinTableName($tableOne, $tableTwo): string
    {
        $tables = [Str::singular($tableOne), Str::singular($tableTwo)];
        sort($tables);

        return sprintf(implode('_', $tables));
    }
}
