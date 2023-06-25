<?php

namespace Terablaze\Support\Testing\Fakes;

use Carbon\CarbonImmutable;
use Terablaze\Bus\UpdatedBatchJobCounts;
use Carbon\Carbon;
use Terablaze\Bus\Batch;
use Terablaze\Collection\CollectionInterface;

class BatchFake extends Batch
{
    /**
     * The jobs that have been added to the batch.
     *
     * @var array
     */
    public $added = [];

    /**
     * Indicates if the batch has been deleted.
     *
     * @var bool
     */
    public $deleted = false;

    /**
     * Create a new batch instance.
     *
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
     * @return void
     */
    public function __construct(string $id,
                                string $name,
                                int $totalJobs,
                                int $pendingJobs,
                                int $failedJobs,
                                array $failedJobIds,
                                array $options,
                                CarbonImmutable $createdAt,
                                ?CarbonImmutable $cancelledAt = null,
                                ?CarbonImmutable $finishedAt = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->totalJobs = $totalJobs;
        $this->pendingJobs = $pendingJobs;
        $this->failedJobs = $failedJobs;
        $this->failedJobIds = $failedJobIds;
        $this->options = $options;
        $this->createdAt = $createdAt;
        $this->cancelledAt = $cancelledAt;
        $this->finishedAt = $finishedAt;
    }

    /**
     * Get a fresh instance of the batch represented by this ID.
     *
     * @return self
     */
    public function fresh()
    {
        return $this;
    }

    /**
     * Add additional jobs to the batch.
     *
     * @param  CollectionInterface|object|array  $jobs
     * @return self
     */
    public function add($jobs)
    {
        $this->added[] = array_merge($this->added, $jobs);

        return $this;
    }

    /**
     * Record that a job within the batch finished successfully, executing any callbacks if necessary.
     *
     * @param  string  $jobId
     * @return void
     */
    public function recordSuccessfulJob(string $jobId)
    {
        //
    }

    /**
     * Decrement the pending jobs for the batch.
     *
     * @param  string  $jobId
     * @return \Terablaze\Bus\UpdatedBatchJobCounts
     */
    public function decrementPendingJobs(string $jobId)
    {
        //
    }

    /**
     * Record that a job within the batch failed to finish successfully, executing any callbacks if necessary.
     *
     * @param  string  $jobId
     * @param  \Throwable  $e
     * @return void
     */
    public function recordFailedJob(string $jobId, $e)
    {
        //
    }

    /**
     * Increment the failed jobs for the batch.
     *
     * @param  string  $jobId
     * @return \Terablaze\Bus\UpdatedBatchJobCounts
     */
    public function incrementFailedJobs(string $jobId)
    {
        return new UpdatedBatchJobCounts;
    }

    /**
     * Cancel the batch.
     *
     * @return void
     */
    public function cancel()
    {
        $this->cancelledAt = Carbon::now();
    }

    /**
     * Delete the batch from storage.
     *
     * @return void
     */
    public function delete()
    {
        $this->deleted = true;
    }

    /**
     * Determine if the batch has been deleted.
     *
     * @return bool
     */
    public function deleted()
    {
        return $this->deleted;
    }
}