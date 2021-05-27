<?php

namespace TeraBlaze\Core\Kernel;

interface RebootableInterface
{
    /**
     * Reboots a kernel.
     */
    public function reboot(): void;
}
