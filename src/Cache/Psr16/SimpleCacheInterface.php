<?php

namespace Terablaze\Cache\Psr16;

use Psr\SimpleCache\CacheInterface;
use Terablaze\Cache\Driver\CacheDriverInterface;

interface SimpleCacheInterface extends CacheInterface
{
    /**
     * Remove a single cached value
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool;

    /**
     * Remove all cached values
     *
     * @return bool
     */
    public function flush(): bool;

    public function getDriver(): CacheDriverInterface;

    public function increment(string $key, int $incrementBy = 1): bool|int;

    public function decrement(string $key, int $decrementBy = 1): bool|int;
}