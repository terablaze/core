<?php

namespace TeraBlaze\Core\Kernel;

interface KernelInterface
{
    /**
     * Boots the current kernel.
     */
    public function boot();

    /**
     * Gets the environment.
     *
     * @return string The current environment
     */
    public function getEnvironment();

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool true if debug mode is enabled, false otherwise
     */
    public function isDebug();

    public function getProjectDir(): string;
    
    public function registerParcels();
    
    public function registerMiddleWares();
}
