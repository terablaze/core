<?php

namespace Terablaze\Queue\Connectors;

use Terablaze\Queue\QueueInterface;
use Terablaze\Queue\SyncQueue;

class SyncConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return QueueInterface
     */
    public function connect(array $config): QueueInterface
    {
        return new SyncQueue();
    }
}
