<?php

namespace Terablaze\Queue;

use DateTimeInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Terablaze\Collection\ArrayCollection;
use Terablaze\Container\Container;
use Terablaze\Container\ContainerInterface;
use Terablaze\Encryption\Encrypter;
use Terablaze\EventDispatcher\Dispatcher;
use Terablaze\Queue\Events\JobQueued;
use Terablaze\Queue\Exception\InvalidPayloadException;
use Terablaze\Queue\Jobs\JobInterface;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\StringMethods;
use Terablaze\Support\Traits\TimeAware;

abstract class Queue implements QueueInterface
{
    use TimeAware;

    /**
     * The IoC container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * The connection name for the queue.
     *
     * @var string
     */
    protected $connectionName;

    /**
     * Indicates that jobs should be dispatched after all database transactions have committed.
     *
     * @var bool
     * @return $this
     */
    protected $dispatchAfterCommit;

    /**
     * The create payload callbacks.
     *
     * @var callable[]
     */
    protected static $createPayloadCallbacks = [];

    /**
     * Push a new job onto the queue.
     *
     * @param  string  $queue
     * @param  string  $job
     * @param  mixed  $data
     * @return mixed
     */
    public function pushOn($queue, $job, $data = '')
    {
        return $this->push($job, $data, $queue);
    }

    /**
     * Push a new job onto a specific queue after (n) seconds.
     *
     * @param  string  $queue
     * @param  DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $job
     * @param  mixed  $data
     * @return mixed
     */
    public function laterOn($queue, $delay, $job, $data = '')
    {
        return $this->later($delay, $job, $data, $queue);
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param  array  $jobs
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return void
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        foreach ((array) $jobs as $job) {
            $this->push($job, $data, $queue);
        }
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param  \Closure|string|object  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @return string
     *
     * @throws InvalidPayloadException
     */
    protected function createPayload($job, $queue, $data = '')
    {
        if ($job instanceof \Closure) {
            $job = CallQueuedClosure::create($job);
        }

        $payload = json_encode($this->createPayloadArray($job, $queue, $data), \JSON_UNESCAPED_UNICODE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidPayloadException(
                'Unable to JSON encode payload. Error code: '.json_last_error()
            );
        }

        return $payload;
    }

    /**
     * Create a payload array from the given job and data.
     *
     * @param  string|object  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @return array
     */
    protected function createPayloadArray($job, $queue, $data = '')
    {
        return is_object($job)
            ? $this->createObjectPayload($job, $queue)
            : $this->createStringPayload($job, $queue, $data);
    }

    /**
     * Create a payload for an object-based queue handler.
     *
     * @param  object  $job
     * @param  string  $queue
     * @return array
     */
    protected function createObjectPayload($job, $queue)
    {
        $payload = $this->withCreatePayloadHooks($queue, [
            'uuid' => (string) StringMethods::uuid(),
            'displayName' => $this->getDisplayName($job),
            'job' => 'Terablaze\Queue\CallQueuedHandler@call',
            'maxTries' => $job->tries ?? null,
            'maxExceptions' => $job->maxExceptions ?? null,
            'failOnTimeout' => $job->failOnTimeout ?? false,
            'backoff' => $this->getJobBackoff($job),
            'timeout' => $job->timeout ?? null,
            'retryUntil' => $this->getJobExpiration($job),
            'data' => [
                'commandName' => $job,
                'command' => $job,
            ],
        ]);

        $command = $this->jobShouldBeEncrypted($job) && $this->container->has(Encrypter::class)
            ? $this->container->get(Encrypter::class)->encrypt(serialize(clone $job))
            : serialize(clone $job);

        return array_merge($payload, [
            'data' => array_merge($payload['data'], [
                'commandName' => get_class($job),
                'command' => $command,
            ]),
        ]);
    }

    /**
     * Get the display name for the given job.
     *
     * @param  object  $job
     * @return string
     */
    protected function getDisplayName($job)
    {
        return method_exists($job, 'displayName')
            ? $job->displayName() : get_class($job);
    }

    /**
     * Get the backoff for an object-based queue handler.
     *
     * @param  mixed  $job
     * @return mixed
     */
    public function getJobBackoff($job)
    {
        if (! method_exists($job, 'backoff') && ! isset($job->backoff)) {
            return;
        }

        if (is_null($backoff = $job->backoff ?? $job->backoff())) {
            return;
        }

        return collect(ArrayMethods::wrap($backoff))
            ->map(function ($backoff) {
                return $backoff instanceof DateTimeInterface
                    ? $this->secondsUntil($backoff) : $backoff;
            })->implode(',');
    }

    /**
     * Get the expiration timestamp for an object-based queue handler.
     *
     * @param  mixed  $job
     * @return mixed
     */
    public function getJobExpiration($job)
    {
        if (! method_exists($job, 'retryUntil') && ! isset($job->retryUntil)) {
            return;
        }

        $expiration = $job->retryUntil ?? $job->retryUntil();

        return $expiration instanceof DateTimeInterface
            ? $expiration->getTimestamp() : $expiration;
    }

    /**
     * Determine if the job should be encrypted.
     *
     * @param  object  $job
     * @return bool
     */
    protected function jobShouldBeEncrypted($job)
    {
        if ($job instanceof ShouldBeEncrypted) {
            return true;
        }

        return isset($job->shouldBeEncrypted) && $job->shouldBeEncrypted;
    }

    /**
     * Create a typical, string based queue payload array.
     *
     * @param  string  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @return array
     */
    protected function createStringPayload($job, $queue, $data)
    {
        return $this->withCreatePayloadHooks($queue, [
            'uuid' => (string) StringMethods::uuid(),
            'displayName' => is_string($job) ? explode('@', $job)[0] : null,
            'job' => $job,
            'maxTries' => null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'data' => $data,
        ]);
    }

    /**
     * Register a callback to be executed when creating job payloads.
     *
     * @param  callable|null  $callback
     * @return void
     */
    public static function createPayloadUsing($callback)
    {
        if (is_null($callback)) {
            static::$createPayloadCallbacks = [];
        } else {
            static::$createPayloadCallbacks[] = $callback;
        }
    }

    /**
     * Create the given payload using any registered payload hooks.
     *
     * @param  string  $queue
     * @param  array  $payload
     * @return array
     */
    protected function withCreatePayloadHooks($queue, array $payload)
    {
        if (! empty(static::$createPayloadCallbacks)) {
            foreach (static::$createPayloadCallbacks as $callback) {
                $payload = array_merge($payload, $callback($this->getConnectionName(), $queue, $payload));
            }
        }

        return $payload;
    }

    /**
     * Enqueue a job using the given callback.
     *
     * @param  \Closure|string|object  $job
     * @param  string  $payload
     * @param  string  $queue
     * @param  DateTimeInterface|\DateInterval|int|null  $delay
     * @param  callable  $callback
     * @return mixed
     */
    protected function enqueueUsing($job, $payload, $queue, $delay, $callback)
    {
        if ($this->shouldDispatchAfterCommit($job) &&
            $this->container->has('db.transactions')) {
            return $this->container->make('db.transactions')->addCallback(
                function () use ($payload, $queue, $delay, $callback, $job) {
                    return tap($callback($payload, $queue, $delay), function ($jobId) use ($job) {
                        $this->raiseJobQueuedEvent($jobId, $job);
                    });
                }
            );
        }

        return tap($callback($payload, $queue, $delay), function ($jobId) use ($job) {
            $this->raiseJobQueuedEvent($jobId, $job);
        });
    }

    /**
     * Determine if the job should be dispatched after all database transactions have committed.
     *
     * @param  \Closure|string|object  $job
     * @return bool
     */
    protected function shouldDispatchAfterCommit($job)
    {
        if (is_object($job) && isset($job->afterCommit)) {
            return $job->afterCommit;
        }

        if (isset($this->dispatchAfterCommit)) {
            return $this->dispatchAfterCommit;
        }

        return false;
    }

    /**
     * Raise the job queued event.
     *
     * @param  string|int|null  $jobId
     * @param  \Closure|string|object  $job
     * @return void
     */
    protected function raiseJobQueuedEvent($jobId, $job)
    {
        if ($this->container->has('events')) {
            $this->container->get(EventDispatcherInterface::class)
                ->dispatch(new JobQueued($this->connectionName, $jobId, $job));
        }
    }

    /**
     * Get the connection name for the queue.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }

    /**
     * Set the connection name for the queue.
     *
     * @param  string  $name
     * @return $this
     */
    public function setConnectionName($name)
    {
        $this->connectionName = $name;

        return $this;
    }

    /**
     * Get the container instance being used by the connection.
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Set the IoC container instance.
     *
     * @param ContainerInterface  $container
     * @return static
     */
    public function setContainer(ContainerInterface $container): QueueInterface
    {
        $this->container = $container;

        return $this;
    }
}
