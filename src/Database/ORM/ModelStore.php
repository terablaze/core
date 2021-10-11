<?php

namespace TeraBlaze\Database\ORM;

use TeraBlaze\Support\ArrayMethods;

class ModelStore
{
    public static array $store = [];

    public static function retrieveType(string $type)
    {
        return static::$store[$type] ?? [];
    }

    public static function retrieve(string $type, $id)
    {
        $type = static::retrieveType($type);
        $id = static::formatId($id);
        return $type[$id] ?? null;
    }

    public static function store(string $type, $id, $modelInstance)
    {
        $id = static::formatId($id);
        static::$store[$type][$id] = $modelInstance;
    }

    public static function has(string $type, $id)
    {
        $id = static::formatId($id);
        return isset(static::$store[$type][$id]);
    }

    protected static function formatId($id)
    {
        return implode('_', ArrayMethods::wrap($id));
    }
}
