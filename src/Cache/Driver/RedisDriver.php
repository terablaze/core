<?php

namespace Terablaze\Cache\Driver;

use Memcached;
use ReflectionException;
use Terablaze\Cache\Exception\ServiceException;
use Terablaze\Cache\Lock\LockInterface;
use Terablaze\Cache\Lock\MemcachedLock;
use Terablaze\Cache\Lock\PhpRedisLock;
use Terablaze\Cache\Lock\RedisLock;
use Terablaze\Cache\LockProviderInterface;
use Terablaze\Container\ContainerInterface;
use Terablaze\Redis\Connections\ConnectionInterface;
use Terablaze\Redis\Connections\PhpRedisConnection;
use Terablaze\Redis\RedisManager;

class RedisDriver extends CacheDriver implements CacheDriverInterface
{
    /**
     * The Redis factory implementation.
     *
     * @var RedisManager
     */
    protected $redis;

    /**
     * The Redis connection instance that should be used to manage locks.
     *
     * @var string
     */
    protected $connection;

    /**
     * The name of the connection that should be used for locks.
     *
     * @var string
     */
    protected $lockConnection;

    public function __construct(protected readonly ContainerInterface $container, array $config)
    {
        parent::__construct($config);
        $this->redis = $this->container->get(RedisManager::class);
        $this->setConnection($config['connection'] ?? "default");
    }

    public function has($key): bool
    {
        $key = $this->fixKey($key);
        return $this->connection()->get($key) !== false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->fixKey($key);
        $value = $this->connection()->get($key);

        return ! is_null($value) ? $this->unserialize($value) : $default;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $results = [];

        $values = $this->connection()->mget(array_map(function ($key) {
            return $this->fixKey($key);
        }, $keys));

        foreach ($values as $index => $value) {
            $results[$keys[$index]] = ! is_null($value) ? $this->unserialize($value) : null;
        }

        return $results ?? $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $key = $this->fixKey($key);
        if (!is_int($ttl)) {
            $ttl = $this->ttl();
        }
        return (bool) $this->connection()->setex(
            $key, (int) max(1, $ttl), $this->serialize($value)
        );
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $this->connection()->multi();

        $manyResult = null;

        foreach ($values as $key => $value) {
            $result = $this->set($key, $value, $ttl);

            $manyResult = is_null($manyResult) ? $result : $result && $manyResult;
        }

        $this->connection()->exec();

        return $manyResult ?: false;
    }

    public function delete($key): bool
    {
        $key = $this->fixKey($key);
        return (bool) $this->connection()->del($key);
    }

    /**
     * @inheritDoc
     */
    public function increment($key, $incrementBy = 1): bool|int
    {
        $key = $this->fixKey($key);
        return $this->connection()->incrby($key, $incrementBy);
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $decrementBy = 1): bool|int
    {
        $key = $this->fixKey($key);
        return $this->connection()->decrby($key, $decrementBy);
    }

    public function clear(): bool
    {
        $this->connection()->flushdb();

        return true;
    }

    /**
     * Get a lock instance.
     *
     * @param string $name
     * @param int $seconds
     * @param null $owner
     * @return LockInterface
     * @throws ReflectionException
     */
    public function lock($name, $seconds = 0, $owner = null): LockInterface
    {
        $lockName = $this->fixKey($name);

        $lockConnection = $this->lockConnection();

        if ($lockConnection instanceof PhpRedisConnection) {
            return new PhpRedisLock($lockConnection, $lockName, $seconds, $owner);
        }

        return new RedisLock($lockConnection, $lockName, $seconds, $owner);
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

    /**
     * Get the Redis connection instance.
     *
     * @return ConnectionInterface
     */
    public function connection()
    {
        return $this->redis->connection($this->connection);
    }

    /**
     * Get the Redis connection instance that should be used to manage locks.
     *
     * @return \Terablaze\Redis\Connections\ConnectionInterface
     */
    public function lockConnection()
    {
        return $this->redis->connection($this->lockConnection ?? $this->connection);
    }

    /**
     * Specify the name of the connection that should be used to store data.
     *
     * @param  string  $connection
     * @return void
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Specify the name of the connection that should be used to manage locks.
     *
     * @param  string  $connection
     * @return $this
     */
    public function setLockConnection($connection)
    {
        $this->lockConnection = $connection;

        return $this;
    }

    /**
     * Get the Redis database instance.
     *
     * @return RedisManager
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * Serialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return is_numeric($value) && ! in_array($value, [INF, -INF]) && ! is_nan($value) ? $value : serialize($value);
    }

    /**
     * Unserialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        return is_numeric($value) ? $value : unserialize($value);
    }
}
