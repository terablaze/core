<?php

namespace Terablaze\Cache\Lock;

use Exception;
use Terablaze\Cache\Exception\LockTimeoutException;
use Terablaze\Support\StringMethods;
use Terablaze\Support\Traits\TimeAware;

abstract class Lock implements LockInterface
{
    use TimeAware;

    /**
     * The name of the lock.
     *
     * @var string
     */
    protected string $name;

    /**
     * The number of seconds the lock should be maintained.
     *
     * @var int
     */
    protected int $seconds;

    /**
     * The scope identifier of this lock.
     *
     * @var string|null
     */
    protected ?string $owner;

    /**
     * The number of milliseconds to wait before re-attempting to acquire a lock while blocking.
     *
     * @var int
     */
    protected int $sleepMilliseconds = 250;

    /**
     * Create a new lock instance.
     *
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return void
     * @throws Exception
     */
    public function __construct(string $name, int $seconds, ?string $owner = null)
    {
        if (is_null($owner)) {
            $owner = StringMethods::random();
        }

        $this->name = $name;
        $this->owner = $owner;
        $this->seconds = $seconds;
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    abstract public function acquire();

    /**
     * Release the lock.
     *
     * @return bool
     */
    abstract public function release(): bool;

    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string
     */
    abstract protected function getCurrentOwner();

    /**
     * Attempt to acquire the lock.
     *
     * @param  callable|null  $callback
     * @return mixed
     */
    public function get($callback = null)
    {
        $result = $this->acquire();

        if ($result && is_callable($callback)) {
            try {
                return $callback();
            } finally {
                $this->release();
            }
        }

        return $result;
    }

    /**
     * Attempt to acquire the lock for the given number of seconds.
     *
     * @param  int  $seconds
     * @param  callable|null  $callback
     * @return mixed
     *
     * @throws LockTimeoutException
     */
    public function block($seconds, $callback = null)
    {
        $starting = $this->currentTime();

        while (! $this->acquire()) {
            usleep($this->sleepMilliseconds * 1000);

            if ($this->currentTime() - $seconds >= $starting) {
                throw new LockTimeoutException;
            }
        }

        if (is_callable($callback)) {
            try {
                return $callback();
            } finally {
                $this->release();
            }
        }

        return true;
    }

    /**
     * Returns the current owner of the lock.
     *
     * @return string
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Determines whether this lock is allowed to release the lock in the driver.
     *
     * @return bool
     */
    protected function isOwnedByCurrentProcess()
    {
        return $this->getCurrentOwner() === $this->owner;
    }

    /**
     * Specify the number of milliseconds to sleep in between blocked lock acquisition attempts.
     *
     * @param  int  $milliseconds
     * @return $this
     */
    public function betweenBlockedAttemptsSleepFor($milliseconds)
    {
        $this->sleepMilliseconds = $milliseconds;

        return $this;
    }
}
