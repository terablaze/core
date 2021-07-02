<?php

namespace TeraBlaze\Ripana\Events;

use TeraBlaze\EventDispatcher\Event;
use TeraBlaze\Ripana\Database\Connectors\ConnectorInterface;

class InitializeEvent extends Event
{
    private ConnectorInterface $connection;

    /**
     * InitializeEvent constructor.
     * @param ConnectorInterface $connection
     */
    public function __construct(ConnectorInterface $connection)
    {
        $this->connection = $connection;
    }

    public function setConnection(ConnectorInterface $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection(): ConnectorInterface
    {
        return $this->connection;
    }
}
