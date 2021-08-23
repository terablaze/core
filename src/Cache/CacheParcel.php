<?php

namespace TeraBlaze\Cache;

use Psr\SimpleCache\CacheInterface;
use TeraBlaze\Cache\Driver\CacheDriver;
use TeraBlaze\Cache\Driver\CacheDriverInterface;
use TeraBlaze\Cache\Driver\MemcachedDriver;
use TeraBlaze\Cache\Driver\FileDriver;
use TeraBlaze\Cache\Driver\MemoryDriver;
use TeraBlaze\Cache\Driver\NullDriver;
use TeraBlaze\Cache\Exception\ArgumentException;
use TeraBlaze\Cache\Exception\DriverException;
use TeraBlaze\Container\Exception\ServiceNotFoundException;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;

class CacheParcel extends Parcel implements ParcelInterface
{
    protected $type;

    protected $options;

    public function boot(): void
    {
        $parsed = loadConfig("cache");

        foreach ($parsed->get('cache.stores') as $key => $conf) {
            $this->initialize($key, $conf);
        }
    }

    /**
     * @throws ServiceNotFoundException
     * @throws ArgumentException
     */
    public function initialize(string $confKey, array $config): void
    {
        $type = $config['type'] ?? $config['driver'] ?? '';

        $driverName = "cache.stores.{$confKey}";
        if (empty($type)) {
            throw new DriverException("Cache driver type not set");
        }

        switch ($type) {
            case "memcache":
            case "memcached":
                $cacheDriver = new MemcachedDriver($config);
                break;
            case "file":
                $cacheDriver = new FileDriver($config);
                break;
            case "memory":
                $cacheDriver = new MemoryDriver($config);
                break;
            case "null":
                $cacheDriver = new NullDriver($config);
                break;
            default:
                throw new ArgumentException(
                    "Invalid cache type or cache configuration not properly set"
                );
        }
        $this->container->registerServiceInstance($driverName, $cacheDriver);
        $this->container->setAlias("cache.store.{$confKey}", $driverName);

        if (getConfig('cache.default') === $confKey) {
            $this->container->setAlias('cache', $driverName);
            $this->container->setAlias(CacheInterface::class, $driverName);
            $this->container->setAlias(CacheDriverInterface::class, $driverName);
        }
    }
}
