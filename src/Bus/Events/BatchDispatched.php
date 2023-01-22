<?php

namespace Terablaze\Bus\Events;

use Terablaze\Bus\Batch;

class BatchDispatched
{
    /**
     * The batch instance.
     *
     * @var \Terablaze\Bus\Batch
     */
    public $batch;

    /**
     * Create a new event instance.
     *
     * @param  \Terablaze\Bus\Batch  $batch
     * @return void
     */
    public function __construct(Batch $batch)
    {
        $this->batch = $batch;
    }
}
