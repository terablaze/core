<?php

namespace Terablaze\Database\ORM;

use Terablaze\Collection\ArrayCollection;
use Terablaze\Collection\CollectionInterface;
use Terablaze\Database\Query\QueryBuilderInterface;
use Terablaze\Queue\QueueableCollection;
use Terablaze\Queue\QueueableEntity;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\Interfaces\Arrayable;

class EntityCollection extends ArrayCollection implements QueueableCollection
{
    /**
     * Find a model in the collection by key.
     *
     * @param mixed $key
     * @param $default
     * @return static|Model|null
     */
    public function find($key, $default = null)
    {
        if ($key instanceof Model) {
            $key = $key->getKey();
        }

        if ($key instanceof Arrayable) {
            $key = $key->toArray();
        }

        if (is_array($key)) {
            if ($this->isEmpty()) {
                return new static;
            }

            return $this->whereIn($this->first()->getKeyName(), $key);
        }

        return ArrayMethods::first($this->elements, function ($model) use ($key) {
            return $model->_getKey() == $key;
        }, $default);
    }

    /**
     * Determine if a key exists in the collection.
     *
     * @param callable|Model|string|int $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function contains($key, $operator = null, $value = null)
    {
        if (func_num_args() > 1 || $this->useAsCallable($key)) {
            return parent::contains(...func_get_args());
        }

        if ($key instanceof Model) {
            return parent::contains(function ($model) use ($key) {
                return $model->_is($key);
            });
        }

        return parent::contains(function ($model) use ($key) {
            return $model->_getKey() == $key;
        });
    }

    /**
     * Get the array of primary keys.
     *
     * @return array<int, array-key>
     */
    public function modelKeys()
    {
        return array_map(function ($model) {
            return $model->_getKey();
        }, $this->elements);
    }

    /**
     * Merge the collection with the given items.
     *
     * @param iterable $items
     * @return static
     */
    public function merge($items)
    {
        $dictionary = $this->getDictionary();

        foreach ($items as $item) {
            $dictionary[$item->_getKey()] = $item;
        }

        return new static(array_values($dictionary));
    }

    /**
     * Reload a fresh model instance from the database for all the entities.
     *
     * @param array|string $with
     * @return static
     */
    public function fresh($with = [])
    {
        if ($this->isEmpty()) {
            return new static;
        }

        /** @var Model $model */
        $model = $this->first();

        $freshModels = (new EntityCollection($model::query()
            ->where($model::query()->expr()->in($model->_getKeyName(), $this->modelKeys()))
            ->all()))->getDictionary();

        return $this->filter(function ($model) use ($freshModels) {
            return isset($freshModels[$model->_getKey()]);
        })
            ->map(function ($model) use ($freshModels) {
                return $freshModels[$model->_getKey()];
            });
    }

    /**
     * Diff the collection with the given items.
     *
     * @param iterable $items
     * @return static
     */
    public function diff($items)
    {
        $diff = new static;

        $dictionary = $this->getDictionary($items);

        foreach ($this->elements as $item) {
            if (!isset($dictionary[$item->_getKey()])) {
                $diff->add($item);
            }
        }

        return $diff;
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param iterable $items
     * @return static
     */
    public function intersect($items)
    {
        $intersect = new static;

        if (empty($items)) {
            return $intersect;
        }

        $dictionary = $this->getDictionary($items);

        foreach ($this->elements as $item) {
            if (isset($dictionary[$item->_getKey()])) {
                $intersect->add($item);
            }
        }

        return $intersect;
    }

    /**
     * Returns all models in the collection except the models with specified keys.
     *
     * @param array<array-key, mixed>|null $keys
     * @return static
     */
    public function except($keys)
    {
        $dictionary = ArrayMethods::except($this->getDictionary(), $keys);

        return new static(array_values($dictionary));
    }

    /**
     * Get a dictionary keyed by primary keys.
     *
     * @param iterable|null $items
     * @return array
     */
    public function getDictionary($items = null)
    {
        $items = is_null($items) ? $this->elements : $items;

        $dictionary = [];

        foreach ($items as $value) {
            $dictionary[$value->_getKey()] = $value;
        }

        return $dictionary;
    }

    /**
     * Get the comparison function to detect duplicates.
     *
     * @param bool $strict
     * @return callable: bool
     */
    protected function duplicateComparator($strict)
    {
        return function ($a, $b) {
            return $a->_is($b);
        };
    }

    /**
     * Get the type of the entities being queued.
     *
     * @return string|null
     *
     * @throws \LogicException
     */
    public function getQueueableClass()
    {
        if ($this->isEmpty()) {
            return null;
        }

        $class = $this->getQueueableModelClass($this->first());

        $this->each(function ($model) use ($class) {
            if ($this->getQueueableModelClass($model) !== $class) {
                throw new \LogicException('Queueing collections with multiple model types is not supported.');
            }
        });

        return $class;
    }

    /**
     * Get the queueable class name for the given model.
     *
     * @param Model $model
     * @return string
     */
    protected function getQueueableModelClass($model)
    {
        return get_class($model);
    }

    /**
     * Get the identifiers for all of the entities.
     *
     * @return array<int, mixed>
     */
    public function getQueueableIds()
    {
        if ($this->isEmpty()) {
            return [];
        }

        return $this->first() instanceof QueueableEntity
            ? $this->map(fn ($model) => $model->getQueueableId())->all()
            : $this->modelKeys();
    }

    /**
     * Get the connection of the entities being queued.
     *
     * @return string|null
     *
     * @throws \LogicException
     */
    public function getQueueableConnection()
    {
        if ($this->isEmpty()) {
            return null;
        }

        $connection = $this->first()->getQueueableConnection();

        $this->each(function ($model) use ($connection) {
            if ($model->getQueueableConnection() !== $connection) {
                throw new \LogicException('Queueing collections with multiple model connections is not supported.');
            }
        });

        return $connection;
    }

    /**
     * Get the Eloquent query builder from the collection.
     *
     * @return QueryBuilderInterface
     *
     * @throws \LogicException
     */
    public function toQuery()
    {
        /** @var Model $model */
        $model = $this->first();

        if (!$model) {
            throw new \LogicException('Unable to create query for empty collection.');
        }

        $class = get_class($model);

        if ($this->filter(function ($model) use ($class) {
            return !$model instanceof $class;
        })->isNotEmpty()) {
            throw new \LogicException('Unable to create query for collection with mixed types.');
        }

        return $model::query()->where($model::query()->expr()->in($model->_getKeyName(), $this->modelKeys()));
    }
}
