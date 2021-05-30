<?php

namespace TeraBlaze\Profiler;

use DebugBar\DebugBar;
use ReflectionException;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Configuration\Driver\DriverInterface;
use TeraBlaze\Configuration\Driver\Ini;
use TeraBlaze\Configuration\Driver\PHPArray;
use TeraBlaze\Configuration\Exception\ArgumentException as ConfigArgumentException;
use TeraBlaze\Configuration\Exception\Syntax;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Profiler\Debugbar\DataCollectors\MySqliCollector;
use TeraBlaze\Profiler\Debugbar\DebugbarMiddleware;
use TeraBlaze\Ripana\Database\Connector;

class ProfilerParcel extends Parcel implements ParcelInterface
{

    /** @var DebugBar $debugbar */
    private $debugbar;

    private $profilerConfig;

    /** @var DriverInterface|Ini|PHPArray $configDriver */
    private $configDriver;

    public function boot(): void
    {
        if (!$this->container->has('configuration')) {
            return;
        }
        /** @var DriverInterface $configDriver */
        $this->configDriver = $this->container->get('configuration');

        $parsed = $this->configDriver->parse("profiler");

        if (!class_exists(DebugbarMiddleware::class) || !class_exists(DebugBar::class)) {
            return;
        }

        if ($this->container->has(DebugbarMiddleware::class)) {
            /** @var DebugbarMiddleware $debugBarMiddleware */
            $debugBarMiddleware = $this->container->get(DebugbarMiddleware::class);
        } else {
            $debugBarMiddleware = new DebugbarMiddleware();
        }
        $this->debugbar = $debugBarMiddleware->getDebugBar();
        $this->container->registerServiceInstance('debugbar', $this->debugbar);
        $this->profilerConfig = $parsed;
//        $this->setCollectors();
    }

    public function setCollectors()
    {
        $collectors = $this->profilerConfig->debugbar->collectors;
        if ($collectors->{'ripana.query'}) {
            $ripanaConnections = $this->container->getParameter('configArray')['ripana'];
            foreach ($ripanaConnections as $key => $ripanaConnection) {
                /** @var Connector $connection */
                $connection = $this->container->get('ripana.database.connector.' . $key);
                $this->debugbar->addCollector(new MySqliCollector($connection->getQueryLogger(), $key));
            }
        }
    }
}
