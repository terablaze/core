<?php

namespace Terablaze\Core\MaintenanceMode;

use Terablaze\Cache\Driver\CacheDriverInterface;

class CacheBasedMaintenanceMode implements MaintenanceModeInterface
{
    /**
     * The cache driver.
     *
     * @var CacheDriverInterface
     */
    protected CacheDriverInterface $cache;

    /**
     * The cache key to use when storing maintenance mode information.
     *
     * @var string
     */
    protected string $key;

    /**
     * Create a new cache based maintenance mode implementation.
     *
     * @param CacheDriverInterface $cache
     * @param string $key
     */
    public function __construct(CacheDriverInterface $cache, string $key)
    {
        $this->cache = $cache;
        $this->key = $key;
    }

    /**
     * Take the application down for maintenance.
     *
     * @param  array  $payload
     * @return void
     */
    public function activate(array $payload): void
    {
        $this->cache->set($this->key, $payload);
    }

    /**
     * Take the application out of maintenance.
     *
     * @return void
     */
    public function deactivate(): void
    {
        $this->cache->forget($this->key);
    }

    /**
     * Determine if the application is currently down for maintenance.
     *
     * @return bool
     */
    public function active(): bool
    {
        return $this->cache->has($this->key);
    }

    /**
     * Get the data array which was provided when the application was placed into maintenance.
     *
     * @return array
     */
    public function data(): array
    {
        return $this->cache->get($this->key);
    }
}
