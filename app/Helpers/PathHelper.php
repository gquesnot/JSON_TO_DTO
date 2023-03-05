<?php

namespace App\Helpers;

class PathHelper
{
    static function add($path, $name): string
    {
        if ($path == '') return $name;
        return $path . '.' . $name;
    }


    static function remove($path, $name): string
    {
        return str_replace($name, '', $path);
    }

    static function last($path): string
    {
        $parts = explode('.', $path);
        return $parts[count($parts) - 1];
    }


    static function before($path): string
    {
        $parts = explode('.', $path);
        if (count($parts) == 1) return '';
        return $parts[count($parts) - 2];
    }


    static function isRoot($path): bool
    {
        return $path == '';
    }

    static function replaceLast($path, $name): string
    {
        $parts = explode('.', $path);
        $parts[count($parts) - 1] = $name;
        return implode('.', $parts);
    }

    static function replace($path, $name, $newName): string
    {
        return str_replace($name, $newName, $path);
    }
}
