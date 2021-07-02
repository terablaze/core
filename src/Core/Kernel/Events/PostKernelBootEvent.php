<?php

namespace TeraBlaze\Core\Kernel\Events;

use TeraBlaze\Core\Kernel\HttpKernelInterface;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\EventDispatcher\Event;
use TeraBlaze\HttpBase\Request;

class PostKernelBootEvent extends Event
{
    private HttpKernelInterface $kernel;

    /**
     * PreKernelBootEvent constructor.
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @return KernelInterface
     */
    public function getKernel(): KernelInterface
    {
        return $this->kernel;
    }
}
