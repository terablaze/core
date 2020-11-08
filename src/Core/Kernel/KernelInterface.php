<?php

namespace TeraBlaze\Core\Kernel;

interface KernelInterface
{
    public function boot();

    public function getProjectDir(): string;
    
    public function registerParcels();
    
    public function registerMiddleWares();
}
