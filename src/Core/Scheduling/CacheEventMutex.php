<?php

namespace Terablaze\Core\Scheduling;

use Terablaze\Cache\Driver\CacheDriverInterface;

class CacheEventMutex implements EventMutexInterface, CacheAwareInterface
{
    /**
     * The cache repository implementation.
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
     * Create a new overlapping strategy.
     *
     * @param  CacheDriverInterface  $cache
     * @return void
     */
    public function __construct(CacheDriverInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Attempt to obtain an event mutex for the given event.
     *
     * @param  Event  $event
     * @return bool
     */
    public function create(Event $event)
    {
        return $this->cache->set(
            $event->mutexName(), true, $event->expiresAt * 60
        );
    }

    /**
     * Determine if an event mutex exists for the given event.
     *
     * @param  Event  $event
     * @return bool
     */
    public function exists(Event $event)
    {
        return $this->cache->has($event->mutexName());
    }

    /**
     * Clear the event mutex for the given event.
     *
     * @param  Event  $event
     * @return void
     */
    public function forget(Event $event)
    {
        $this->cache->forget($event->mutexName());
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
