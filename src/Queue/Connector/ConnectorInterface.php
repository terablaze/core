<?php

namespace Terablaze\Queue\Connector;

use Terablaze\Queue\QueueInterface;

interface ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return QueueInterface
     */
    public function connect(array $config): QueueInterface;
}
