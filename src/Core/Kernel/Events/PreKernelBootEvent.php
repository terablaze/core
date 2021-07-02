<?php

namespace TeraBlaze\Core\Kernel\Events;

use TeraBlaze\Core\Kernel\HttpKernelInterface;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\EventDispatcher\Event;
use TeraBlaze\HttpBase\Request;

class PreKernelBootEvent extends Event
{
    private HttpKernelInterface $kernel;
    private Request $request;

    /**
     * PreKernelBootEvent constructor.
     * @param KernelInterface $kernel
     * @param Request $request
     */
    public function __construct(KernelInterface $kernel, Request $request)
    {
        $this->kernel = $kernel;
        $this->request = $request;
    }

    /**
     * @return KernelInterface
     */
    public function getKernel(): KernelInterface
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
