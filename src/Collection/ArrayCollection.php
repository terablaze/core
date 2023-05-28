<?php

namespace Terablaze\Collection;

use ArrayIterator;
use CachingIterator;
use JsonSerializable;
use Symfony\Component\VarDumper\VarDumper;
use Terablaze\Collection\Exceptions\InvalidTypeException;
use Terablaze\Collection\Exceptions\TypeException;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\Helpers;
use Terablaze\Support\Interfaces\Arrayable;
use Terablaze\Support\Interfaces\Jsonable;
use Terablaze\Support\Traits\Conditionable;
use Traversable;
use UnitEnum;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_search;
use function array_slice;
use function array_values;
use function count;
use function current;
use function dump;
use function in_array;
use function key;
use function next;
use function reset;
use function spl_object_hash;
use const ARRAY_FILTER_USE_BOTH;

/**
 * An ArrayCollection is a Collection implementation that wraps a regular PHP array.
 *
 * Warning: Using (un-)serialize() on a collection is not a supported use-case
 * and may break when we change the internals in the future. If you need to
 * serialize a collection use {@link toArray()} and reconstruct the collection
 * manually.
 */
class ArrayCollection implements CollectionInterface
{
    use Conditionable, EnumeratesValues;

    /**
     * Indicates that the object's string representation should be escaped when __toString is invoked.
     *
     * @var bool
     */
    protected $escapeWhenCastingToString = false;

    /**
     * An array containing the entries of this collection.
     *
     * @var array
     */
    protected $elements;

    /**
     *
     * @var string|null $type
     */
    protected $type;

    /**
     * Initializes a new ArrayCollection.
     *
     * @param array|Traversable $elements
     * @param string|null $type
     */
    public function __construct($elements = [], ?string $type = null)
    {
        $this->elements = $elements;
        if ($type != null) {
            if (!in_array($type, self::BASIC_TYPES) && !class_exists($type)) {
                $message = "The data type you required is invalid. " .
                    "Make sure it is a valid class or any of the built-in basic " .
                    "php data types: (" . join(', ', self::BASIC_TYPES) . ")";
                throw new InvalidTypeException($message);
            }
            $this->type = $type;
            $this->verifyType();
        }
    }

    /**
     * Create a collection with the given range.
     *
     * @param int $from
     * @param int $to
     * @return static<int, int>
     */
    public static function range($from, $to)
    {
        return new static(range($from, $to));
    }

    /**
     * {@inheritDoc}
     */
    public function all()
    {
        return $this->elements;
    }

    /**
     * Get a lazy collection for the items in this collection.
     *
     * @return LazyCollection
     */
    public function lazy()
    {
        return new LazyCollection($this->elements);
    }

    /**
     * Get the average value of a given key.
     * @param $callback
     * @return float|int|null
     */
    public function avg($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        $elements = $this->map(function ($value) use ($callback) {
            return $callback($value);
        })->filter(function ($value) {
            return !is_null($value);
        });

        if ($count = $elements->count()) {
            return $elements->sum() / $count;
        }

        return null;
    }

    /**
     * Get the median of a given key.
     *
     * @param string|array<array-key, string>|null $key
     * @return float|int|null
     */
    public function median($key = null)
    {
        $values = (isset($key) ? $this->pluck($key) : $this)
            ->filter(fn($item) => !is_null($item))
            ->sort()->values();

        $count = $values->count();

        if ($count === 0) {
            return null;
        }

        $middle = (int)($count / 2);

        if ($count % 2) {
            return $values->get($middle);
        }

        return (new static([
            $values->get($middle - 1), $values->get($middle),
        ]))->average();
    }

    /**
     * Get the mode of a given key.
     *
     * @param string|array<array-key, string>|null $key
     * @return array<int, float|int>|null
     */
    public function mode($key = null)
    {
        if ($this->count() === 0) {
            return null;
        }

        $collection = isset($key) ? $this->pluck($key) : $this;

        $counts = new static;

        $collection->each(fn($value) => $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1);

        $sorted = $counts->sort();

        $highestValue = $sorted->last();

        return $sorted->filter(fn($value) => $value == $highestValue)
            ->sort()->keys()->all();
    }

    /**
     * {@inheritDoc}
     */
    public function first(callable $callback = null, $default = null)
    {
        return ArrayMethods::first($this->elements, $callback, $default);
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param int $depth
     * @return static<int, mixed>
     */
    public function flatten($depth = INF)
    {
        return new static(ArrayMethods::flatten($this->elements, $depth));
    }

    /**
     * Flip the items in the collection.
     *
     * @return static
     */
    public function flip()
    {
        return new static(array_flip($this->elements));
    }

    /**
     * {@inheritDoc}
     */
    public function last(callable $callback = null, $default = null)
    {
        return ArrayMethods::last($this->elements, $callback, $default);
    }

    /**
     * Get the keys of the collection items.
     * @return $this
     * @throws InvalidTypeException
     */
    public function keys()
    {
        return new static(array_keys($this->elements));
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param  callable|array|string  $keyBy
     * @return static
     */
    public function keyBy($keyBy)
    {
        $keyBy = $this->valueRetriever($keyBy);

        $results = [];

        foreach ($this->elements as $key => $item) {
            $resolvedKey = $keyBy($item, $key);

            if (is_object($resolvedKey)) {
                $resolvedKey = (string) $resolvedKey;
            }

            $results[$resolvedKey] = $item;
        }

        return new static($results);
    }

    /**
     * Creates a new instance from the specified elements.
     *
     * This method is provided for derived classes to specify how a new
     * instance should be created when constructor semantics have changed.
     *
     * @param array $elements Elements.
     *
     * @return static
     * @throws TypeException
     */
    protected function createFrom(array $elements)
    {
        return new static($elements);
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return key($this->elements);
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        return next($this->elements);
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        return current($this->elements);
    }

    /**
     * {@inheritDoc}
     */
    public function remove($key)
    {
        if (!isset($this->elements[$key]) && !array_key_exists($key, $this->elements)) {
            return null;
        }

        $removed = $this->elements[$key];
        unset($this->elements[$key]);

        return $removed;
    }

    /**
     * {@inheritDoc}
     */
    public function removeElement($element)
    {
        $key = array_search($element, $this->elements, true);

        if ($key === false) {
            return false;
        }

        unset($this->elements[$key]);

        return true;
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    public function offsetExists($offset): bool
    {
        return $this->containsKey($offset);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value): void
    {
        if (!isset($offset)) {
            $this->add($value);

            return;
        }

        $this->set($offset, $value);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function containsKey($key)
    {
        return isset($this->elements[$key]) || array_key_exists($key, $this->elements);
    }

    /**
     * {@inheritDoc}
     */
    public function contains($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1) {
            if ($this->useAsCallable($key)) {
                $placeholder = new \stdClass();

                return $this->first($key, $placeholder) !== $placeholder;
            }

            return in_array($key, $this->elements);
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * {@inheritDoc}
     */
    public function exists(callable $p)
    {
        foreach ($this->elements as $key => $element) {
            if ($p($key, $element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function indexOf($element)
    {
        return array_search($element, $this->elements, true);
    }

    /**
     * {@inheritDoc}
     */
    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->elements)) {
            return $this->elements[$key];
        }

        return value($default);
    }

    /**
     * {@inheritDoc}
     */
    public function getKeys()
    {
        return array_keys($this->elements);
    }

    /**
     * {@inheritDoc}
     */
    public function getValues()
    {
        return array_values($this->elements);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->elements);
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value)
    {
        $this->elements[$key] = $value;
    }

    /**
     * {@inheritDoc}
     *
     * This breaks assumptions about the template type, but it would
     * be a backwards-incompatible change to remove this method
     */
    public function add($element)
    {
        $this->elements[] = $element;

        return true;
    }

    /**
     * Push an item onto the beginning of the collection.
     *
     * @param mixed $value
     * @param mixed $key
     * @return $this
     */
    public function prepend($value, $key = null)
    {
        $this->elements = ArrayMethods::prepend($this->elements, ...func_get_args());

        return $this;
    }

    /**
     * Push one or more items onto the end of the collection.
     *
     * @param mixed $values [optional]
     * @return $this
     */
    public function push(...$values)
    {
        foreach ($values as $value) {
            $this->elements[] = $value;
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty()
    {
        return empty($this->elements);
    }

    /**
     * Required by interface IteratorAggregate.
     *
     * {@inheritDoc}
     *
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->elements);
    }

    /**
     * Zip the collection together with one or more arrays.
     *
     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     *
     * @param mixed ...$elements
     * @return static
     */
    public function zip($elements)
    {
        $arrayableItems = array_map(function ($elements) {
            return $this->getArrayableItems($elements);
        }, func_get_args());

        $params = array_merge([function () {
            return new static(func_get_args());
        }, $this->elements], $arrayableItems);

        return new static(array_map(...$params));
    }

    /**
     * Pad collection to the specified length with a value.
     *
     * @param int $size
     * @param  $value
     * @return static
     */
    public function pad($size, $value)
    {
        return new static(array_pad($this->elements, $size, $value));
    }

    /**
     * {@inheritDoc}
     *
     * @return static
     */
    public function map(callable $func)
    {
        $keys = array_keys($this->elements);

        $elements = array_map($func, $this->elements, $keys);

        return $this->createFrom(array_combine($keys, $elements));
    }

    /**
     * Execute a callback over each item.
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Run a dictionary map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param callable $callback
     * @return static
     */
    public function mapToDictionary(callable $callback)
    {
        $dictionary = [];

        foreach ($this->elements as $key => $item) {
            $pair = $callback($item, $key);

            $key = key($pair);

            $value = reset($pair);

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }

            $dictionary[$key][] = $value;
        }

        return new static($dictionary);
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param callable $callback
     * @return static
     */
    public function mapWithKeys(callable $callback)
    {
        $result = [];

        foreach ($this->elements as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /**
     * Merge the collection with the given items.
     *
     * @param mixed $elements
     * @return static
     */
    public function merge($elements)
    {
        return new static(array_merge($this->elements, $this->getArrayableItems($elements)));
    }

    /**
     * Recursively merge the collection with the given items.
     *
     * @param mixed $elements
     * @return static
     */
    public function mergeRecursive($elements)
    {
        return new static(array_merge_recursive($this->elements, $this->getArrayableItems($elements)));
    }

    /**
     * Create a collection by using this collection for keys and another for its values.
     *
     * @param mixed $values
     * @return static
     */
    public function combine($values)
    {
        return new static(array_combine($this->all(), $this->getArrayableItems($values)));
    }

    /**
     * Union the collection with the given items.
     *
     * @param mixed $elements
     * @return static
     */
    public function union($elements)
    {
        return new static($this->elements + $this->getArrayableItems($elements));
    }

    /**
     * Create a new collection consisting of every n-th element.
     *
     * @param int $step
     * @param int $offset
     * @return static
     */
    public function nth($step, $offset = 0)
    {
        $new = [];

        $position = 0;

        foreach ($this->slice($offset)->elements as $item) {
            if ($position % $step === 0) {
                $new[] = $item;
            }

            $position++;
        }

        return new static($new);
    }

    /**
     * Get the items with the specified keys.
     *
     * @param mixed $keys
     * @return static
     */
    public function only($keys)
    {
        if (is_null($keys)) {
            return new static($this->elements);
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(ArrayMethods::only($this->elements, $keys));
    }

    /**
     * Get and remove the last N items from the collection.
     *
     * @param int $count
     * @return mixed
     */
    public function pop($count = 1)
    {
        if ($count === 1) {
            return array_pop($this->elements);
        }

        if ($this->isEmpty()) {
            return new static;
        }

        $results = [];

        $collectionCount = $this->count();

        foreach (range(1, min($count, $collectionCount)) as $item) {
            array_push($results, array_pop($this->elements));
        }

        return new static($results);
    }

    /**
     * Push all of the given items onto the collection.
     *
     * @param iterable $source
     * @return static
     */
    public function concat($source)
    {
        $result = new static($this->all());

        foreach ($source as $item) {
            $result->push($item);
        }

        return $result;
    }

    /**
     * Get and remove an item from the collection.
     *
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return ArrayMethods::pull($this->elements, $key, $default);
    }

    /**
     * Put an item in the collection by key.
     *
     * @param mixed $key
     * @param mixed $value
     * @return $this
     */
    public function put($key, $value)
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * Get one or a specified number of items randomly from the collection.
     *
     * @param int|null $number
     * @return static|mixed
     *
     * @throws \InvalidArgumentException
     */
    public function random($number = null)
    {
        if (is_null($number)) {
            return ArrayMethods::random($this->elements);
        }

        return new static(ArrayMethods::random($this->elements, $number));
    }

    /**
     * Replace the collection items with the given items.
     *
     * @param mixed $elements
     * @return static
     */
    public function replace($elements)
    {
        return new static(array_replace($this->elements, $this->getArrayableItems($elements)));
    }

    /**
     * Recursively replace the collection items with the given items.
     *
     * @param mixed $elements
     * @return static
     */
    public function replaceRecursive($elements)
    {
        return new static(array_replace_recursive($this->elements, $this->getArrayableItems($elements)));
    }

    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse()
    {
        return new static(array_reverse($this->elements, true));
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param mixed $value
     * @param bool $strict
     * @return mixed
     */
    public function search($value, $strict = false)
    {
        if (!$this->useAsCallable($value)) {
            return array_search($value, $this->elements, $strict);
        }

        foreach ($this->elements as $key => $item) {
            if ($value($item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Get and remove the first N items from the collection.
     *
     * @param int $count
     * @return static|null
     */
    public function shift($count = 1)
    {
        if ($count === 1) {
            return array_shift($this->elements);
        }

        if ($this->isEmpty()) {
            return new static;
        }

        $results = [];

        $collectionCount = $this->count();

        foreach (range(1, min($count, $collectionCount)) as $item) {
            array_push($results, array_shift($this->elements));
        }

        return new static($results);
    }

    /**
     * Shuffle the items in the collection.
     *
     * @param int|null $seed
     * @return static
     */
    public function shuffle($seed = null)
    {
        return new static(ArrayMethods::shuffle($this->elements, $seed));
    }

    /**
     * Create chunks representing a "sliding window" view of the items in the collection.
     *
     * @param int $size
     * @param int $step
     * @return static<int, static>
     */
    public function sliding($size = 2, $step = 1)
    {
        $chunks = floor(($this->count() - $size) / $step) + 1;

        return static::times($chunks, function ($number) use ($size, $step) {
            return $this->slice(($number - 1) * $step, $size);
        });
    }

    /**
     * Skip the first {$count} items.
     *
     * @param int $count
     * @return static
     */
    public function skip($count)
    {
        return $this->slice($count);
    }

    /**
     * Slice the underlying collection array.
     *
     * @param int $offset
     * @param int|null $length
     * @return static
     */
    public function slice($offset, $length = null)
    {
        return new static(array_slice($this->elements, $offset, $length, true));
    }

    /**
     * Split a collection into a certain number of groups.
     *
     * @param int $numberOfGroups
     * @return static<int, static>
     */
    public function split($numberOfGroups)
    {
        if ($this->isEmpty()) {
            return new static;
        }

        $groups = new static;

        $groupSize = floor($this->count() / $numberOfGroups);

        $remain = $this->count() % $numberOfGroups;

        $start = 0;

        for ($i = 0; $i < $numberOfGroups; $i++) {
            $size = $groupSize;

            if ($i < $remain) {
                $size++;
            }

            if ($size) {
                $groups->push(new static(array_slice($this->elements, $start, $size)));

                $start += $size;
            }
        }

        return $groups;
    }

    /**
     * Split a collection into a certain number of groups, and fill the first groups completely.
     *
     * @param int $numberOfGroups
     * @return static<int, static>
     */
    public function splitIn($numberOfGroups)
    {
        return $this->chunk(ceil($this->count() / $numberOfGroups));
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @param callable $callback
     * @return static
     */
    public function flatMap(callable $callback)
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static
     */
    public function collapse()
    {
        return new static(ArrayMethods::collapse($this->elements));
    }

    /**
     * {@inheritDoc}
     *
     * @return static
     */
    public function filter(?callable $p = null)
    {
        return $this->createFrom(array_filter($this->elements, $p, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritDoc}
     */
    public function forAll(callable $p)
    {
        foreach ($this->elements as $key => $element) {
            if (!$p($key, $element)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Concatenate values of a given key as a string.
     *
     * @param string $value
     * @param string|null $glue
     * @return string
     */
    public function implode($value, $glue = null)
    {
        $first = $this->first();

        if (is_array($first) || (is_object($first) && !$first instanceof \Stringable)) {
            return implode($glue ?? '', $this->pluck($value)->all());
        }

        return implode($value ?? '', $this->elements);
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        $this->elements = [];
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param callable|mixed $callback
     * @return static
     */
    public function reject($callback = true)
    {
        $useAsCallable = $this->useAsCallable($callback);

        return $this->filter(function ($value, $key) use ($callback, $useAsCallable) {
            return $useAsCallable
                ? !$callback($value, $key)
                : $value != $callback;
        });
    }

    /**
     * Convert a flatten "dot" notation array into an expanded array.
     *
     * @return static
     */
    public function undot()
    {
        return new static(ArrayMethods::undot($this->all()));
    }

    /**
     * Return only unique items from the collection array.
     *
     * @param string|callable|null $key
     * @param bool $strict
     * @return static
     */
    public function unique($key = null, $strict = false)
    {
        if (is_null($key) && $strict === false) {
            return new static(array_unique($this->elements, SORT_REGULAR));
        }

        $callback = $this->valueRetriever($key);

        $exists = [];

        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * Reset the keys on the underlying array.
     * @return $this
     * @throws InvalidTypeException
     */
    public function values()
    {
        return new static(array_values($this->elements));
    }

    /**
     * Get the values of a given key.
     *
     * @param string|array $value
     * @param string|null $key
     * @return static
     */
    public function pluck($value, $key = null)
    {
        return new static(ArrayMethods::pluck($this->elements, $value, $key));
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param string|array $keys
     * @return $this
     */
    public function forget($keys)
    {
        foreach ((array)$keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param callable $callback
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        $result = $initial;

        foreach ($this as $key => $value) {
            $result = $callback($result, $value, $key);
        }

        return $result;
    }

    /**
     * Sort through each item with a callback.
     *
     * @param callable|int|null $callback
     * @return static
     */
    public function sort($callback = null)
    {
        $elements = $this->elements;

        $callback && is_callable($callback)
            ? uasort($elements, $callback)
            : asort($elements, $callback ?? SORT_REGULAR);

        return new static($elements);
    }

    /**
     * Sort items in descending order.
     *
     * @param int $options
     * @return static
     */
    public function sortDesc($options = SORT_REGULAR)
    {
        $elements = $this->elements;

        arsort($elements, $options);

        return new static($elements);
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param callable|array|string $callback
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        if (is_array($callback) && !is_callable($callback)) {
            return $this->sortByMany($callback);
        }

        $results = [];

        $callback = $this->valueRetriever($callback);

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->elements as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options)
            : asort($results, $options);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->elements[$key];
        }

        return new static($results);
    }

    /**
     * Sort the collection using multiple comparisons.
     *
     * @param array $comparisons
     * @return static
     */
    protected function sortByMany(array $comparisons = [])
    {
        $elements = $this->elements;

        usort($elements, function ($a, $b) use ($comparisons) {
            foreach ($comparisons as $comparison) {
                $comparison = ArrayMethods::wrap($comparison);

                $prop = $comparison[0];

                $ascending = ArrayMethods::get($comparison, 1, true) === true ||
                    ArrayMethods::get($comparison, 1, true) === 'asc';

                $result = 0;

                if (is_callable($prop)) {
                    $result = $prop($a, $b);
                } else {
                    $values = [dataGet($a, $prop), dataGet($b, $prop)];

                    if (!$ascending) {
                        $values = array_reverse($values);
                    }

                    $result = $values[0] <=> $values[1];
                }

                if ($result === 0) {
                    continue;
                }

                return $result;
            }
        });

        return new static($elements);
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param callable|string $callback
     * @param int $options
     * @return static
     */
    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Sort the collection keys.
     *
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public function sortKeys($options = SORT_REGULAR, $descending = false)
    {
        $elements = $this->elements;

        $descending ? krsort($elements, $options) : ksort($elements, $options);

        return new static($elements);
    }

    /**
     * Sort the collection keys in descending order.
     *
     * @param int $options
     * @return static
     */
    public function sortKeysDesc($options = SORT_REGULAR)
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Splice a portion of the underlying collection array.
     *
     * @param int $offset
     * @param int|null $length
     * @param mixed $replacement
     * @return static
     */
    public function splice($offset, $length = null, $replacement = [])
    {
        if (func_num_args() === 1) {
            return new static(array_splice($this->elements, $offset));
        }

        return new static(array_splice($this->elements, $offset, $length, $replacement));
    }

    /**
     * Dump the items and end the script.
     *
     * @param mixed ...$args
     * @return never
     */
    public function dd(...$args)
    {
        $this->dump(...$args);

        exit(1);
    }

    /**
     * Dump the items.
     *
     * @return $this
     */
    public function dump()
    {
        (new ArrayCollection(func_get_args()))
            ->push($this->all())
            ->each(function ($item) {
                VarDumper::dump($item);
            });

        return $this;
    }

    /**
     * Create a new collection instance if the value isn't one already.
     *
     *
     * @param Arrayable|iterable|null $elements
     * @return static
     */
    public static function make($elements = [])
    {
        return new static($elements);
    }

    /**
     * Wrap the given value in a collection if applicable.
     *
     * @template TWrapKey of array-key
     * @template TWrapValue
     *
     * @param iterable $value
     * @return static
     */
    public static function wrap($value)
    {
        return $value instanceof CollectionInterface
            ? new static($value)
            : new static(ArrayMethods::wrap($value));
    }

    /**
     * Get the underlying items from the given collection if applicable.
     *
     * @param array|static $value
     * @return array
     */
    public static function unwrap($value)
    {
        return $value instanceof CollectionInterface ? $value->all() : $value;
    }

    /**
     * Create a new instance with no items.
     *
     * @return static
     */
    public static function empty()
    {
        return new static([]);
    }

    /**
     * Create a new collection by invoking the callback a given amount of times.
     *
     * @template TTimesValue
     *
     * @param int $number
     * @param (callable(int): TTimesValue)|null $callback
     * @return static<int, TTimesValue>
     */
    public static function times($number, callable $callback = null)
    {
        if ($number < 1) {
            return new static;
        }

        return static::range(1, $number)
            ->unless($callback == null)
            ->map($callback);
    }

    /**
     * Alias for the "avg" method.
     *
     * @param (callable: float|int)|string|null $callback
     * @return float|int|null
     */
    public function average($callback = null)
    {
        return $this->avg($callback);
    }

    /**
     * Alias for the "contains" method.
     *
     * @param  $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function some($key, $operator = null, $value = null)
    {
        return $this->contains(...func_get_args());
    }

    /**
     * Execute a callback over each nested chunk of items.
     *
     * @param callable(...mixed): mixed  $callback
     * @return static
     */
    public function eachSpread(callable $callback)
    {
        return $this->each(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * Determine if all items pass the given truth test.
     *
     * @param $key
     * @param $operator
     * @param $value
     * @return bool
     */
    public function every($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1) {
            $callback = $this->valueRetriever($key);

            foreach ($this as $k => $v) {
                if (!$callback($v, $k)) {
                    return false;
                }
            }

            return true;
        }

        return $this->every($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get the first item by the given key value pair.
     *
     * @param $key
     * @param $operator
     * @param $value
     * @return mixed|null
     */
    public function firstWhere($key, $operator = null, $value = null)
    {
        return $this->first($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get a single key's value from the first matching item in the collection.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function value($key, $default = null)
    {
        if ($value = $this->firstWhere($key)) {
            return dataGet($value, $key, $default);
        }

        return value($default);
    }

    /**
     * Determine if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty()
    {
        return !$this->isEmpty();
    }

    /**
     * Run a map over each nested chunk of items
     *
     * @param callable $callback
     * @return $this
     */
    public function mapSpread(callable $callback)
    {
        return $this->map(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * Run a grouping map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param callable $callback
     * @return ArrayCollection
     */
    public function mapToGroups(callable $callback)
    {
        $groups = $this->mapToDictionary($callback);

        return $groups->map([$this, 'make']);
    }

    /**
     * Map the values into a new class.
     *
     * @param $class
     * @return $this
     */
    public function mapInto($class)
    {
        return $this->map(fn($value, $key) => new $class($value, $key));
    }

    /**
     * Get the min value of a given key.
     *
     * @param $callback
     * @return mixed|null
     */
    public function min($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->map(fn($value) => $callback($value))
            ->filter(fn($value) => !is_null($value))
            ->reduce(fn($result, $value) => is_null($result) || $value < $result ? $value : $result);
    }

    /**
     * Get the max value of a given key.
     *
     * @param $callback
     * @return \Closure|mixed|null
     */
    public function max($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->filter(fn($value) => !is_null($value))->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);

            return is_null($result) || $value > $result ? $value : $result;
        });
    }

    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     *
     * @param int $page
     * @param int $perPage
     * @return static
     */
    public function forPage($page, $perPage)
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->slice($offset, $perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function partition($key, $operator = null, $value = null)
    {
        $passed = [];
        $failed = [];

        $callback = func_num_args() === 1
            ? $this->valueRetriever($key)
            : $this->operatorForWhere(...func_get_args());

        foreach ($this as $key => $item) {
            if ($callback($item, $key)) {
                $passed[$key] = $item;
            } else {
                $failed[$key] = $item;
            }
        }

        return new static([new static($passed), new static($failed)]);
    }

    /**
     * Get the sum of the given values.
     *
     * @param $callback
     * @return mixed|null
     */
    public function sum($callback = null)
    {
        $callback = is_null($callback)
            ? $this->identity()
            : $this->valueRetriever($callback);

        return $this->reduce(fn($result, $item) => $result + $callback($item), 0);
    }

    /**
     * Apply the callback if the collection is empty.
     *
     * @template TWhenEmptyReturnType
     *
     * @param (callable($this): TWhenEmptyReturnType) $callback
     * @param (callable($this): TWhenEmptyReturnType)|null $default
     * @return $this|TWhenEmptyReturnType
     */
    public function whenEmpty(callable $callback, callable $default = null)
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    /**
     * Apply the callback if the collection is not empty.
     *
     * @template TWhenNotEmptyReturnType
     *
     * @param callable($this): TWhenNotEmptyReturnType $callback
     * @param (callable($this): TWhenNotEmptyReturnType)|null $default
     * @return $this|TWhenNotEmptyReturnType
     */
    public function whenNotEmpty(callable $callback, callable $default = null)
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Apply the callback unless the collection is empty.
     *
     * @template TUnlessEmptyReturnType
     *
     * @param callable($this): TUnlessEmptyReturnType $callback
     * @param (callable($this): TUnlessEmptyReturnType)|null $default
     * @return $this|TUnlessEmptyReturnType
     */
    public function unlessEmpty(callable $callback, callable $default = null)
    {
        return $this->whenNotEmpty($callback, $default);
    }

    /**
     * Apply the callback unless the collection is not empty.
     *
     * @template TUnlessNotEmptyReturnType
     *
     * @param callable($this): TUnlessNotEmptyReturnType $callback
     * @param (callable($this): TUnlessNotEmptyReturnType)|null $default
     * @return $this|TUnlessNotEmptyReturnType
     */
    public function unlessNotEmpty(callable $callback, callable $default = null)
    {
        return $this->whenEmpty($callback, $default);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param callable|string $key
     * @param mixed $operator
     * @param mixed $value
     * @return static
     */
    public function where($key, $operator = null, $value = null)
    {
        return $this->filter($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Filter items where the value for the given key is null.
     *
     * @param string|null $key
     * @return static
     */
    public function whereNull($key = null)
    {
        return $this->whereStrict($key, null);
    }

    /**
     * Filter items where the value for the given key is not null.
     *
     * @param string|null $key
     * @return static
     */
    public function whereNotNull($key = null)
    {
        return $this->where($key, '!==', null);
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function whereStrict($key, $value)
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @param bool $strict
     * @return static
     */
    public function whereIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->filter(fn($item) => in_array(dataGet($item, $key), $values, $strict));
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereInStrict($key, $values)
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Filter items such that the value of the given key is between the given values.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereBetween($key, $values)
    {
        return $this->where($key, '>=', reset($values))->where($key, '<=', end($values));
    }

    /**
     * Filter items such that the value of the given key is not between the given values.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereNotBetween($key, $values)
    {
        return $this->filter(
            fn($item) => dataGet($item, $key) < reset($values) || dataGet($item, $key) > end($values)
        );
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @param bool $strict
     * @return static
     */
    public function whereNotIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->reject(fn($item) => in_array(dataGet($item, $key), $values, $strict));
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereNotInStrict($key, $values)
    {
        return $this->whereNotIn($key, $values, true);
    }

    /**
     * Filter the items, removing any items that don't match the given type(s).
     *
     * @template TWhereInstanceOf
     *
     * @param class-string|array<array-key, class-string> $type
     * @return static
     */
    public function whereInstanceOf($type)
    {
        return $this->filter(function ($value) use ($type) {
            if (is_array($type)) {
                foreach ($type as $classType) {
                    if ($value instanceof $classType) {
                        return true;
                    }
                }

                return false;
            }

            return $value instanceof $type;
        });
    }

    /**
     * Pass the collection to the given callback and return the result.
     *
     * @template TPipeReturnType
     *
     * @param callable($this): TPipeReturnType $callback
     * @return TPipeReturnType
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Pass the collection into a new class.
     *
     * @param class-string $class
     * @return mixed
     */
    public function pipeInto($class)
    {
        return new $class($this);
    }

    /**
     * Pass the collection through a series of callable pipes and return the result.
     *
     * @param array<callable> $callbacks
     * @return mixed
     */
    public function pipeThrough($callbacks)
    {
        return ArrayCollection::make($callbacks)->reduce(
            fn($carry, $callback) => $callback($carry),
            $this,
        );
    }

    /**
     * Reduce the collection to multiple aggregate values.
     *
     * @param callable $callback
     * @param mixed ...$initial
     * @return array
     *
     * @throws \UnexpectedValueException
     */
    public function reduceSpread(callable $callback, ...$initial)
    {
        $result = $initial;

        foreach ($this as $key => $value) {
            $result = call_user_func_array($callback, array_merge($result, [$value, $key]));

            if (!is_array($result)) {
                throw new \UnexpectedValueException(sprintf(
                    "%s::reduceSpread expects reducer to return an array, but got a '%s' instead.",
                    classBasename(static::class), gettype($result)
                ));
            }
        }

        return $result;
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param callable($this): mixed $callback
     * @return $this
     */
    public function tap(callable $callback)
    {
        $callback($this);

        return $this;
    }

    /**
     * Return only unique items from the collection array using strict comparison.
     *
     * @param callable|string|null $key
     * @return static
     */
    public function uniqueStrict($key = null)
    {
        return $this->unique($key, true);
    }

    /**
     * Collect the values into a collection.
     *
     * @return ArrayCollection
     */
    public function collect()
    {
        return new ArrayCollection($this->all());
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->map(fn($value) => $value instanceof Arrayable ? $value->toArray() : $value)->all();
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {
                return json_decode($value->toJson(), true);
            } elseif ($value instanceof Arrayable) {
                return $value->toArray();
            }

            return $value;
        }, $this->all());
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Get a CachingIterator instance.
     *
     * @param int $flags
     * @return CachingIterator
     */
    public function getCachingIterator($flags = CachingIterator::CALL_TOSTRING)
    {
        return new CachingIterator($this->getIterator(), $flags);
    }

    /**
     * Indicate that the model's string representation should be escaped when __toString is invoked.
     *
     * @param bool $escape
     * @return $this
     */
    public function escapeWhenCastingToString($escape = true)
    {
        $this->escapeWhenCastingToString = $escape;

        return $this;
    }

    /**
     * Get an operator checker callback.
     *
     * @param callable|string $key
     * @param string|null $operator
     * @param mixed $value
     * @return \Closure
     */
    protected function operatorForWhere($key, $operator = null, $value = null)
    {
        if ($this->useAsCallable($key)) {
            return $key;
        }

        if (func_num_args() === 1) {
            $value = true;

            $operator = '=';
        }

        if (func_num_args() === 2) {
            $value = $operator;

            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = dataGet($item, $key);

            $strings = array_filter([$retrieved, $value], function ($value) {
                return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
            });

            if (count($strings) < 2 && count(array_filter([$retrieved, $value], 'is_object')) == 1) {
                return in_array($operator, ['!=', '<>', '!==']);
            }

            switch ($operator) {
                default:
                case '=':
                case '==':
                    return $retrieved == $value;
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
                case '<=>':
                    return $retrieved <=> $value;
            }
        };
    }

    /**
     * Make a function to check an item's equality.
     *
     * @param mixed $value
     * @return \Closure(mixed): bool
     */
    protected function equality($value)
    {
        return fn($item) => $item === $value;
    }

    /**
     * Make a function using another function, by negating its result.
     *
     * @param \Closure $callback
     * @return \Closure
     */
    protected function negate(\Closure $callback)
    {
        return fn(...$params) => !$callback(...$params);
    }

    /**
     * Make a function that returns what's passed to it.
     *
     * @return \Closure
     */
    protected function identity()
    {
        return fn($value) => $value;
    }

    protected function verifyType()
    {
        foreach ($this->elements as $key => $element) {
            $isType = false;
            $elementType = gettype($element);
            if ($elementType == 'object') {
                $elementType = get_class($element);
                if ($element instanceof $this->type) {
                    continue;
                }
            }
            if ($elementType != 'object') {
                if (class_exists($this->type)) {
                    $isType = false;
                } else {
                    $typeCheckFunction = "is_" . $this->type;
                    $isType = call_user_func($typeCheckFunction, $element);
                }
            }
            if ($isType == false) {
                $message = "The element at key position {$key} " .
                    "is of an unnacptable type ({$elementType}). Only {$this->type} is allowed";
                throw new TypeException($message);
            }
        }
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param mixed $value
     * @return bool
     */
    protected function useAsCallable($value)
    {
        return !is_string($value) && is_callable($value);
    }

    /**
     * Get a value retrieving callback.
     *
     * @param callable|string|null $value
     * @return callable
     */
    protected function valueRetriever($value)
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return fn ($item) => Helpers::dataGet($item, $value);
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->escapeWhenCastingToString
            ? e($this->toJson())
            : $this->toJson();
    }

    /**
     * Determine if an item exists, using strict comparison.
     *
     * @param  (callable: bool)|mixed $key
     * @param  mixed|null  $value
     * @return bool
     */
    public function containsStrict($key, $value = null)
    {
        if (func_num_args() === 2) {
            return $this->contains(fn ($item) => Helpers::dataGet($item, $key) === $value);
        }

        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }

        return in_array($key, $this->elements, true);
    }

    /**
     * Determine if an item is not contained in the collection.
     *
     * @param  mixed  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function doesntContain($key, $operator = null, $value = null)
    {
        return ! $this->contains(...func_get_args());
    }

    /**
     * Cross join with the given lists, returning all possible permutations.
     *
     * @template TCrossJoinKey
     * @template TCrossJoinValue
     *
     * @param  Arrayable|iterable  ...$lists
     * @return static<int, array>
     */
    public function crossJoin(...$lists)
    {
        return new static(ArrayMethods::crossJoin(
            $this->elements, ...array_map([$this, 'getArrayableItems'], $lists)
        ));
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @param  Arrayable|iterable $elements
     * @return static
     */
    public function diff($elements)
    {
        return new static(array_diff($this->elements, $this->getArrayableItems($elements)));
    }

    /**
     * Get the items in the collection that are not present in the given items, using the callback.
     *
     * @param  Arrayable|iterable  $elements
     * @param  callable: int  $callback
     * @return static
     */
    public function diffUsing($elements, callable $callback)
    {
        return new static(array_udiff($this->elements, $this->getArrayableItems($elements), $callback));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     *
     * @param  Arrayable|iterable  $elements
     * @return static
     */
    public function diffAssoc($elements)
    {
        return new static(array_diff_assoc($this->elements, $this->getArrayableItems($elements)));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items, using the callback.
     *
     * @param  Arrayable|iterable  $elements
     * @param  callable: int  $callback
     * @return static
     */
    public function diffAssocUsing($elements, callable $callback)
    {
        return new static(array_diff_uassoc($this->elements, $this->getArrayableItems($elements), $callback));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @param  Arrayable|iterable  $elements
     * @return static
     */
    public function diffKeys($elements)
    {
        return new static(array_diff_key($this->elements, $this->getArrayableItems($elements)));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items, using the callback.
     *
     * @param  Arrayable|iterable  $elements
     * @param  callable: int  $callback
     * @return static
     */
    public function diffKeysUsing($elements, callable $callback)
    {
        return new static(array_diff_ukey($this->elements, $this->getArrayableItems($elements), $callback));
    }

    /**
     * Retrieve duplicate items from the collection.
     *
     * @param  (callable: bool)|string|null  $callback
     * @param  bool  $strict
     * @return static
     */
    public function duplicates($callback = null, $strict = false)
    {
        $elements = $this->map($this->valueRetriever($callback));

        $uniqueItems = $elements->unique(null, $strict);

        $compare = $this->duplicateComparator($strict);

        $duplicates = new static;

        foreach ($elements as $key => $value) {
            if ($uniqueItems->isNotEmpty() && $compare($value, $uniqueItems->first())) {
                $uniqueItems->shift();
            } else {
                $duplicates[$key] = $value;
            }
        }

        return $duplicates;
    }

    /**
     * Retrieve duplicate items from the collection using strict comparison.
     *
     * @param  (callable: bool)|string|null  $callback
     * @return static
     */
    public function duplicatesStrict($callback = null)
    {
        return $this->duplicates($callback, true);
    }

    /**
     * Get the comparison function to detect duplicates.
     *
     * @param  bool  $strict
     * @return callable: bool
     */
    protected function duplicateComparator($strict)
    {
        if ($strict) {
            return fn ($a, $b) => $a === $b;
        }

        return fn ($a, $b) => $a == $b;
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param  EnumerableInterface|array|string  $keys
     * @return static
     */
    public function except($keys)
    {
        if ($keys instanceof EnumerableInterface) {
            $keys = $keys->all();
        } elseif (! is_array($keys)) {
            $keys = func_get_args();
        }

        return new static(ArrayMethods::except($this->elements, $keys));
    }

    public function groupBy($groupBy, $preserveKeys = false)
    {
        if (! $this->useAsCallable($groupBy) && is_array($groupBy)) {
            $nextGroups = $groupBy;

            $groupBy = array_shift($nextGroups);
        }

        $groupBy = $this->valueRetriever($groupBy);

        $results = [];

        foreach ($this->elements as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (! is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                $groupKey = match (true) {
                    is_bool($groupKey) => (int) $groupKey,
                    $groupKey instanceof \Stringable => (string) $groupKey,
                    default => $groupKey,
                };

                if (! array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static;
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }

        $result = new static($results);

        if (! empty($nextGroups)) {
            return $result->map->groupBy($nextGroups, $preserveKeys);
        }

        return $result;
    }

    public function has($key)
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (! array_key_exists($value, $this->elements)) {
                return false;
            }
        }

        return true;
    }

    public function hasAny($key)
    {
        if ($this->isEmpty()) {
            return false;
        }

        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if ($this->has($value)) {
                return true;
            }
        }

        return false;
    }

    public function intersect($elements)
    {
        return new static(array_intersect($this->elements, $this->getArrayableItems($elements)));
    }

    public function intersectByKeys($elements)
    {
        return new static(array_intersect_key(
            $this->elements, $this->getArrayableItems($elements)
        ));
    }

    public function containsOneItem()
    {
        return $this->count() === 1;
    }

    public function join($glue, $finalGlue = '')
    {
        if ($finalGlue === '') {
            return $this->implode($glue);
        }

        $count = $this->count();

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $this->last();
        }

        $collection = new static($this->elements);

        $finalItem = $collection->pop();

        return $collection->implode($glue).$finalGlue.$finalItem;
    }

    public function skipUntil($value)
    {
        return new static($this->lazy()->skipUntil($value)->all());
    }

    public function skipWhile($value)
    {
        return new static($this->lazy()->skipWhile($value)->all());
    }

    public function sole($key = null, $operator = null, $value = null)
    {
        $filter = func_num_args() > 1
            ? $this->operatorForWhere(...func_get_args())
            : $key;

        $items = $this->unless($filter == null)->filter($filter);

        $count = $items->count();

        if ($count === 0) {
            throw new ItemNotFoundException;
        }

        if ($count > 1) {
            throw new MultipleItemsFoundException($count);
        }

        return $items->first();
    }

    public function firstOrFail($key = null, $operator = null, $value = null)
    {
        $filter = func_num_args() > 1
            ? $this->operatorForWhere(...func_get_args())
            : $key;

        $placeholder = new \stdClass();

        $item = $this->first($filter, $placeholder);

        if ($item === $placeholder) {
            throw new ItemNotFoundException;
        }

        return $item;
    }

    public function chunk($size)
    {
        if ($size <= 0) {
            return new static;
        }

        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    public function chunkWhile(callable $callback)
    {
        return new static(
            $this->lazy()->chunkWhile($callback)->mapInto(static::class)
        );
    }

    public function sortKeysUsing(callable $callback)
    {
        $items = $this->elements;

        uksort($items, $callback);

        return new static($items);
    }

    public function take($limit)
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    public function takeUntil($value)
    {
        return new static($this->lazy()->takeUntil($value)->all());
    }

    public function takeWhile($value)
    {
        return new static($this->lazy()->takeWhile($value)->all());
    }

    public function countBy($countBy = null)
    {
        return new static($this->lazy()->countBy($countBy)->all());
    }
}
