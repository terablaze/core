<?php

namespace Terablaze\Queue;

use Terablaze\Queue\Jobs\JobInterface;

trait InteractsWithQueue
{
    /**
     * The underlying queue job instance.
     *
     * @var JobInterface
     */
    public $job;

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return $this->job ? $this->job->attempts() : 1;
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        if ($this->job) {
            $this->job->delete();
            return;
        }
    }

    /**
     * Fail the job from the queue.
     *
     * @param  \Throwable|null  $exception
     * @return void
     */
    public function fail($exception = null)
    {
        if ($this->job) {
            $this->job->fail($exception);
        }
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        if ($this->job) {
            $this->job->release($delay);
            return;
        }
    }

    /**
     * Set the base queue job instance.
     *
     * @param  JobInterface  $job
     * @return $this
     */
    public function setJob(JobInterface $job)
    {
        $this->job = $job;

        return $this;
    }
}
