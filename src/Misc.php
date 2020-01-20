<?php

namespace Cws\EloquentModelGenerator;

use Illuminate\Support\Str;

class Misc
{

    public static function endsWith(string $searchInto, string $stringToLookFor): bool
    {
        $len = strlen($stringToLookFor);
        if ($len == 0) {
            return true;
        }
        return (substr($searchInto, -$len) === $stringToLookFor);
    }

    public static function startsWith($string, $startString): bool
    {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }

    public static function appPath($subPath = null): string
    {
        return empty($subPath) ? base_path('app') :
            base_path('app/' . trim($subPath, "/"));
    }

    /**
     * @param $tableName
     * @param $allTablesName
     * @return bool
     */
    public static function isTableNameARelationTableName(string $tableName, array $allTablesName): bool
    {
        $single = [];
        $containedInTableName = [];
        $singleCurrentTable = Str::singular($tableName);
        foreach ($allTablesName as $p) {
            $sin = Str::singular($p);
            $single[] = $sin;
            if (strpos($tableName, $sin) !== false && $sin !== $singleCurrentTable) {
                $containedInTableName[] = $sin;
            }
        }
        $countContained = count($containedInTableName);
        if ($countContained < 2) {
            return false;
        }
        if ($countContained > 1 && (count(array_intersect($containedInTableName, $single)) == $countContained)) {
            $first = explode("_", $containedInTableName[0]);
            $second = explode("_", $containedInTableName[1]);
            if (empty(array_intersect($first, $second)) && empty(array_intersect($second, $first))) {
                return true;
            }
        }
        return false;
    }
}
