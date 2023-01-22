<?php

namespace Terablaze\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Terablaze\Support\ArrayMethods;

/**
 * Class Dispatcher
 * @package Terablaze\Events
 */
class Dispatcher implements EventDispatcherInterface
{
    /**
     * @var ListenerProvider
     */
    private $listenerProvider;

    /**
     * EventDispatcher constructor.
     * @param ListenerProvider $listenerProvider
     */
    public function __construct(ListenerProvider $listenerProvider)
    {
        $this->listenerProvider = $listenerProvider;
    }

    /**
     * @param object $event
     * @return object
     */
    public function dispatch(object $event): object
    {
        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            return $event;
        }
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        return $event;
    }

    public function getListenerProvider(): ListenerProviderInterface
    {
        return $this->listenerProvider;
    }

    public function listen(string $event, $listeners)
    {
        $listeners = is_callable($listeners) ? [$listeners] : ArrayMethods::wrap($listeners);
        $this->listenerProvider->addListeners($event, $listeners);
    }
}
