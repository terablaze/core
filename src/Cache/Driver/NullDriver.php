<?php

namespace TeraBlaze\Cache\Driver;

class NullDriver extends CacheDriver
{
    public function has($key)
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
    public function clear(): bool
    {
        return true;
    }
}
