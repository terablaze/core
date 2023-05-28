<?php

namespace Terablaze\Queue\Connectors;

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
