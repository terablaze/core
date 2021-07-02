<?php

namespace TeraBlaze\Profiler;

use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Profiler\DebugBar\DebugbarMiddleware;
use TeraBlaze\Profiler\DebugBar\TeraBlazeDebugbar;

class ProfilerParcel extends Parcel implements ParcelInterface
{
    /** @var TeraBlazeDebugbar $debugbar */
    private $debugbar;

    public function boot(): void
    {
        $parsed = loadConfig("profiler");

        if ($parsed->get('debugbar.enabled', false)) {
            if (!$this->container->has(DebugbarMiddleware::class)) {
                /** @var DebugbarMiddleware $debugBarMiddleware */
                $this->container->registerService(
                    DebugbarMiddleware::class,
                    ['class' => DebugbarMiddleware::class]
                );
            }
            /** @var DebugbarMiddleware $debugBarMiddleware */
            $debugBarMiddleware = $this->container->get(DebugbarMiddleware::class);
            $this->getKernel()->registerMiddleWare(DebugbarMiddleware::class);
            $this->debugbar = $debugBarMiddleware->getDebugBar();
            $this->container->registerServiceInstance('debugbar', $this->debugbar);
        }
    }
}
