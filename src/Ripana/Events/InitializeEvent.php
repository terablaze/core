<?php

namespace TeraBlaze\Ripana\Events;

use TeraBlaze\EventDispatcher\Event;
use TeraBlaze\Ripana\Database\Connection\ConnectionInterface;
use TeraBlaze\Ripana\Database\Connectors\ConnectorInterface;

class InitializeEvent extends Event
{
    /** @var ConnectionInterface|ConnectorInterface  */
    private $connection;

    /**
     * InitializeEvent constructor.
     * @param ConnectorInterface|ConnectionInterface $connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function setConnection($connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @return ConnectionInterface|ConnectorInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
