<?php

namespace TeraBlaze\Collection;

use ArrayIterator;
use Closure;
use TeraBlaze\ArrayMethods;
use TeraBlaze\Collection\Exceptions\TypeException;

use const ARRAY_FILTER_USE_BOTH;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_search;
use function array_slice;
use function array_values;
use function count;
use function current;
use function end;
use function in_array;
use function key;
use function next;
use function reset;
use function spl_object_hash;
use function dump;

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
    /**
     * An array containing the entries of this collection.
     *
     * @var array
     */
    private $elements;

    /**
     *
     * @var string|null $type
     */
    private $type;

    /**
     * Initializes a new ArrayCollection.
     *
     * @param array $elements
     * @param string|null $type
     */
    public function __construct(array $elements = [], ?string $type = null)
    {
        $this->elements = $elements;
        if ($type != null) {
            if (!in_array($type, self::BASIC_TYPES) && !class_exists($type)) {
                $message = "The data type you required is invalid. " .
                    "Make sure it is a valid class or any of the built-in basic " .
                    "php data types: (" . join(', ', self::BASIC_TYPES) . ")";
                throw new TypeException($message);
            }
            $this->type = $type;
            $this->verifyType();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function all()
    {
        return $this->elements;
    }

    public function toArray()
    {
        return $this->all();
    }

    /**
     * {@inheritDoc}
     */
    public function first()
    {
        return reset($this->elements);
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
     */
    protected function createFrom(array $elements)
    {
        return new static($elements);
    }

    /**
     * {@inheritDoc}
     */
    public function last()
    {
        return end($this->elements);
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
    public function offsetExists($offset)
    {
        return $this->containsKey($offset);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value)
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
    public function offsetUnset($offset)
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
    public function contains($element)
    {
        return in_array($element, $this->elements, true);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(Closure $p)
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
    public function get($key)
    {
        return $this->elements[$key] ?? null;
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
    public function count()
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
     */
    public function getIterator()
    {
        return new ArrayIterator($this->elements);
    }

    /**
     * {@inheritDoc}
     *
     * @return static
     */
    public function map(Closure $func)
    {
        return $this->createFrom(array_map($func, $this->elements));
    }

    /**
     * {@inheritDoc}
     *
     * @return static
     */
    public function filter(Closure $p)
    {
        return $this->createFrom(array_filter($this->elements, $p, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritDoc}
     */
    public function forAll(Closure $p)
    {
        foreach ($this->elements as $key => $element) {
            if (!$p($key, $element)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function partition(Closure $p)
    {
        $matches = $noMatches = [];

        foreach ($this->elements as $key => $element) {
            if ($p($key, $element)) {
                $matches[$key] = $element;
            } else {
                $noMatches[$key] = $element;
            }
        }

        return [$this->createFrom($matches), $this->createFrom($noMatches)];
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        $this->elements = [];
    }

    /**
     * {@inheritDoc}
     */
    public function slice($offset, $length = null)
    {
        return array_slice($this->elements, $offset, $length, true);
    }

    /**
     * Get the values of a given key.
     *
     * @param  string|array  $value
     * @param  string|null  $key
     * @return static
     */
    public function pluck($value, $key = null)
    {
        return new static(ArrayMethods::pluck($this->elements, $value, $key));
    }

    public function dump()
    {
        dump($this->elements);
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        return self::class . '@' . spl_object_hash($this);
    }

    private function verifyType()
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
}
