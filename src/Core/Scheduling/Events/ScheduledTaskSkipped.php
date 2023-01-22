<?php

namespace Terablaze\Core\Scheduling\Events;

use Terablaze\Core\Scheduling\Event;

class ScheduledTaskSkipped
{
    /**
     * The scheduled event being run.
     *
     * @var \Terablaze\Core\Scheduling\Event
     */
    public $task;

    /**
     * Create a new event instance.
     *
     * @param  \Terablaze\Core\Scheduling\Event  $task
     * @return void
     */
    public function __construct(Event $task)
    {
        $this->task = $task;
    }
}
