<?php

namespace Terablaze\Cache\Driver;

use Psr\SimpleCache\InvalidArgumentException;
use Terablaze\Cache\Lock\LockInterface;
use Terablaze\Cache\Lock\MemoryLock;

class MemoryDriver extends CacheDriver
{
    /** @var array<string, mixed> */
    private array $cached = [];

    /**
     * The array of locks.
     *
     * @var array
     */
    public $locks = [];

    public function has($key): bool
    {
        return isset($this->cached[$key]) && $this->cached[$key]['expires'] > time();
    }

    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            return $this->cached[$key]['value'];
        }

        return $default;
    }

    public function set($key, $value, $ttl = null): bool|static
    {
        if (!is_int($ttl)) {
            $seconds = (int) $this->config['seconds'];
        }

        $this->cached[$key] = [
            'value' => $value,
            'expires' => time() + $seconds,
        ];

        return $this;
    }

    public function delete($key): bool
    {
        unset($this->cached[$key]);
        return is_null($this->cached[$key]);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param int $incrementBy
     * @return bool|int
     * @throws InvalidArgumentException
     */
    public function increment(string $key, int $incrementBy = 1): bool|int
    {
        if (! is_null($existing = $this->get($key))) {
            return tap(((int) $existing) + $incrementBy, function ($incremented) use ($key) {

                $this->cached[$key]['value'] = $incremented;
            });
        }

        $this->set($key, $incrementBy);

        return $incrementBy;
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $decrementBy = 1): bool|int
    {
        return $this->increment($key, $decrementBy * -1);
    }

    public function clear(): bool
    {
        $this->cached = [];
        return empty($this->cached);
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
        return new MemoryLock($this, $name, $seconds, $owner);
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
