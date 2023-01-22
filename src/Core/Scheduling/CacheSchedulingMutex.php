<?php

namespace Terablaze\Core\Scheduling;

use DateTimeInterface;
use Terablaze\Cache\Driver\CacheDriverInterface;

class CacheSchedulingMutex implements SchedulingMutexInterface, CacheAwareInterface
{
    /**
     * The cache factory implementation.
     *
     * @var CacheDriverInterface
     */
    public $cache;

    /**
     * The cache store that should be used.
     *
     * @var string|null
     */
    public $store;

    /**
     * Create a new scheduling strategy.
     *
     * @param  CacheDriverInterface  $cache
     * @return void
     */
    public function __construct(CacheDriverInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Attempt to obtain a scheduling mutex for the given event.
     *
     * @param Event $event
     * @param  \DateTimeInterface  $time
     * @return bool
     */
    public function create(Event $event, DateTimeInterface $time)
    {
        return $this->cache->set(
            $event->mutexName().$time->format('Hi'), true, 3600
        );
    }

    /**
     * Determine if a scheduling mutex exists for the given event.
     *
     * @param Event $event
     * @param  \DateTimeInterface  $time
     * @return bool
     */
    public function exists(Event $event, DateTimeInterface $time)
    {
        return $this->cache->has(
            $event->mutexName().$time->format('Hi')
        );
    }

    /**
     * Specify the cache store that should be used.
     *
     * @param  string  $store
     * @return $this
     */
    public function useStore($store)
    {
        $this->store = $store;

        return $this;
    }
}
