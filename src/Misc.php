<?php

namespace Cws\EloquentModelGenerator;

class Misc
{

    static function endsWith(string $searchInto, string $stringToLookFor)
    {
        $len = strlen($stringToLookFor);
        if ($len == 0) {
            return true;
        }
        return (substr($searchInto, -$len) === $stringToLookFor);
    }

    static function startsWith($string, $startString)
    {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }

    static function appPath($subPath = null)
    {
        return empty($subPath) ? base_path('app') :
            base_path('app/' . trim($subPath, "/"));
    }
}