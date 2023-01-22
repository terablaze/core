<?php

namespace Terablaze\Cache\Driver;

use Psr\SimpleCache\CacheInterface;
use Terablaze\Cache\LockProviderInterface;

interface CacheDriverInterface extends CacheInterface, LockProviderInterface
{
    /**
     * Remove a single cached value
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key);

    /**
     * Remove all cached values
     *
     * @return bool
     */
    public function flush();

    /**
     * Increments the value of an item in the cache;
     *
     * @param string $key
     * @param int $incrementBy
     * @return int|bool
     */
    public function increment(string $key, int $incrementBy = 1);

    /**
     * Decrements the value of an item in the cache;
     *
     * @param string $key
     * @param int $decrementBy
     * @return int|bool
     */
    public function decrement(string $key, int $decrementBy = 1);
}
