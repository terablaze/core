<?php

namespace Terablaze\Queue\Connector;

use Terablaze\Queue\NullQueue;
use Terablaze\Queue\QueueInterface;

class NullConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return QueueInterface
     */
    public function connect(array $config): QueueInterface
    {
        return new NullQueue();
    }
}
