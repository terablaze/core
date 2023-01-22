<?php

namespace Terablaze\Core\Kernel;

interface RebootableInterface
{
    /**
     * Reboots a kernel.
     */
    public function reboot(): void;
}
