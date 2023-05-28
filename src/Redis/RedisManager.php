<?php

namespace Terablaze\Redis;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Terablaze\Container\Container;
use Terablaze\Container\ContainerInterface;
use Terablaze\Redis\Connections\ConnectionInterface;
use Terablaze\Redis\Connectors\PhpRedisConnector;
use Terablaze\Redis\Connectors\PredisConnector;
use Terablaze\Support\ArrayMethods;
use InvalidArgumentException;
use Terablaze\Support\ConfigurationUrlParser;

class RedisManager
{
    /**
     * The application instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The name of the default driver.
     *
     * @var string
     */
    protected $driver;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The Redis server configurations.
     *
     * @var array
     */
    protected $config;

    /**
     * The Redis connections.
     *
     * @var mixed
     */
    protected $connections;

    /**
     * Indicates whether event dispatcher is set on connections.
     *
     * @var bool
     */
    protected $events = false;

    /**
     * Create a new Redis manager instance.
     *
     * @param  Container $container
     * @param  string  $driver
     * @param  array  $config
     * @return void
     */
    public function __construct(ContainerInterface $container, $driver, array $config)
    {
        $this->container = $container;
        $this->driver = $driver;
        $this->config = $config;
    }

    /**
     * Get a Redis connection by name.
     *
     * @param  string|null  $name
     * @return \Terablaze\Redis\Connections\ConnectionInterface
     */
    public function connection($name = null)
    {
        $name = $name ?: 'default';

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->connections[$name] = $this->configure(
            $this->resolve($name), $name
        );
    }

    /**
     * Resolve the given connection by name.
     *
     * @param  string|null  $name
     * @return \Terablaze\Redis\Connections\ConnectionInterface
     *
     * @throws \InvalidArgumentException
     */
    public function resolve($name = null)
    {
        $name = $name ?: 'default';

        $options = $this->config['options'] ?? [];

        if (isset($this->config[$name])) {
            return $this->connector()->connect(
                $this->parseConnectionConfiguration($this->config[$name]),
                array_merge(ArrayMethods::except($options, 'parameters'), ['parameters' => ArrayMethods::get($options, 'parameters.'.$name, ArrayMethods::get($options, 'parameters', []))])
            );
        }

        if (isset($this->config['clusters'][$name])) {
            return $this->resolveCluster($name);
        }

        throw new InvalidArgumentException("Redis connection [{$name}] not configured.");
    }

    /**
     * Resolve the given cluster connection by name.
     *
     * @param  string  $name
     * @return \Terablaze\Redis\Connections\ConnectionInterface
     */
    protected function resolveCluster($name)
    {
        return $this->connector()->connectToCluster(
            array_map(function ($config) {
                return $this->parseConnectionConfiguration($config);
            }, $this->config['clusters'][$name]),
            $this->config['clusters']['options'] ?? [],
            $this->config['options'] ?? []
        );
    }

    /**
     * Configure the given connection to prepare it for commands.
     *
     * @param  \Terablaze\Redis\Connections\ConnectionInterface  $connection
     * @param  string  $name
     * @return \Terablaze\Redis\Connections\ConnectionInterface
     */
    protected function configure(ConnectionInterface $connection, $name)
    {
        $connection->setName($name);

        if ($this->events && $this->container->has(EventDispatcherInterface::class)) {
            $connection->setEventDispatcher($this->container->make(EventDispatcherInterface::class));
        }

        return $connection;
    }

    /**
     * Get the connector instance for the current driver.
     *
     * @return \Terablaze\Redis\Connectors\ConnectorInterface|null
     */
    protected function connector()
    {
        $customCreator = $this->customCreators[$this->driver] ?? null;

        if ($customCreator) {
            return $customCreator();
        }

        return match ($this->driver) {
            'predis' => new PredisConnector,
            'phpredis' => new PhpRedisConnector,
            default => null,
        };
    }

    /**
     * Parse the Redis connection configuration.
     *
     * @param  mixed  $config
     * @return array
     */
    protected function parseConnectionConfiguration($config)
    {
        $parsed = (new ConfigurationUrlParser())->parseConfiguration($config);

        $driver = strtolower($parsed['driver'] ?? '');

        if (in_array($driver, ['tcp', 'tls'])) {
            $parsed['scheme'] = $driver;
        }

        return array_filter($parsed, function ($key) {
            return ! in_array($key, ['driver'], true);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Return all of the created connections.
     *
     * @return array
     */
    public function connections()
    {
        return $this->connections;
    }

    /**
     * Enable the firing of Redis command events.
     *
     * @return void
     */
    public function enableEvents()
    {
        $this->events = true;
    }

    /**
     * Disable the firing of Redis command events.
     *
     * @return void
     */
    public function disableEvents()
    {
        $this->events = false;
    }

    /**
     * Set the default driver.
     *
     * @param  string  $driver
     * @return void
     */
    public function setDriver($driver)
    {
        $this->driver = $driver;
    }

    /**
     * Disconnect the given connection and remove from local cache.
     *
     * @param  string|null  $name
     * @return void
     */
    public function purge($name = null)
    {
        $name = $name ?: 'default';

        unset($this->connections[$name]);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback->bindTo($this, $this);

        return $this;
    }

    /**
     * Pass methods onto the default Redis connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connection()->{$method}(...$parameters);
    }
}
