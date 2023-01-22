<?php

namespace Terablaze\Bus;

use Carbon\CarbonImmutable;
use Terablaze\Queue\FactoryInterface as QueueFactory;

class BatchFactory
{
    /**
     * The queue factory implementation.
     *
     * @var QueueFactory
     */
    protected $queue;

    /**
     * Create a new batch factory instance.
     *
     * @param  QueueFactory  $queue
     * @return void
     */
    public function __construct(QueueFactory $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Create a new batch instance.
     *
     * @param  \Terablaze\Bus\BatchRepositoryInterface  $repository
     * @param  string  $id
     * @param  string  $name
     * @param  int  $totalJobs
     * @param  int  $pendingJobs
     * @param  int  $failedJobs
     * @param  array  $failedJobIds
     * @param  array  $options
     * @param  \Carbon\CarbonImmutable  $createdAt
     * @param  \Carbon\CarbonImmutable|null  $cancelledAt
     * @param  \Carbon\CarbonImmutable|null  $finishedAt
     * @return \Terablaze\Bus\Batch
     */
    public function make(BatchRepositoryInterface $repository,
                         string                   $id,
                         string                   $name,
                         int                      $totalJobs,
                         int                      $pendingJobs,
                         int                      $failedJobs,
                         array                    $failedJobIds,
                         array                    $options,
                         CarbonImmutable          $createdAt,
                         ?CarbonImmutable         $cancelledAt,
                         ?CarbonImmutable         $finishedAt)
    {
        return new Batch(
            $this->queue,
            $repository,
            $id,
            $name,
            $totalJobs,
            $pendingJobs,
            $failedJobs,
            $failedJobIds,
            $options,
            $createdAt,
            $cancelledAt,
            $finishedAt
        );
    }
}
