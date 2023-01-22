<?php

namespace Terablaze\Profiler;

use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBarException;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionException;
use Terablaze\Config\ConfigInterface;
use Terablaze\Config\Exception\InvalidContextException;
use Terablaze\Console\Application;
use Terablaze\Core\Kernel\Events\PostKernelBootEvent;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Core\Parcel\ParcelInterface;
use Terablaze\EventDispatcher\ListenerProvider;
use Terablaze\Profiler\Console\Command\ClearCommand;
use Terablaze\Profiler\DebugBar\DebugbarMiddleware;
use Terablaze\Profiler\DebugBar\TerablazeDebugbar;

use function in_array;
use function ini_set;

class ProfilerParcel extends Parcel implements ParcelInterface
{
    /** @var TerablazeDebugbar $debugbar */
    private $debugbar;

    public function boot(): void
    {
        $config = $this->loadConfig("profiler");

        if ($config->get('profiler.debugbar.enabled', false)) {
            $this->startDebugbar($config);
        }
    }

    /**
     * @param ConfigInterface $config
     * @throws DebugBarException
     * @throws ReflectionException
     * @throws InvalidContextException
     */
    protected function startDebugbar(ConfigInterface $config): void
    {
        if ($this->getKernel()->inConsole()) {
            return;
        }

        /** @var DebugbarMiddleware $debugBarMiddleware */
        $debugBarMiddleware = $this->container->make(DebugbarMiddleware::class);
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

    public function registerCommands(Application $application)
    {
        $application->add($this->container->make(ClearCommand::class));
    }
}
