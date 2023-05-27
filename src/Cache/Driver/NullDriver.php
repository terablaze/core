<?php

namespace Terablaze\Cache\Driver;

use Terablaze\Cache\Lock\LockInterface;
use Terablaze\Cache\Lock\NullLock;

class NullDriver extends CacheDriver
{
    public function has($key): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        return $default;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null): iterable
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function increment($key, $incrementBy = 1): bool|int
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $decrementBy = 1): bool|int
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return true;
    }

    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return LockInterface
     */
    public function lock($name, $seconds = 0, $owner = null): LockInterface
    {
        return new NullLock($name, $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return LockInterface
     */
    public function restoreLock($name, $owner): LockInterface
    {
        return $this->lock($name, 0, $owner);
    }
}
