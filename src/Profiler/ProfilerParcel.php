<?php

namespace TeraBlaze\Profiler;

use DebugBar\DataCollector\TimeDataCollector;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TeraBlaze\Config\ConfigInterface;
use TeraBlaze\Core\Kernel\Events\PostKernelBootEvent;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\EventDispatcher\ListenerProvider;
use TeraBlaze\Profiler\DebugBar\DebugbarMiddleware;
use TeraBlaze\Profiler\DebugBar\TeraBlazeDebugbar;

class ProfilerParcel extends Parcel implements ParcelInterface
{
    /** @var TeraBlazeDebugbar $debugbar */
    private $debugbar;

    public function boot(): void
    {
        $config = loadConfig("profiler");

        if ($config->get('profiler.debugbar.enabled', false)) {
            $this->startDebugbar($config);
        }
    }

    protected function startDebugbar(ConfigInterface $config): void
    {
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

        /** @var ListenerProviderInterface $listenerProvider */
        $listenerProvider = $this->container->get(ListenerProviderInterface::class);

        $debugbarRoutes = loadConfigArray(
            'routes',
            null,
            [$this->getPath() . DIRECTORY_SEPARATOR . 'config']
        );

        $this->registerRoutes($debugbarRoutes);

        if ($config->get('profiler.debugbar.collectors.time', true)) {
            $this->debugbar->addCollector(new TimeDataCollector());

            $listenerProvider->addListener(
                PostKernelBootEvent::class,
                function () {
                    $startTime = $this->getKernel()->getInitialRequest()->getServerParam('REQUEST_TIME_FLOAT');
                    if ($startTime) {
                        $this->debugbar['time']->addMeasure('Booting', $startTime, microtime(true));
                    }
                }
            );

            $listenerProvider->addListener(
                PostKernelBootEvent::class,
                function () {
                    $this->debugbar->startMeasure('application', 'Application');
                }
            );
        }
        $this->container->registerServiceInstance('debugbar', $this->debugbar);
    }
}
