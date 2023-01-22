<?php

namespace Terablaze\Cache\Lock;


use Carbon\Carbon;
use Terablaze\Cache\Driver\MemoryDriver;

class MemoryLock extends Lock
{
    /**
     * The parent array cache store.
     *
     * @var MemoryDriver
     */
    protected $driver;

    /**
     * Create a new lock instance.
     *
     * @param  MemoryDriver  $driver
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return void
     */
    public function __construct($driver, $name, $seconds, $owner = null)
    {
        parent::__construct($name, $seconds, $owner);

        $this->driver = $driver;
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire()
    {
        $expiration = $this->driver->locks[$this->name]['expiresAt'] ?? Carbon::now()->addSecond();

        if ($this->exists() && $expiration->isFuture()) {
            return false;
        }

        $this->driver->locks[$this->name] = [
            'owner' => $this->owner,
            'expiresAt' => $this->seconds === 0 ? null : Carbon::now()->addSeconds($this->seconds),
        ];

        return true;
    }

    /**
     * Determine if the current lock exists.
     *
     * @return bool
     */
    protected function exists()
    {
        return isset($this->driver->locks[$this->name]);
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release()
    {
        if (! $this->exists()) {
            return false;
        }

        if (! $this->isOwnedByCurrentProcess()) {
            return false;
        }

        $this->forceRelease();

        return true;
    }

    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string
     */
    protected function getCurrentOwner()
    {
        return $this->driver->locks[$this->name]['owner'];
    }

    /**
     * Releases this lock in disregard of ownership.
     *
     * @return void
     */
    public function forceRelease()
    {
        unset($this->driver->locks[$this->name]);
    }
}
