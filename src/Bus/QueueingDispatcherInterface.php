<?php

namespace Terablaze\Bus;

use Terablaze\Collection\CollectionInterface;

interface QueueingDispatcherInterface extends DispatcherInterface
{
    /**
     * Attempt to find the batch with the given ID.
     *
     * @param  string  $batchId
     * @return Batch|null
     */
    public function findBatch(string $batchId);

    /**
     * Create a new batch of queueable jobs.
     *
     * @param  CollectionInterface|array  $jobs
     * @return PendingBatch
     */
    public function batch($jobs);

    /**
     * Dispatch a command to its appropriate handler behind a queue.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatchToQueue($command);
}
