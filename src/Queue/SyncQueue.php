<?php

namespace Terablaze\Queue;

use Psr\EventDispatcher\EventDispatcherInterface;
use Terablaze\Queue\Events\JobExceptionOccurred;
use Terablaze\Queue\Events\JobProcessed;
use Terablaze\Queue\Events\JobProcessing;
use Terablaze\Queue\Jobs\JobInterface;
use Terablaze\Queue\Jobs\SyncJob;
use Terablaze\Queue\QueueInterface;
use Throwable;

class SyncQueue extends Queue implements QueueInterface
{
    /**
     * Get the size of the queue.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function size($queue = null): int
    {
        return 0;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     *
     * @throws \Throwable
     */
    public function push($job, $data = '', $queue = null)
    {
        $queueJob = $this->resolveJob($this->createPayload($job, $queue, $data), $queue);

        try {
            $this->raiseBeforeJobEvent($queueJob);

            $queueJob->fire();

            $this->raiseAfterJobEvent($queueJob);
        } catch (Throwable $e) {
            $this->handleException($queueJob, $e);
        }

        return 0;
    }

    /**
     * Resolve a Sync job instance.
     *
     * @param  string  $payload
     * @param  string  $queue
     * @return SyncJob
     */
    protected function resolveJob($payload, $queue)
    {
        return new SyncJob($this->container, $payload, $this->connectionName, $queue);
    }

    /**
     * Raise the before queue job event.
     *
     * @param  JobInterface  $job
     * @return void
     */
    protected function raiseBeforeJobEvent(JobInterface $job)
    {
        if ($this->container->has(EventDispatcherInterface::class)) {
            $this->container->get(EventDispatcherInterface::class)
                ->dispatch(new JobProcessing($this->connectionName, $job));
        }
    }

    /**
     * Raise the after queue job event.
     *
     * @param  JobInterface  $job
     * @return void
     */
    protected function raiseAfterJobEvent(JobInterface $job)
    {
        if ($this->container->has(EventDispatcherInterface::class)) {
            $this->container->get(EventDispatcherInterface::class)
                ->dispatch(new JobProcessed($this->connectionName, $job));
        }
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param  JobInterface  $job
     * @param  \Throwable  $e
     * @return void
     */
    protected function raiseExceptionOccurredJobEvent(JobInterface $job, Throwable $e)
    {
        if ($this->container->has(EventDispatcherInterface::class)) {
            $this->container->get(EventDispatcherInterface::class)
                ->dispatch(new JobExceptionOccurred($this->connectionName, $job, $e));
        }
    }

    /**
     * Handle an exception that occurred while processing a job.
     *
     * @param  JobInterface  $queueJob
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    protected function handleException(JobInterface $queueJob, Throwable $e)
    {
        $this->raiseExceptionOccurredJobEvent($queueJob, $e);

        $queueJob->fail($e);

        throw $e;
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        //
    }

    /**
     * Push a new job onto the queue after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->push($job, $data, $queue);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string|null  $queue
     * @return JobInterface|null
     */
    public function pop($queue = null)
    {
        //
    }
}
