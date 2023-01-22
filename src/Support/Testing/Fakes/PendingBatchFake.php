<?php

namespace Terablaze\Support\Testing\Fakes;

use Terablaze\Bus\Batch;
use Terablaze\Bus\PendingBatch;
use Terablaze\Collection\CollectionInterface;

class PendingBatchFake extends PendingBatch
{
    /**
     * The fake bus instance.
     *
     * @var \Terablaze\Support\Testing\Fakes\BusFake
     */
    protected $bus;

    /**
     * Create a new pending batch instance.
     *
     * @param  \Terablaze\Support\Testing\Fakes\BusFake  $bus
     * @param  CollectionInterface  $jobs
     * @return void
     */
    public function __construct(BusFake $bus, CollectionInterface $jobs)
    {
        $this->bus = $bus;
        $this->jobs = $jobs;
    }

    /**
     * Dispatch the batch.
     *
     * @return Batch
     */
    public function dispatch()
    {
        return $this->bus->recordPendingBatch($this);
    }

    /**
     * Dispatch the batch after the response is sent to the browser.
     *
     * @return Batch
     */
    public function dispatchAfterResponse()
    {
        return $this->bus->recordPendingBatch($this);
    }
}
