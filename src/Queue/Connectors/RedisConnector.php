<?php

namespace Terablaze\Queue\Connectors;

use Terablaze\Container\ContainerInterface;
use Terablaze\Queue\QueueInterface;
use Terablaze\Redis\RedisManager;
use Terablaze\Queue\RedisQueue;

class RedisConnector implements ConnectorInterface
{
    /**
     * The Redis database instance.
     *
     * @var RedisManager
     */
    protected $redis;


    public function __construct(private ContainerInterface $container)
    {
        $this->redis = $this->container->get(RedisManager::class);
    }

    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return QueueInterface
     */
    public function connect(array $config): QueueInterface
    {
        return new RedisQueue(
            $this->redis, $config['queue'],
            $config['connection'] ?? "default",
            $config['retry_after'] ?? 60,
            $config['block_for'] ?? null,
            $config['after_commit'] ?? null,
            $config['migration_batch_size'] ?? -1
        );
    }
}
