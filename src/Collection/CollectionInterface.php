<?php

namespace Terablaze\Collection;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Terablaze\Support\Interfaces\Arrayable;
use Terablaze\Support\Interfaces\Jsonable;

interface CollectionInterface extends Countable, IteratorAggregate, ArrayAccess, Arrayable, Jsonable, JsonSerializable, EnumerableInterface
{
    public const BASIC_TYPES = [
        'string',
        'int',
        'integer',
        'float',
        'double',
        'bool',
        'boolean',
        'array',
        'object',
        'null',
        'resource',
    ];

    /**
     * Adds an element at the end of the collection.
     *
     * @param mixed $element The element to add.
     *
     * @return true Always TRUE.
     */
    public function add($element);

    /**
     * Clears the collection, removing all elements.
     *
     * @return void
     */
    public function clear();

    /**
     * Checks whether an element is contained in the collection.
     * This is an O(n) operation, where n is the size of the collection.
     *
     * @param mixed $element The element to search for.
     *
     * @return bool TRUE if the collection contains the element, FALSE otherwise.
     */
    public function contains($key, $operator = null, $value = null);

    /**
     * Checks whether the collection is empty (contains no elements).
     *
     * @return bool TRUE if the collection is empty, FALSE otherwise.
     */
    public function isEmpty();

    /**
     * Removes the element at the specified index from the collection.
     *
     * @param string|int $key The key/index of the element to remove.
     *
     * @return mixed The removed element or NULL, if the collection did not contain the element.
     */
    public function remove($key);

    /**
     * Removes the specified element from the collection, if it is found.
     *
     * @param mixed $element The element to remove.
     *
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeElement($element);

    /**
     * Checks whether the collection contains an element with the specified key/index.
     *
     * @param string|int $key The key/index to check for.
     *
     * @return bool TRUE if the collection contains an element with the specified key/index,
     *              FALSE otherwise.
     */
    public function containsKey($key);

    /**
     * Gets the element at the specified key/index.
     *
     * @param string|int $key The key/index of the element to retrieve.
     *
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Gets all keys/indices of the collection.
     *
     * @return int[]|string[] The keys/indices of the collection, in the order of the corresponding
     *               elements in the collection.
     */
    public function getKeys();

    /**
     * Gets all values of the collection.
     *
     * @return array The values of all elements in the collection, in the order they
     *               appear in the collection.
     */
    public function getValues();

    /**
     * Sets an element in the collection at the specified key/index.
     *
     * @param string|int $key The key/index of the element to set.
     * @param mixed $value The element to set.
     *
     * @return void
     */
    public function set($key, $value);

    /**
     * Gets a native PHP array representation of the collection.
     *
     * @return array
     */
    public function all();

    /**
     * Gets a native PHP array representation of the collection.
     *
     * @return array
     */
    public function toArray();

    /**
     * Get the first item from the collection passing the given truth test.
     *
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null);

    /**
     * Get the last item from the collection.
     *
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public function last(callable $callback = null, $default = null);

    /**
     * Gets the key/index of the element at the current iterator position.
     *
     * @return int|string|null
     */
    public function key();

    /**
     * Gets the element of the collection at the current iterator position.
     *
     * @return mixed
     */
    public function current();

    /**
     * Moves the internal iterator position to the next element and returns this element.
     *
     * @return mixed
     */
    public function next();

    /**
     * Tests for the existence of an element that satisfies the given predicate.
     *
     * @param callable $p The predicate.
     *
     * @return bool TRUE if the predicate is TRUE for at least one element, FALSE otherwise.
     *
     * @psalm-param callable(TKey=, T=):bool $p
     */
    public function exists(callable $p);

    /**
     * Returns all the elements of this collection that satisfy the predicate p.
     * The order of the elements is preserved.
     *
     * @param callable $p The predicate used for filtering.
     *
     * @return static A collection with the results of the filter operation.
     */
    public function filter(?callable $p = null);

    /**
     * Tests whether the given predicate p holds for all elements of this collection.
     *
     * @param callable $p The predicate.
     *
     * @return bool TRUE, if the predicate yields TRUE for all elements, FALSE otherwise.
     *
     * @psalm-param callable(TKey=, T=):bool $p
     */
    public function forAll(callable $p);

    /**
     * Zip the collection together with one or more arrays.
     *
     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     *
     * @param mixed ...$elements
     * @return static
     */
    public function zip($elements);

    /**
     * Pad collection to the specified length with a value.
     *
     * @param int $size
     * @param $value
     * @return static
     */
    public function pad($size, $value);

    /**
     * Applies the given function to each element in the collection and returns
     * a new collection with the elements returned by the function.
     *
     * @return static
     */
    public function map(callable $func);

    /**
     * Run a dictionary map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param callable $callback
     * @return static
     */
    public function mapToDictionary(callable $callback);

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param callable $callback
     * @return static
     */
    public function mapWithKeys(callable $callback);

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @param callable $callback
     * @return static
     */
    public function flatMap(callable $callback);

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static
     */
    public function collapse();

    /**
     * Partitions this collection in two collections according to a predicate.
     * Keys are preserved in the resulting collections.
     *
     *
     * * Partition the collection into two arrays using the given callback or key.
     * @param $key
     * @param $operator
     * @param $value
     * @return $this|ArrayCollection[]
     *
     * @return static[] An array with two elements. The first element contains the collection
     *                      of elements where the predicate returned TRUE, the second element
     *                      contains the collection of elements where the predicate returned FALSE.
     */
    public function partition($key, $operator = null, $value = null);

    /**
     * Gets the index/key of a given element. The comparison of two elements is strict,
     * that means not only the value but also the type must match.
     * For objects this means reference equality.
     *
     * @param mixed $element The element to search for.
     *
     * @return int|string|bool The key/index of the element or FALSE if the element was not found.
     */
    public function indexOf($element);

    /**
     * Extracts a slice of $length elements starting at position $offset from the Collection.
     *
     * If $length is null it returns all elements from $offset to the end of the Collection.
     * Keys have to be preserved by this method. Calling this method will only return the
     * selected slice and NOT change the elements contained in the collection slice is called on.
     *
     * @param int $offset The offset to start from.
     * @param int|null $length The maximum number of elements to return, or null for no limit.
     *
     * @return array
     */
    public function slice($offset, $length = null);

    public function pluck($value, $key = null);

    /**
     * Reduce the collection to a single value.
     *
     * @param callable $callback
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null);

    public static function range($from, $to);

    public function avg($callback = null);

    public function median($key = null);

    public function mode($key = null);

    public function flatten($depth = INF);

    public function prepend($value, $key = null);

    public function push(...$values);

    public function each(callable $callback);

    public function merge($elements);

    public function mergeRecursive($elements);

    public function combine($values);

    public function union($elements);

    public function nth($step, $offset = 0);

    public function only($keys);

    public function pop($count = 1);

    public function concat($source);

    public function pull($key, $default = null);

    public function put($key, $value);

    public function random($number = null);

    public function replace($elements);

    public function replaceRecursive($elements);

    public function reverse();

    public function search($value, $strict = false);

    public function implode($value, $glue = null);

    public function reject($callback = true);

    public function undot();

    public function unique($key = null, $strict = false);

    public function values();

    public function forget($keys);

    public function sort($callback = null);

    public function sortDesc($options = SORT_REGULAR);

    public function sortBy($callback, $options = SORT_REGULAR, $descending = false);

    public function sortByDesc($callback, $options = SORT_REGULAR);

    public function sortKeys($options = SORT_REGULAR, $descending = false);

    public function sortKeysDesc($options = SORT_REGULAR);

    public function splice($offset, $length = null, $replacement = []);

    public function dd(...$args);

    public function dump();

    public static function make($elements = []);

    public static function wrap($value);

    public static function unwrap($value);

    public static function empty();

    public static function times($number, callable $callback = null);

    public function average($callback = null);

    public function some($key, $operator = null, $value = null);

    public function eachSpread(callable $callback);

    public function every($key, $operator = null, $value = null);

    public function firstWhere($key, $operator = null, $value = null);

    public function value($key, $default = null);

    public function isNotEmpty();

    public function mapSpread(callable $callback);

    public function mapToGroups(callable $callback);

    public function mapInto($class);

    public function min($callback = null);

    public function max($callback = null);

    public function forPage($page, $perPage);

    public function sum($callback = null);

    public function whenEmpty(callable $callback, callable $default = null);

    public function whenNotEmpty(callable $callback, callable $default = null);

    public function unlessEmpty(callable $callback, callable $default = null);

    public function unlessNotEmpty(callable $callback, callable $default = null);

    public function where($key, $operator = null, $value = null);

    public function whereNull($key = null);

    public function whereNotNull($key = null);

    public function whereStrict($key, $value);

    public function whereIn($key, $values, $strict = false);

    public function whereInStrict($key, $values);

    public function whereBetween($key, $values);

    public function whereNotBetween($key, $values);

    public function whereNotIn($key, $values, $strict = false);

    public function whereNotInStrict($key, $values);

    public function whereInstanceOf($type);

    public function pipe(callable $callback);

    public function pipeInto($class);

    public function pipeThrough($callbacks);

    public function reduceSpread(callable $callback, ...$initial);

    public function tap(callable $callback);

    public function uniqueStrict($key = null);

    public function collect();

    public function getCachingIterator($flags = \CachingIterator::CALL_TOSTRING);

    public function escapeWhenCastingToString($escape = true);
}
