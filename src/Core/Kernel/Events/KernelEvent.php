<?php

namespace TeraBlaze\Core\Kernel\Events;

use TeraBlaze\Core\Kernel\HttpKernelInterface;
use TeraBlaze\EventDispatcher\Event;
use TeraBlaze\HttpBase\Request;

/**
 * Base class for events thrown in the HttpKernel component.
 */
class KernelEvent extends Event
{
    private HttpKernelInterface $kernel;
    private Request $request;

    /**
     * KernelEvent constructor.
     * @param HttpKernelInterface $kernel
     * @param Request $request
     */
    public function __construct(HttpKernelInterface $kernel, Request $request)
    {
        $this->kernel = $kernel;
        $this->request = $request;
    }

    /**
     * Returns the kernel in which this event was thrown.
     *
     * @return HttpKernelInterface
     */
    public function getKernel(): HttpKernelInterface
    {
        return $this->kernel;
    }

    /**
     * Returns the request the kernel is currently processing.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
