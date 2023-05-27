<?php

namespace Terablaze\Cache\Lock;

use Exception;
use Psr\SimpleCache\InvalidArgumentException;
use Terablaze\Cache\Driver\CacheDriverInterface;

class CacheLock extends Lock
{
    /**
     * The cache store implementation.
     *
     * @var CacheDriverInterface
     */
    protected CacheDriverInterface $driver;

    /**
     * Create a new lock instance.
     *
     * @param CacheDriverInterface $driver
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return void
     * @throws Exception
     */
    public function __construct($driver, $name, $seconds, ?string $owner = null)
    {
        parent::__construct($name, $seconds, $owner);

        $this->driver = $driver;
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function acquire()
    {
        if (method_exists($this->driver, 'add') && $this->seconds > 0) {
            return $this->driver->add(
                $this->name, $this->owner, $this->seconds
            );
        }

        if (!is_null($this->driver->get($this->name))) {
            return false;
        }

        return ($this->seconds > 0)
            ? $this->driver->set($this->name, $this->owner, $this->seconds)
            : $this->driver->set($this->name, $this->owner, 0);
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release(): bool
    {
        if ($this->isOwnedByCurrentProcess()) {
            return $this->driver->forget($this->name);
        }

        return false;
    }

    /**
     * Releases this lock regardless of ownership.
     *
     * @return void
     */
    public function forceRelease()
    {
        $this->driver->forget($this->name);
    }

    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return mixed
     */
    protected function getCurrentOwner()
    {
        return $this->driver->get($this->name);
    }
}
