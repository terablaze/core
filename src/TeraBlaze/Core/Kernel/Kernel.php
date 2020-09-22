<?php

namespace TeraBlaze\Core\Kernel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Relay\Relay;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\Core\Parcel\ParcelInterface;

abstract class Kernel implements KernelInterface
{
    private $booted = false;
    private $debug;
    private $environment;
    private $projectDir;
    private $parcels;
    private $middlewares;

    /** @var Container */
    private $container;

    /**
     * Handle a Request and turn it in to a response.
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->boot();

        $relay = new Relay($this->middlewares);

        return $relay->handle($request);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $services = include_once($this->getProjectDir() . '/config/services.php');
        $parameters = include_once($this->getProjectDir() . '/config/parameters.php');
        $this->container = Container::getContainer($services, $parameters);
        $this->container->registerService(static::class, ['class' => static::class]);
        $this->container->setAlias('app.kernel', static::class);
        $this->container->registerServiceInstance(static::class, $this);

        $configuration = new Configuration("phparray");

        $config = [];

        if ($configuration) {
            $this->container->registerServiceInstance('configuration', $configuration);
            $configuration = $configuration->initialize();
            $config = $configuration->parse("config/configuration");
        }
        $this->container->registerParameter('config', $config);

        $this->registerMiddlewares();
        $this->registerParcels();

        $this->booted = true;
    }

    public function getProjectDir(): string
    {
        if (null === $this->projectDir) {
            $r = new \ReflectionObject($this);

            if (!file_exists($dir = $r->getFileName())) {
                throw new \LogicException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
            }

            $dir = $rootDir = \dirname($dir);
            while (!file_exists($dir . '/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    public function getVarDir(): string
    {
        $dir = $this->getProjectDir() . '/var/';
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    public function getVarCacheDir(): string
    {
        $dir = $this->getProjectDir() . '/var/cache/' . $this->environment;
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    public function getVarLogsDir(): string
    {
        $dir = $this->getProjectDir() . '/var/logs/' . $this->environment;
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    public function getVarSessionDir(): string
    {
        $dir = $this->getProjectDir() . '/var/sessions/' . $this->environment;
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    public function getSessionsDir(): string
    {
        return $this->getVarSessionDir();
    }

    /**
     * {@inheritdoc}
     */
    public function getContainer()
    {
        if (!$this->container) {
            throw new \LogicException('Cannot retrieve the container from a non-booted kernel.');
        }

        return $this->container;
    }

    public function reboot()
    {
        $this->shutdown();
        $this->boot();
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        if (false === $this->booted) {
            return;
        }

        $this->booted = false;
        $this->container = null;
        //        $this->requestStackSize = 0;
        //        $this->resetServices = false;
    }

    public function registerMiddlewares(): void
    {
        $middlewares = require $this->getProjectDir() . '/config/middlewares.php';
        foreach ($middlewares as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                if (class_exists($class)) {
                    $this->container->registerService($class, ['class' => $class]);
                }
                $this->middlewares[] = $this->container->get($class);
            }
        }
    }

    public function registerParcels(): void
    {
        $parcels = require $this->getProjectDir() . '/config/parcels.php';
        foreach ($parcels as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                if (!class_exists($class)) {
                    throw new \Exception("Parcel with class name: {$class} not found");
                }

                try {
                    /** @var ParcelInterface $parcel */
                    $parcel = new $class();
                    $parcel->build($this->container);
                    $this->container->registerServiceInstance($class, $parcel);
                } catch (\Exception $e) {
                    throw new \Exception("An error occured while building parcel {$class} with additional message: {$e->getMessage()}");
                }
            }
        }
    }
}
