<?php

namespace Terablaze\Queue;

use Terablaze\Database\ORM\Model;
use Terablaze\Database\ORM\ModelIdentifier;
use Terablaze\Database\Query\QueryBuilderInterface;
use Terablaze\Queue\QueueableCollection;
use Terablaze\Queue\QueueableEntity;
use Terablaze\Database\ORM\EntityCollection;
use Terablaze\Support\Helpers;

trait SerializesAndRestoresModelIdentifiers
{
    /**
     * Get the property value prepared for serialization.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getSerializedPropertyValue($value)
    {
        if ($value instanceof QueueableCollection) {
            return (new ModelIdentifier(
                $value->getQueueableClass(),
                $value->getQueueableIds(),
                $value->getQueueableConnection()
            ))->useCollectionClass(
                ($collectionClass = get_class($value)) !== EntityCollection::class
                    ? $collectionClass
                    : null
            );
        }

        if ($value instanceof QueueableEntity) {
            return new ModelIdentifier(
                get_class($value),
                $value->getQueueableId(),
                $value->getQueueableConnection()
            );
        }

        return $value;
    }

    /**
     * Get the restored property value after deserialization.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getRestoredPropertyValue($value)
    {
        if (! $value instanceof ModelIdentifier) {
            return $value;
        }

        return is_array($value->id)
                ? $this->restoreCollection($value)
                : $this->restoreModel($value);
    }

    /**
     * Restore a queueable collection instance.
     *
     * @param  ModelIdentifier  $value
     * @return EntityCollection
     */
    protected function restoreCollection($value)
    {
        if (! $value->class || count($value->id) === 0) {
            return ! is_null($value->collectionClass ?? null)
                ? new $value->collectionClass
                : new EntityCollection();
        }

        /** @var Model $model */
        $model = new $value->class();
        /** @var EntityCollection $collection */
        $collection = $value->class::all($value->class::query()->expr()->in($model->_getKeyName(), $value->id));

        $collection = $collection->keyBy(fn ($model) => $model->_getKey());

        $collectionClass = get_class($collection);

        return new $collectionClass(
            Helpers::collect($value->id)->map(function ($id) use ($collection) {
                return $collection[$id] ?? null;
            })->filter()
        );
    }

    /**
     * Restore the model from the model identifier instance.
     *
     * @param ModelIdentifier  $value
     * @return mixed
     */
    public function restoreModel($value)
    {
        /** @var Model $model */
        $model = new $value->class();
        /** @var EntityCollection $collection */
        return $value->class::first($value->class::query()->expr()->in($model->_getKeyName(), $value->id));
    }
}
