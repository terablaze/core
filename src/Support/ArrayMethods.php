<?php

namespace TeraBlaze\Support;

use ArrayAccess;
use InvalidArgumentException;
use stdClass;
use TeraBlaze\Collection\ArrayCollection;
use TeraBlaze\Collection\CollectionInterface;

/**
 * Class ArrayMethods
 * @package TeraBlaze
 */
class ArrayMethods
{
    /**
     * Determine whether the given value is array accessible.
     *
     * @param mixed $value
     * @return bool
     */
    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * Collapse an array of arrays into a single array.
     *
     * @param array $array
     * @return array
     */
    public static function collapse($array)
    {
        $results = [];

        foreach ($array as $values) {
            if ($values instanceof ArrayCollection) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return $results;
    }

    /**
     * Removes empty items from the array
     * @param $array
     * @return array
     */
    public static function clean($array)
    {
        return array_filter($array, function ($item) {
            return !empty($item);
        });
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param  iterable  $array
     * @param  string  $prepend
     * @return array
     */
    public static function dot($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param ArrayAccess|array $array
     * @param string|int $key
     * @return bool
     */
    public static function exists($array, $key)
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        return array_key_exists($key, $array);
    }

    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param $array
     * @return mixed|null
     */
    public static function first($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($array)) {
                return value($default);
            }

            foreach ($array as $item) {
                return $item;
            }
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return value($default);
    }

    /**
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param array $array
     * @param array|string $keys
     * @return void
     */
    public static function forget(&$array, $keys)
    {
        $original = &$array;

        $keys = ArrayMethods::wrap($keys);

        if (count($keys) === 0) {
            return;
        }

        foreach ($keys as $key) {
            // if the exact key exists in the top-level, remove it
            if (static::exists($array, $key)) {
                unset($array[$key]);

                continue;
            }

            $parts = explode('.', $key);

            // clean up before each pass
            $array = &$original;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }

            unset($array[array_shift($parts)]);
        }
    }

    /**
     * Flatten a multi-dimensional array or object into a single level.
     *
     * @param iterable $array
     * @param int $depth
     * @return array
     */
    public static function flatten($array, $depth = INF)
    {
        $return = [];

        foreach ($array as $item) {
            $item = $item instanceof CollectionInterface ? $item->all() : $item;

            if (is_array($item) || is_object($item)) {
                $values = $depth === 1
                    ? array_values($item)
                    : static::flatten($item, $depth - 1);

                foreach ($values as $value) {
                    $return[] = $value;
                }
            } else {
                $return[] = $item;
            }
        }

        return $return;
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array
     * @param string|int|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        if (!static::accessible($array)) {
            return value($default);
        }

        if (is_null($key)) {
            return $array;
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (strpos($key, '.') === false) {
            return $array[$key] ?? value($default);
        }

        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return value($default);
            }
        }

        return $array;
    }

    /**
     * Check if an item or items exist in an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array
     * @param string|array $keys
     * @return bool
     */
    public static function has($array, $keys)
    {
        $keys = ArrayMethods::wrap($keys);

        if (!$array || $keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            $subKeyArray = $array;

            if (static::exists($array, $key)) {
                continue;
            }

            foreach (explode('.', $key) as $segment) {
                if (static::accessible($subKeyArray) && static::exists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Determine if any of the keys exist in an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array
     * @param string|array $keys
     * @return bool
     */
    public static function hasAny($array, $keys)
    {
        if (is_null($keys)) {
            return false;
        }

        $keys = ArrayMethods::wrap($keys);

        if (!$array) {
            return false;
        }

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            if (static::has($array, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if an array is associative.
     *
     * An array is "associative" if it doesn't have sequential numerical keys beginning with zero.
     *
     * @param array $array
     * @return bool
     */
    public static function isAssoc(array $array)
    {
        $keys = array_keys($array);

        return array_keys($keys) !== $keys;
    }

    /**
     * Return the last element in an array passing a given truth test.
     *
     * @param array $array
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public static function last($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? value($default) : end($array);
        }

        return static::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param array $array
     * @param array|string $keys
     * @return array
     */
    public static function only($array, $keys)
    {
        return array_intersect_key($array, array_flip(ArrayMethods::wrap($keys)));
    }

    /**
     * Pluck an array of values from an array.
     *
     * @param array $array
     * @param string|array $value
     * @param string|array|null $key
     * @return array
     */
    public static function pluck($array, $value, $key = null)
    {
        $results = [];

        [$value, $key] = static::explodePluckParameters($value, $key);

        foreach ($array as $item) {
            $itemValue = dataGet($item, $value);

            // If the key is "null", we will just append the value to the array and keep
            // looping. Otherwise we will key the array using the value of the key we
            // received from the developer. Then we'll return the final array form.
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = dataGet($item, $key);

                if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                    $itemKey = (string)$itemKey;
                }

                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Push an item onto the beginning of an array.
     *
     * @param array $array
     * @param mixed $value
     * @param mixed $key
     * @return array
     */
    public static function prepend($array, $value, $key = null)
    {
        if (func_num_args() == 2) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }

        return $array;
    }

    /**
     * Get a value from the array, and remove it.
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function pull(&$array, $key, $default = null)
    {
        $value = static::get($array, $key, $default);

        static::forget($array, $key);

        return $value;
    }

    /**
     * Get one or a specified number of random values from an array.
     *
     * @param array $array
     * @param int|null $number
     * @param bool|false $preserveKeys
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public static function random($array, $number = null, $preserveKeys = false)
    {
        $requested = is_null($number) ? 1 : $number;

        $count = count($array);

        if ($requested > $count) {
            throw new InvalidArgumentException(
                "You requested {$requested} items, but there are only {$count} items available."
            );
        }

        if (is_null($number)) {
            return $array[array_rand($array)];
        }

        if ((int)$number === 0) {
            return [];
        }

        $keys = array_rand($array, $number);

        $results = [];

        if ($preserveKeys) {
            foreach (ArrayMethods::wrap($keys) as $key) {
                $results[$key] = $array[$key];
            }
        } else {
            foreach (ArrayMethods::wrap($keys) as $key) {
                $results[] = $array[$key];
            }
        }

        return $results;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param array $array
     * @param string|null $key
     * @param mixed $value
     * @return array
     */
    public static function set(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Shuffle the given array and return the result.
     *
     * @param array $array
     * @param int|null $seed
     * @return array
     */
    public static function shuffle($array, $seed = null)
    {
        if (is_null($seed)) {
            shuffle($array);
        } else {
            mt_srand($seed);
            shuffle($array);
            mt_srand();
        }

        return $array;
    }

    /**
     * Sort the array using the given callback or "dot" notation.
     *
     * @param array $array
     * @param callable|array|string|null $callback
     * @return array
     */
    public static function sort($array, $callback = null)
    {
        return (new ArrayCollection($array))->sortBy($callback)->all();
    }

    /**
     * Recursively sort an array by keys and values.
     *
     * @param array $array
     * @param int $options
     * @param bool $descending
     * @return array
     */
    public static function sortRecursive($array, $options = SORT_REGULAR, $descending = false)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = static::sortRecursive($value, $options, $descending);
            }
        }

        if (static::isAssoc($array)) {
            $descending
                ? krsort($array, $options)
                : ksort($array, $options);
        } else {
            $descending
                ? rsort($array, $options)
                : sort($array, $options);
        }

        return $array;
    }

    /**
     * Converts an array to object
     *
     * @param $array
     * return \ArrayObject
     * @return stdClass
     */
    public static function toObject($array)
    {
//        $result = new class ($array) extends \ArrayObject {};
        $result = new stdClass();

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result->{$key} = self::toObject($value);
            } else {
                $result->{$key} = $value;
            }
        }

        return $result;
    }

    /**
     * Convert the array into a query string.
     *
     * @param $array
     * @return string
     */
    public static function toQueryString($array)
    {
        return http_build_query(
            self::clean(
                $array
            ),
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    /**
     * Removes space and newline characters from the elements of an array
     * @param $array
     * @return array|string[]
     */
    public static function trim($array)
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                return static::trim($item);
            }
            return trim($item, " \t\n\r\0\x0B\"");
        }, $array);
    }

    public static function utf8ize($data)
    {
        if (is_string($data)) {
            return utf8_encode($data);
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = static::utf8ize($value);
            }
        }
        return $data;
    }

    /**
     * Filter the array using the given callback.
     *
     * @param array $array
     * @param callable $callback
     * @return array
     */
    public static function where($array, callable $callback)
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * If the given value is not an array and not null, wrap it in one.
     *
     * @param mixed $value
     * @return array
     */
    public static function wrap($value)
    {
        if (is_null($value)) {
            return [];
        }

        return !is_array($value) ? [$value] : $value;
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param string|array $value
     * @param string|array|null $key
     * @return array
     */
    protected static function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    private function __construct()
    {
        // do nothing
    }

    private function __clone()
    {
        // do nothing
    }
}
