<?php

namespace Terablaze\Queue\Connector;

use Terablaze\Container\ContainerInterface;
use Terablaze\Database\Connection\ConnectionInterface as DatabaseConnectionInterface;
use Terablaze\Queue\DatabaseQueue;
use Terablaze\Queue\QueueInterface;

class DatabaseConnector implements ConnectorInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function connect(array $config): QueueInterface
    {
        $databaseConnection = $this->container->has("database.connection." . ($config['connection'] ?? "")) ?
            $this->container->get("database.connection." . ($config['connection'] ?? "")) :
            $this->container->get(DatabaseConnectionInterface::class);

        $connection = new DatabaseQueue(
            $databaseConnection,
            $config['table'],
            $config['queue'],
            $this->config['retry_after'] ?? 60,
            $this->config['after_commit'] ?? null
        );

        $connection->setContainer($this->container);

        return $connection;
    }
}