<?php

namespace Terablaze\Queue;

interface QueueManagerInterface
{
    /**
     * Resolve a queue connection instance.
     *
     * @param  string|null  $name
     * @return \Terablaze\Queue\Queue
     */
    public function connection($name = null);
}
