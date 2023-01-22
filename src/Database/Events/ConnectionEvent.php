<?php

namespace Terablaze\Database\Events;

use Terablaze\Database\Connection\ConnectionInterface;

abstract class ConnectionEvent
{
    /**
     * The name of the connection.
     *
     * @var string
     */
    public $connectionName;

    /**
     * The database connection instance.
     *
     * @var ConnectionInterface
     */
    public $connection;

    /**
     * Create a new event instance.
     *
     * @param  ConnectionInterface  $connection
     * @return void
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->connectionName = $connection->getName();
    }
}
