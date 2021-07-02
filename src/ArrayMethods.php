<?php

namespace TeraBlaze;

use ArrayAccess;

/**
 * Class ArrayMethods
 * @package TeraBlaze
 */
class ArrayMethods
{
    private function __construct()
    {
        // do nothing
    }

    /**
     * Determine whether the given value is array accessible.
     *
     * @param  mixed  $value
     * @return bool
     */
    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param ArrayAccess|array  $array
     * @param  string|int  $key
     * @return bool
     */
    public static function exists($array, $key)
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        return array_key_exists($key, $array);
    }

    public static function trim($array)
    {
        return array_map(function ($item) {
            return trim($item, " \t\n\r\0\x0B\"");
        }, $array);
    }

    public static function toObject($array)
    {
        $result = new class($array) extends \ArrayObject {};

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result->{$key} = self::toObject($value);
            } else {
                $result->{$key} = $value;
            }
        }

        return $result;
    }

    public static function toQueryString($array)
    {
        return http_build_query(
            self::clean(
                $array
            )
        );
    }

    public static function clean($array)
    {
        return array_filter($array, function ($item) {
            return !empty($item);
        });
    }

    public static function flatten($array, $return = array())
    {
        foreach ($array as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $return = self::flatten($value, $return);
            } else {
                $return[] = $value;
            }
        }

        return $return;
    }

    public static function first($array)
    {
        if (sizeof($array) == 0) {
            return null;
        }

        $keys = array_keys($array);
        return $array[$keys[0]];
    }

    public static function last($array)
    {
        if (sizeof($array) == 0) {
            return null;
        }

        $keys = array_keys($array);
        return $array[$keys[sizeof($keys) - 1]];
    }

    private function __clone()
    {
        // do nothing
    }
}
