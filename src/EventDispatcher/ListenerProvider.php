<?php

namespace Terablaze\EventDispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;

use function is_callable;
use function is_int;

/**
 * Class ListenerProvider
 * @package Terablaze\Events
 */
class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<int, array<string, callable[]>> */
    private array $listeners = [];
    /** @var array<int, array<string, callable[]>> */
    private array $wildcardListeners = [];

    /**
     * @param object $event
     *   An event for which to return the relevant listeners.
     * @return callable[]
     *   An iterable (array, iterator, or generator) of callables.  Each
     *   callable MUST be type-compatible with $event.
     */
    public function getListenersForEvent(object $event): iterable
    {
        $priorities = array_keys($this->listeners);
        usort($priorities, function ($a, $b) {
            return $b <=> $a;
        });
        foreach ($priorities as $priority) {
            foreach ($this->listeners[$priority] as $eventName => $listeners) {
                if ($event instanceof $eventName) {
                    $listeners = array_merge($listeners, $this->wildcardListeners);
                    foreach ($listeners as $listener) {
                        yield $listener;
                    }
                }
            }
        }
    }

    /**
     * @param string $eventType
     * @param callable $listener
     * @param int $priority
     * @return $this
     */
    public function addListener(string $eventType, callable $listener, int $priority = 0): self
    {
        if ($eventType == "*") {
            $this->wildcardListeners[$priority] = $listener;
            return $this;
        }
        if (
            isset($this->listeners[$priority][$eventType])
            && in_array($listener, $this->listeners[$priority][$eventType], true)
        ) {
            // Duplicate detected
            return $this;
        }
        $this->listeners[$priority][$eventType][] = $listener;
        return $this;
    }

    /**
     * @param string $eventType
     * @param array<int, callable> $listeners
     * @return $this
     */
    public function addListeners(string $eventType, array $listeners): self
    {
        foreach ($listeners as $listener) {
            if (!is_callable($listener)) {
                continue;
            }
            $this->addListener($eventType, $listener);
        }
        return $this;
    }

    /**
     * @param string $eventType
     */
    public function clearListeners(string $eventType): void
    {
        foreach ($this->listeners as $priority => $listener) {
            if (array_key_exists($eventType, $listener)) {
                unset($this->listeners[$priority][$eventType]);
                unset($listener[$eventType]);
            }
        }
    }

    public function clearAllListeners(): void
    {
        unset($this->listeners);
        $this->listeners = [];
    }

    /**
     * @return callable[][][]
     */
    public function getAllListeners(): array
    {
        return $this->listeners;
    }
}
