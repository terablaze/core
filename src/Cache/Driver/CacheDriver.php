<?php

namespace TeraBlaze\Cache\Driver;

use TeraBlaze\Cache\Exception\ArgumentException;

abstract class CacheDriver implements CacheDriverInterface
{
    /**
     * @var string
     */
    protected const CHECK_KEY = 'key';

    /**
     * @var string
     */
    protected const CHECK_VALUE = 'value';

    /**
     * @var string[] $config
     */
    protected array $config = [];

    /**
     * CacheDriver constructor.
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Ensure the validity of the given cache key.
     *
     * @param mixed $key Key to check.
     * @return void
     * @throws ArgumentException When the key is not valid.
     */
    protected function ensureValidKey($key): void
    {
        if (!is_string($key) || strlen($key) === 0) {
            throw new ArgumentException('A cache key must be a non-empty string.');
        }
    }

    /**
     * Ensure the validity of the argument type and cache keys.
     *
     * @param iterable $iterable The iterable to check.
     * @param string $check Whether to check keys or values.
     * @return void
     * @throws ArgumentException
     */
    protected function ensureValidType($iterable, string $check = self::CHECK_VALUE): void
    {
        if (!is_iterable($iterable)) {
            throw new ArgumentException(sprintf(
                'A cache %s must be either an array or a Traversable.',
                $check === self::CHECK_VALUE ? 'key set' : 'set'
            ));
        }

        foreach ($iterable as $key => $value) {
            if ($check === self::CHECK_VALUE) {
                $this->ensureValidKey($value);
            } else {
                $this->ensureValidKey($key);
            }
        }
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     * @return iterable A list of key value pairs.
     * Cache keys that do not exist or are stale will have $default as value.
     * @throws ArgumentException If $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null): iterable
    {
        $this->ensureValidType($keys);

        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param int|null $ttl Optional. The TTL value of this item. If no value is sent and
     *   the driver supports TTL then the library may set a default value
     *   for it or let the driver take care of that.
     * @return bool True on success and false on failure.
     * @throws ArgumentException If $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $this->ensureValidType($values, self::CHECK_KEY);

        if ($ttl == null) {
            $ttl = $this->ttl();
        }
        foreach ($values as $key => $value) {
            $success = $this->set($key, $value, $ttl);
            if ($success === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     * @return bool True if the items were successfully removed. False if there was an error.
     * @throws ArgumentException If $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys): bool
    {
        $this->ensureValidType($keys);

        foreach ($keys as $key) {
            $result = $this->delete($key);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generates a key for cache backend usage.
     *
     * If the requested key is valid, the engine prefix are applied.
     * Whitespace in keys will be replaced.
     *
     * @param string $key the key passed over
     * @return string Prefixed key with potentially unsafe characters replaced.
     * @throws ArgumentException If key's value is invalid.
     */
    protected function fixKey($key): string
    {
        $this->ensureValidKey($key);

        $key = preg_replace('/[\s]+/', '_', $key);

        return (
                getConfig('cache.prefix', '') .
                ($this->config['prefix'] ?? '')
            ) . $key;
    }

    protected function ttl(): int
    {
        return (int)($this->config['ttl'] ?? $this->config['seconds'] ?? $this->config['expires'] ?? 3600);
    }

    public function forget(string $key)
    {
        return $this->delete($key);
    }

    public function flush()
    {
        return $this->clear();
    }
}
