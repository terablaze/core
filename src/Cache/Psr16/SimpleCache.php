<?php

namespace Terablaze\Cache\Psr16;

use Terablaze\Cache\Driver\CacheDriver;
use Terablaze\Cache\Driver\CacheDriverInterface;

class SimpleCache implements SimpleCacheInterface
{
    private CacheDriver $cacheDriver;

    public function __construct(CacheDriver $cacheDriver)
    {
        $this->cacheDriver = $cacheDriver;
    }

    public function get($key, $default = null)
    {
        return $this->cacheDriver->get($key);
    }

    public function set($key, $value, $ttl = null)
    {
        return $this->cacheDriver->set($key, $value);
    }

    public function delete($key)
    {
        return $this->cacheDriver->delete($key);
    }

    public function clear()
    {
        return $this->cacheDriver->clear();
    }

    public function getMultiple($keys, $default = null)
    {
        return $this->cacheDriver->getMultiple($keys, $default);
    }

    public function setMultiple($values, $ttl = null)
    {
        return $this->cacheDriver->setMultiple($values, $ttl);
    }

    public function deleteMultiple($keys)
    {
        return $this->cacheDriver->deleteMultiple($keys);
    }

    public function has($key)
    {
        return $this->cacheDriver->has($key);
    }

    public function forget(string $key)
    {
        return $this->cacheDriver->forget($key);
    }

    public function flush()
    {
        return $this->cacheDriver->flush();
    }

    public function getDriver(): CacheDriverInterface
    {
        return $this->cacheDriver;
    }

    public function increment(string $key, int $incrementBy = 1)
    {
        return $this->cacheDriver->increment($key, $incrementBy);
    }

    public function decrement(string $key, int $decrementBy = 1)
    {
        return $this->cacheDriver->decrement($key, $decrementBy);
    }
}