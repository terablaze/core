<?php

namespace Terablaze\Queue;

interface ClearableQueueInterface
{
    /**
     * Delete all the jobs from the queue.
     *
     * @param  string  $queue
     * @return int
     */
    public function clear($queue);
}
