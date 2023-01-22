<?php

namespace Terablaze\Core\Scheduling;

use DateTimeInterface;

interface SchedulingMutexInterface
{
    /**
     * Attempt to obtain a scheduling mutex for the given event.
     *
     * @param  \Terablaze\Core\Scheduling\Event  $event
     * @param  \DateTimeInterface  $time
     * @return bool
     */
    public function create(Event $event, DateTimeInterface $time);

    /**
     * Determine if a scheduling mutex exists for the given event.
     *
     * @param  \Terablaze\Core\Scheduling\Event  $event
     * @param  \DateTimeInterface  $time
     * @return bool
     */
    public function exists(Event $event, DateTimeInterface $time);
}
