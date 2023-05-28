<?php

namespace Terablaze\Queue\Events;

use Terablaze\Queue\Jobs\JobInterface;

class JobPopped
{
    /**
     * The connection name.
     *
     * @var string
     */
    public $connectionName;

    /**
     * The job instance.
     *
     * @var JobInterface|null
     */
    public $job;

    /**
     * Create a new event instance.
     *
     * @param  string  $connectionName
     * @param  JobInterface|null  $job
     * @return void
     */
    public function __construct($connectionName, $job)
    {
        $this->connectionName = $connectionName;
        $this->job = $job;
    }
}
