<?php

namespace TeraBlaze\Cache\Driver;

use Psr\SimpleCache\CacheInterface;

interface CacheDriverInterface extends CacheInterface
{
    /**
     * Remove a single cached value
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key);

    /**
     * Remove all cached values
     *
     * @return bool
     */
    public function flush();
}