<?php

namespace Terablaze\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Terablaze\Cache\Driver\CacheDriverInterface;
use Terablaze\Cache\Driver\MemcachedDriver;
use Terablaze\Cache\Driver\FileDriver;
use Terablaze\Cache\Driver\MemoryDriver;
use Terablaze\Cache\Driver\NullDriver;
use Terablaze\Cache\Exception\InvalidArgumentException;
use Terablaze\Cache\Exception\DriverException;
use Terablaze\Cache\Psr16\SimpleCache;
use Terablaze\Cache\Psr16\SimpleCacheInterface;
use Terablaze\Cache\Psr6\CachePool;
use Terablaze\Cache\Psr6\CachePoolInterface;
use Terablaze\Container\Exception\ServiceNotFoundException;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Core\Parcel\ParcelInterface;

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
     * @throws InvalidArgumentException
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
                throw new InvalidArgumentException(
                    "Invalid cache type or cache configuration not properly set"
                );
        }
        $simpleCache = new SimpleCache($cacheDriver);
        $cache = new CachePool($cacheDriver);

        $this->container->registerServiceInstance("simple_" . $driverName, $simpleCache);
        $this->container->registerServiceInstance($driverName, $cache);
        $this->container->setAlias("cache.store.{$confKey}", $driverName);
        $this->container->setAlias("simple_cache.store.{$confKey}", "simple_" . $driverName);
        $this->container->setAlias("cache.{$confKey}", $driverName);
        $this->container->setAlias("simple_cache.{$confKey}", "simple_" . $driverName);

        if (getConfig('cache.default') === $confKey) {
            $this->container->registerServiceInstance(CacheDriverInterface::class, $cacheDriver);
            if (getConfig("cache.implementation_default") === "cache") {
                $this->container->setAlias('cache', $driverName);
            }
            if (getConfig("cache.implementation_default") === "simple_cache") {
                $this->container->setAlias('cache', "simple_" . $driverName);
            }
            $this->container->setAlias(CacheInterface::class, "simple_" . $driverName);
            $this->container->setAlias(SimpleCacheInterface::class, "simple_" . $driverName);
            $this->container->setAlias(CacheItemPoolInterface::class, $driverName);
            $this->container->setAlias(CachePoolInterface::class, $driverName);
        }
    }
}
