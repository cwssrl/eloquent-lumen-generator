<?php

namespace Cws\EloquentModelGenerator;

class Misc
{

    public static function endsWith(string $searchInto, string $stringToLookFor)
    {
        $len = strlen($stringToLookFor);
        if ($len == 0) {
            return true;
        }
        return (substr($searchInto, -$len) === $stringToLookFor);
    }

    public static function startsWith($string, $startString)
    {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }

    public static function appPath($subPath = null)
    {
        return empty($subPath) ? base_path('app') :
            base_path('app/' . trim($subPath, "/"));
    }
}
