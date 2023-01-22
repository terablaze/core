<?php

namespace Terablaze\Core\Kernel\Events;

use Terablaze\Core\Kernel\HttpKernelInterface;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\EventDispatcher\Event;
use Terablaze\HttpBase\Request;

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
