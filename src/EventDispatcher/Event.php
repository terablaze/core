<?php

namespace Terablaze\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Class EventDispatcher
 * @package Terablaze\Events
 */
class Event implements StoppableEventInterface
{
    /**
     * @var bool Whether no further event listeners should be triggered
     */
    private bool $propagationStopped = false;

    /**
     * Is propagation stopped?
     *
     * This will typically only be used by the DispatcherInterface to determine if the
     * previous listener halted propagation.
     *
     * @return bool
     *   True if the EventDispatcher is complete and no further listeners should be called.
     *   False to continue calling listeners.
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
