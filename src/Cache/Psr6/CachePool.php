<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Terablaze\Cache\Psr6;

use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareTrait;
use Terablaze\Cache\Driver\CacheDriverInterface;
use Terablaze\Cache\Exception\InvalidArgumentException;

/**
 * Turns a PSR-16 cache into a PSR-6 one.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CachePool implements CachePoolInterface
{
    use LoggerAwareTrait;

    /**
     * @internal
     */
    protected const NS_SEPARATOR = '_';

    protected CacheDriverInterface $pool;

    private $miss;

    /**
     * @var \Closure needs to be set by class, signature is function(string <key>, mixed <value>, bool <isHit>)
     */
    private static $createCacheItem;

    /**
     * @var \Closure needs to be set by class, signature is function(array <deferred>, string <namespace>, array <&expiredIds>)
     */
    private static $mergeByLifetime;

    private $namespace = '';
    private $defaultLifetime;
    private $namespaceVersion = '';
    private $versioningIsEnabled = false;
    private $deferred = [];
    private $ids = [];

    /**
     * @var int|null The maximum length to enforce for identifiers or null when no limit applies
     */
    protected $maxIdLength;

    private static $apcuSupported;
    private static $phpFilesSupported;

    public function __construct(CacheDriverInterface $pool, string $namespace = '', int $defaultLifetime = 0)
    {
        $this->namespace = '' === $namespace ? '' : CacheCacheItem::validateKey($namespace).static::NS_SEPARATOR;
        $this->defaultLifetime = $defaultLifetime;
        if (null !== $this->maxIdLength && \strlen($namespace) > $this->maxIdLength - 24) {
            throw new InvalidArgumentException(
                sprintf(
                    'Namespace must be %d chars max, %d given ("%s").',
                    $this->maxIdLength - 24,
                    \strlen($namespace),
                    $namespace
                )
            );
        }
        self::$createCacheItem ?? self::$createCacheItem = \Closure::bind(
            static function ($key, $value, $isHit) {
                $item = new CacheCacheItem();
                $item->key = $key;
                $item->value = $v = $value;
                $item->isHit = $isHit;
                // Detect wrapped values that encode for their expiry and creation duration
                // For compactness, these values are packed in the key of an array using
                // magic numbers in the form 9D-..-..-..-..-00-..-..-..-5F
                if (\is_array($v) && 1 === \count($v) && 10 === \strlen($k = (string) array_key_first($v)) && "\x9D" === $k[0] && "\0" === $k[5] && "\x5F" === $k[9]) {
                    $item->value = $v[$k];
                    $v = unpack('Ve/Nc', substr($k, 1, -1));
                    $item->metadata[CacheCacheItem::METADATA_EXPIRY] = $v['e'] + CacheCacheItem::METADATA_EXPIRY_OFFSET;
                    $item->metadata[CacheCacheItem::METADATA_CTIME] = $v['c'];
                }

                return $item;
            },
            null,
            CacheCacheItem::class
        );
        self::$mergeByLifetime ?? self::$mergeByLifetime = \Closure::bind(
            static function ($deferred, $namespace, &$expiredIds, $getId, $defaultLifetime) {
                $byLifetime = [];
                $now = microtime(true);
                $expiredIds = [];

                foreach ($deferred as $key => $item) {
                    $key = (string) $key;
                    if (null === $item->expiry) {
                        $ttl = 0 < $defaultLifetime ? $defaultLifetime : 0;
                    } elseif (0 === $item->expiry) {
                        $ttl = 0;
                    } elseif (0 >= $ttl = (int) (0.1 + $item->expiry - $now)) {
                        $expiredIds[] = $getId($key);
                        continue;
                    }
                    if (isset(($metadata = $item->newMetadata)[CacheCacheItem::METADATA_TAGS])) {
                        unset($metadata[CacheCacheItem::METADATA_TAGS]);
                    }
                    // For compactness, expiry and creation duration are packed in the key of an array, using magic numbers as separators
                    $byLifetime[$ttl][$getId($key)] = $metadata ? ["\x9D".pack('VN', (int) (0.1 + $metadata[self::METADATA_EXPIRY] - self::METADATA_EXPIRY_OFFSET), $metadata[self::METADATA_CTIME])."\x5F" => $item->value] : $item->value;
                }

                return $byLifetime;
            },
            null,
            CacheCacheItem::class
        );
        $this->pool = $pool;
        $this->miss = new \stdClass();
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function commit()
    {
        $ok = true;
        $byLifetime = (self::$mergeByLifetime)($this->deferred, $this->namespace, $expiredIds, \Closure::fromCallable([$this, 'getId']), $this->defaultLifetime);
        $retry = $this->deferred = [];

        if ($expiredIds) {
            $this->doDelete($expiredIds);
        }
        foreach ($byLifetime as $lifetime => $values) {
            try {
                $e = $this->doSave($values, $lifetime);
            } catch (\Exception $e) {
            }
            if (true === $e || [] === $e) {
                continue;
            }
            if (\is_array($e) || 1 === \count($values)) {
                foreach (\is_array($e) ? $e : array_keys($values) as $id) {
                    $ok = false;
                    $v = $values[$id];
                    $type = get_debug_type($v);
                    $message = sprintf('Failed to save key "{key}" of type %s%s', $type, $e instanceof \Exception ? ': '.$e->getMessage() : '.');
                    CacheCacheItem::log($this->logger, $message, ['key' => substr($id, \strlen($this->namespace)), 'exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
                }
            } else {
                foreach ($values as $id => $v) {
                    $retry[$lifetime][] = $id;
                }
            }
        }

        // When bulk-save failed, retry each item individually
        foreach ($retry as $lifetime => $ids) {
            foreach ($ids as $id) {
                try {
                    $v = $byLifetime[$lifetime][$id];
                    $e = $this->doSave([$id => $v], $lifetime);
                } catch (\Exception $e) {
                }
                if (true === $e || [] === $e) {
                    continue;
                }
                $ok = false;
                $type = get_debug_type($v);
                $message = sprintf('Failed to save key "{key}" of type %s%s', $type, $e instanceof \Exception ? ': '.$e->getMessage() : '.');
                CacheCacheItem::log($this->logger, $message, ['key' => substr($id, \strlen($this->namespace)), 'exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
            }
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        foreach ($this->pool->getMultiple($ids, $this->miss) as $key => $value) {
            if ($this->miss !== $value) {
                yield $key => $value;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave(string $id)
    {
        return $this->pool->has($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear(string $namespace)
    {
        return $this->pool->clear();
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        return $this->pool->deleteMultiple($ids);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, int $lifetime)
    {
        return $this->pool->setMultiple($values, 0 === $lifetime ? null : $lifetime);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function hasItem($key)
    {
        $id = $this->getId($key);

        if (isset($this->deferred[$key])) {
            $this->commit();
        }

        try {
            return $this->doHave($id);
        } catch (\Exception $e) {
            CacheCacheItem::log($this->logger, 'Failed to check if key "{key}" is cached: '.$e->getMessage(), ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function clear(string $prefix = '')
    {
        $this->deferred = [];
        if ($cleared = $this->versioningIsEnabled) {
            if ('' === $namespaceVersionToClear = $this->namespaceVersion) {
                foreach ($this->doFetch([static::NS_SEPARATOR.$this->namespace]) as $v) {
                    $namespaceVersionToClear = $v;
                }
            }
            $namespaceToClear = $this->namespace.$namespaceVersionToClear;
            $namespaceVersion = strtr(substr_replace(base64_encode(pack('V', mt_rand())), static::NS_SEPARATOR, 5), '/', '_');
            try {
                $cleared = $this->doSave([static::NS_SEPARATOR.$this->namespace => $namespaceVersion], 0);
            } catch (\Exception $e) {
                $cleared = false;
            }
            if ($cleared = true === $cleared || [] === $cleared) {
                $this->namespaceVersion = $namespaceVersion;
                $this->ids = [];
            }
        } else {
            $namespaceToClear = $this->namespace.$prefix;
        }

        try {
            return $this->doClear($namespaceToClear) || $cleared;
        } catch (\Exception $e) {
            CacheCacheItem::log($this->logger, 'Failed to clear the cache: '.$e->getMessage(), ['exception' => $e, 'cache-adapter' => get_debug_type($this)]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function deleteItem($key)
    {
        return $this->deleteItems([$key]);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function deleteItems(array $keys)
    {
        $ids = [];

        foreach ($keys as $key) {
            $ids[$key] = $this->getId($key);
            unset($this->deferred[$key]);
        }

        try {
            if ($this->doDelete($ids)) {
                return true;
            }
        } catch (\Exception $e) {
        }

        $ok = true;

        // When bulk-delete failed, retry each item individually
        foreach ($ids as $key => $id) {
            try {
                $e = null;
                if ($this->doDelete([$id])) {
                    continue;
                }
            } catch (\Exception $e) {
            }
            $message = 'Failed to delete key "{key}"'.($e instanceof \Exception ? ': '.$e->getMessage() : '.');
            CacheCacheItem::log($this->logger, $message, ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
            $ok = false;
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $id = $this->getId($key);

        if (isset($this->deferred[$key])) {
            $this->commit();
        }

        $isHit = false;
        $value = null;

        try {
            foreach ($this->doFetch([$id]) as $value) {
                $isHit = true;
            }

            return (self::$createCacheItem)($key, $value, $isHit);
        } catch (\Exception $e) {
            CacheCacheItem::log($this->logger, 'Failed to fetch key "{key}": '.$e->getMessage(), ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
        }

        return (self::$createCacheItem)($key, null, false);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        $ids = [];
        $commit = false;

        foreach ($keys as $key) {
            $ids[] = $this->getId($key);
            $commit = $commit || isset($this->deferred[$key]);
        }

        if ($commit) {
            $this->commit();
        }

        try {
            $items = $this->doFetch($ids);
        } catch (\Exception $e) {
            CacheCacheItem::log($this->logger, 'Failed to fetch items: '.$e->getMessage(), ['keys' => $keys, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
            $items = [];
        }
        $ids = array_combine($ids, $keys);

        return $this->generateItems($items, $ids);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof CacheCacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (!$item instanceof CacheCacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * Enables/disables versioning of items.
     *
     * When versioning is enabled, clearing the cache is atomic and doesn't require listing existing keys to proceed,
     * but old keys may need garbage collection and extra round-trips to the back-end are required.
     *
     * Calling this method also clears the memoized namespace version and thus forces a resynchonization of it.
     *
     * @return bool the previous state of versioning
     */
    public function enableVersioning(bool $enable = true)
    {
        $wasEnabled = $this->versioningIsEnabled;
        $this->versioningIsEnabled = $enable;
        $this->namespaceVersion = '';
        $this->ids = [];

        return $wasEnabled;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        if ($this->deferred) {
            $this->commit();
        }
        $this->namespaceVersion = '';
        $this->ids = [];
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        if ($this->deferred) {
            $this->commit();
        }
    }

    private function generateItems(iterable $items, array &$keys): \Generator
    {
        $f = self::$createCacheItem;

        try {
            foreach ($items as $id => $value) {
                if (!isset($keys[$id])) {
                    throw new InvalidArgumentException(sprintf('Could not match value id "%s" to keys "%s".', $id, implode('", "', $keys)));
                }
                $key = $keys[$id];
                unset($keys[$id]);
                yield $key => $f($key, $value, true);
            }
        } catch (\Exception $e) {
            CacheCacheItem::log($this->logger, 'Failed to fetch items: '.$e->getMessage(), ['keys' => array_values($keys), 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
        }

        foreach ($keys as $key) {
            yield $key => $f($key, null, false);
        }
    }

    private function getId($key)
    {
        if ($this->versioningIsEnabled && '' === $this->namespaceVersion) {
            $this->ids = [];
            $this->namespaceVersion = '1'.static::NS_SEPARATOR;
            try {
                foreach ($this->doFetch([static::NS_SEPARATOR.$this->namespace]) as $v) {
                    $this->namespaceVersion = $v;
                }
                if ('1'.static::NS_SEPARATOR === $this->namespaceVersion) {
                    $this->namespaceVersion = strtr(substr_replace(base64_encode(pack('V', time())), static::NS_SEPARATOR, 5), '/', '_');
                    $this->doSave([static::NS_SEPARATOR.$this->namespace => $this->namespaceVersion], 0);
                }
            } catch (\Exception $e) {
            }
        }

        if (\is_string($key) && isset($this->ids[$key])) {
            return $this->namespace.$this->namespaceVersion.$this->ids[$key];
        }
        \assert('' !== CacheCacheItem::validateKey($key));
        $this->ids[$key] = $key;

        if (\count($this->ids) > 1000) {
            array_splice($this->ids, 0, 500); // stop memory leak if there are many keys
        }

        if (null === $this->maxIdLength) {
            return $this->namespace.$this->namespaceVersion.$key;
        }
        if (\strlen($id = $this->namespace.$this->namespaceVersion.$key) > $this->maxIdLength) {
            // Use MD5 to favor speed over security, which is not an issue here
            $this->ids[$key] = $id = substr_replace(base64_encode(hash('md5', $key, true)), static::NS_SEPARATOR, -(\strlen($this->namespaceVersion) + 2));
            $id = $this->namespace.$this->namespaceVersion.$id;
        }

        return $id;
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback(string $class)
    {
        throw new \DomainException('Class not found: '.$class);
    }

    public function getDriver(): CacheDriverInterface
    {
        return $this->pool;
    }
}
