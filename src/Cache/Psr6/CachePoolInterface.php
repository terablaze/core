<?php

namespace Terablaze\Cache\Psr6;

use Psr\Cache\CacheItemPoolInterface;

// Help opcache.preload discover always-needed symbols
class_exists(CacheCacheItem::class);

/**
 * Interface for adapters managing instances of Symfony's CacheItem.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface CachePoolInterface extends CacheItemPoolInterface
{
    /**
     * {@inheritdoc}
     *
     * @return CacheCacheItem
     */
    public function getItem($key);

    /**
     * {@inheritdoc}
     *
     * @return \Traversable<string, CacheCacheItem>
     */
    public function getItems(array $keys = []);

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function clear(string $prefix = '');
}
