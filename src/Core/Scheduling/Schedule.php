<?php

namespace Terablaze\Core\Scheduling;

use Closure;
use DateTimeInterface;
use Terablaze\Bus\Dispatcher;
use Terablaze\Bus\UniqueLock;
use Terablaze\Cache\Driver\CacheDriverInterface;
use Terablaze\Collection\ArrayCollection;
use Terablaze\Console\Application;
use Terablaze\Container\Container;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\Queue\CallQueuedClosure;
use Terablaze\Queue\ShouldBeUnique;
use Terablaze\Support\ProcessUtils;
use Terablaze\Support\StringMethods;
use Terablaze\Support\Traits\Macroable;
use RuntimeException;

class Schedule
{
    use Macroable;

    public const SUNDAY = 0;

    public const MONDAY = 1;

    public const TUESDAY = 2;

    public const WEDNESDAY = 3;

    public const THURSDAY = 4;

    public const FRIDAY = 5;

    public const SATURDAY = 6;

    /**
     * All the events on the schedule.
     *
     * @var \Terablaze\Core\Scheduling\Event[]
     */
    protected $events = [];

    /**
     * The event mutex implementation.
     *
     * @var \Terablaze\Core\Scheduling\EventMutexInterface
     */
    protected $eventMutex;

    /**
     * The scheduling mutex implementation.
     *
     * @var \Terablaze\Core\Scheduling\SchedulingMutexInterface
     */
    protected $schedulingMutex;

    /**
     * The timezone the date should be evaluated on.
     *
     * @var \DateTimeZone|string
     */
    protected $timezone;

    /**
     * The job dispatcher implementation.
     *
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * Create a new schedule instance.
     *
     * @param  \DateTimeZone|string|null  $timezone
     * @return void
     *
     * @throws \RuntimeException
     */
    public function __construct($timezone = null)
    {
        $this->timezone = $timezone;

        if (! class_exists(Container::class)) {
            throw new RuntimeException(
                'A container implementation is required to use the scheduler.'
            );
        }

        $container = Container::getContainer();

        $this->eventMutex = $container->has(EventMutexInterface::class)
            ? $container->get(EventMutexInterface::class)
            : $container->make(CacheEventMutex::class, ['alias' => [EventMutexInterface::class]]);

        $this->schedulingMutex = $container->has(SchedulingMutexInterface::class)
            ? $container->get(SchedulingMutexInterface::class)
            : $container->make(CacheSchedulingMutex::class, ['alias' => [SchedulingMutexInterface::class]]);
    }

    /**
     * Add a new callback event to the schedule.
     *
     * @param  string|callable  $callback
     * @param  array  $parameters
     * @return \Terablaze\Core\Scheduling\CallbackEvent
     */
    public function call($callback, array $parameters = [])
    {
        $this->events[] = $event = new CallbackEvent(
            $this->eventMutex, $callback, $parameters, $this->timezone
        );

        return $event;
    }

    /**
     * Add a new Artisan command event to the schedule.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return \Terablaze\Core\Scheduling\Event
     */
    public function command($command, array $parameters = [])
    {
        if (class_exists($command)) {
            $command = Container::getContainer()->make($command);

            return $this->exec(
                Application::formatCommandString($command->getName()), $parameters,
            )->description($command->getDescription());
        }

        return $this->exec(
            Application::formatCommandString($command), $parameters
        );
    }

    /**
     * Add a new job callback event to the schedule.
     *
     * @param  object|string  $job
     * @param  string|null  $queue
     * @param  string|null  $connection
     * @return \Terablaze\Core\Scheduling\CallbackEvent
     */
    public function job($job, $queue = null, $connection = null)
    {
        return $this->call(function () use ($job, $queue, $connection) {
            $job = is_string($job) ? Container::getContainer()->make($job) : $job;

            if ($job instanceof \Terablaze\Queue\ShouldQueue) {
                $this->dispatchToQueue($job, $queue ?? $job->queue, $connection ?? $job->connection);
            } else {
                $this->dispatchNow($job);
            }
        })->name(is_string($job) ? $job : get_class($job));
    }

    /**
     * Dispatch the given job to the queue.
     *
     * @param  object  $job
     * @param  string|null  $queue
     * @param  string|null  $connection
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function dispatchToQueue($job, $queue, $connection)
    {
        if ($job instanceof Closure) {
            if (! class_exists(CallQueuedClosure::class)) {
                throw new RuntimeException(
                    'To enable support for closure jobs, please install the illuminate/queue package.'
                );
            }

            $job = CallQueuedClosure::create($job);
        }

        if ($job instanceof ShouldBeUnique) {
            $this->dispatchUniqueJobToQueue($job, $queue, $connection);
            return;
        }

        $this->getDispatcher()->dispatch(
            $job->onConnection($connection)->onQueue($queue)
        );
    }

    /**
     * Dispatch the given unique job to the queue.
     *
     * @param  object  $job
     * @param  string|null  $queue
     * @param  string|null  $connection
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function dispatchUniqueJobToQueue($job, $queue, $connection)
    {
        if (! Container::getContainer()->has(CacheDriverInterface::class)) {
            throw new RuntimeException('Cache driver not available. Scheduling unique jobs not supported.');
        }

        if (! (new UniqueLock(Container::getContainer()->make(CacheDriverInterface::class)))->acquire($job)) {
            return;
        }

        $this->getDispatcher()->dispatch(
            $job->onConnection($connection)->onQueue($queue)
        );
    }

    /**
     * Dispatch the given job right now.
     *
     * @param  object  $job
     * @return void
     */
    protected function dispatchNow($job)
    {
        $this->getDispatcher()->dispatchNow($job);
    }

    /**
     * Add a new command event to the schedule.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return \Terablaze\Core\Scheduling\Event
     */
    public function exec($command, array $parameters = [])
    {
        if (count($parameters)) {
            $command .= ' '.$this->compileParameters($parameters);
        }

        $this->events[] = $event = new Event($this->eventMutex, $command, $this->timezone);

        return $event;
    }

    /**
     * Compile parameters for a command.
     *
     * @param  array  $parameters
     * @return string
     */
    protected function compileParameters(array $parameters)
    {
        return (new ArrayCollection($parameters))->map(function ($value, $key) {
            if (is_array($value)) {
                return $this->compileArrayInput($key, $value);
            }

            if (! is_numeric($value) && ! preg_match('/^(-.$|--.*)/i', $value)) {
                $value = ProcessUtils::escapeArgument($value);
            }

            return is_numeric($key) ? $value : "{$key}={$value}";
        })->implode(' ');
    }

    /**
     * Compile array input for a command.
     *
     * @param  string|int  $key
     * @param  array  $value
     * @return string
     */
    public function compileArrayInput($key, $value)
    {
        $value = (new ArrayCollection($value))->map(function ($value) {
            return ProcessUtils::escapeArgument($value);
        });

        if (str_starts_with($key, '--')) {
            $value = $value->map(function ($value) use ($key) {
                return "{$key}={$value}";
            });
        } elseif (str_starts_with($key, '-')) {
            $value = $value->map(function ($value) use ($key) {
                return "{$key} {$value}";
            });
        }

        return $value->implode(' ');
    }

    /**
     * Determine if the server is allowed to run this event.
     *
     * @param  \Terablaze\Core\Scheduling\Event  $event
     * @param  \DateTimeInterface  $time
     * @return bool
     */
    public function serverShouldRun(Event $event, DateTimeInterface $time)
    {
        return $this->schedulingMutex->create($event, $time);
    }

    /**
     * Get all the events on the schedule that are due.
     *
     * @param KernelInterface $kernel
     * @return ArrayCollection
     * @throws \Terablaze\Collection\Exceptions\InvalidTypeException
     */
    public function dueEvents(KernelInterface $kernel)
    {
        return (new ArrayCollection($this->events))->filter(function (Event $event) use ($kernel) {
            return $event->isDue($kernel);
        });
    }

    /**
     * Get all the events on the schedule.
     *
     * @return \Terablaze\Core\Scheduling\Event[]
     */
    public function events()
    {
        return $this->events;
    }

    /**
     * Specify the cache store that should be used to store mutexes.
     *
     * @param  string  $store
     * @return $this
     */
    public function useCache($store)
    {
        if ($this->eventMutex instanceof CacheAwareInterface) {
            $this->eventMutex->useStore($store);
        }

        if ($this->schedulingMutex instanceof CacheAwareInterface) {
            $this->schedulingMutex->useStore($store);
        }

        return $this;
    }

    /**
     * Get the job dispatcher, if available.
     *
     * @return Dispatcher
     *
     * @throws \RuntimeException
     */
    protected function getDispatcher()
    {
        if ($this->dispatcher === null) {
            try {
                $this->dispatcher = Container::getContainer()->make(Dispatcher::class);
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    'Unable to resolve the dispatcher from the service container. '
                    . 'Please bind it or install the illuminate/bus package.',
                    is_int($e->getCode()) ? $e->getCode() : 0, $e
                );
            }
        }

        return $this->dispatcher;
    }
}
