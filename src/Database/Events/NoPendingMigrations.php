<?php

namespace Terablaze\Database\Events;

use Terablaze\EventDispatcher\Event;

class NoPendingMigrations extends Event
{
    /**
     * The migration method that was called.
     *
     * @var string
     */
    public string $method;

    /**
     * Create a new event instance.
     *
     * @param string $method
     * @return void
     */
    public function __construct(string $method)
    {
        $this->method = $method;
    }
}
