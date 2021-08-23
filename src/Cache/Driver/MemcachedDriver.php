<?php

namespace TeraBlaze\Cache\Driver;

use Memcached;
use TeraBlaze\Cache\Exception\ServiceException;

class MemcachedDriver extends CacheDriver
{
    private ?Memcached $memcached = null;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->connect();
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

    public function disconnect()
    {
        $this->memcached->resetServerList();
        $this->memcached = null;
    }

    public function has($key)
    {
        $key = $this->fixKey($key);
        return $this->memcached->get($key) !== false;
    }

    public function get($key, $default = null)
    {
        $key = $this->fixKey($key);
        if ($value = $this->memcached->get($key)) {
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

        return $this->memcached->set($key, $value, time() + $ttl);
    }

    public function delete($key): bool
    {
        $key = $this->fixKey($key);
        return $this->memcached->delete($key);
    }

    public function clear(): bool
    {
        return $this->memcached->flush();
    }
}
