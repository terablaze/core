<?php

namespace Terablaze\Cache\Driver;

use Memcached;
use Terablaze\Cache\Exception\ServiceException;
use Terablaze\Cache\Lock\LockInterface;
use Terablaze\Cache\Lock\MemcachedLock;
use Terablaze\Cache\LockProviderInterface;

class MemcachedDriver extends CacheDriver
{
    private ?Memcached $memcached = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    protected function connect(): void
    {
        try {
            $this->memcached = new Memcached();

            if (!empty($this->config['host'])) {
                $this->memcached->addServer($this->config['host'], $this->config['port'] ?? 11211);
            }

            foreach ($this->config['servers'] ?? [] as $server) {
                $this->memcached->addServer($server['host'], ($server['port'] ?? 11211), $server['weight']);
            }
        } catch (\Exception $e) {
            throw new ServiceException("Unable to connect to service");
        }
    }

    public function memcached(): Memcached
    {
        if (is_null($this->memcached)) {
            $this->connect();
        }
        return $this->memcached;
    }

    public function disconnect()
    {
        $this->memcached()->resetServerList();
        $this->memcached = null;
    }

    public function has($key)
    {
        $key = $this->fixKey($key);
        return $this->memcached()->get($key) !== false;
    }

    public function get($key, $default = null)
    {
        $key = $this->fixKey($key);
        if ($value = $this->memcached()->get($key)) {
            return $value;
        }

        return $default;
    }

    public function set($key, $value, $ttl = null)
    {
        $key = $this->fixKey($key);
        if (!is_int($ttl)) {
            $ttl = $this->ttl();
        }

        return $this->memcached()->set($key, $value, time() + $ttl);
    }

    public function delete($key): bool
    {
        $key = $this->fixKey($key);
        return $this->memcached()->delete($key);
    }

    /**
     * @inheritDoc
     */
    public function increment($key, $incrementBy = 1)
    {
        $key = $this->fixKey($key);
        return $this->memcached->increment($key, $incrementBy);
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $decrementBy = 1)
    {
        $key = $this->fixKey($key);
        return $this->memcached->decrement($key, $decrementBy);
    }

    public function clear(): bool
    {
        return $this->memcached()->flush();
    }

    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return LockInterface
     */
    public function lock($name, $seconds = 0, $owner = null)
    {
        return new MemcachedLock($this->memcached, $this->fixKey($name), $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return LockInterface
     */
    public function restoreLock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }
}
