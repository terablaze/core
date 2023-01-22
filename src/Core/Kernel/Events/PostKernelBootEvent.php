<?php

namespace Terablaze\Core\Kernel\Events;

use Terablaze\Core\Kernel\HttpKernelInterface;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\EventDispatcher\Event;
use Terablaze\HttpBase\Request;

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
