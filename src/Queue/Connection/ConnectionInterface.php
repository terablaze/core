<?php

namespace TeraBlaze\Queue\Connection;

use TeraBlaze\Queue\Queues\QueueInterface;

interface ConnectionInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return QueueInterface
     */
    public function connect(array $config);
}
