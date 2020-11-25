<?php

namespace TeraBlaze\Core\Kernel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\HttpBase\Response;

abstract class Kernel implements KernelInterface
{
    private $booted = false;
    private $debug;
    private $environment;
    private $projectDir;
    private $parcels;
    private $middlewares = [];

    /** @var Container */
    private $container;

    public function __construct(string $environment, bool $debug)
    {
        $this->environment = $environment;
        $this->debug = $debug;

        if ($this->debug) {
            $this->enableDebug();
        }
    }

    /**
     * Handle a Request and turn it in to a response.
     * @param ServerRequestInterface $request
     * @return ResponseInterface|Response
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->boot();

        $handler = new Handler($this->middlewares);

        $this->container->registerServiceInstance('request', $request);

        return $handler->handle($request);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $services = [];
        $servicesConfigFile = $this->getProjectDir() . '/config/services.php';
        if (file_exists($servicesConfigFile)) {
            $services = include_once($servicesConfigFile);
        }
        $parameters = [];
        $parametersConfigFile = $this->getProjectDir() . '/config/parameters.php';
        if (file_exists($parametersConfigFile)) {
            $parameters = include_once($parametersConfigFile);
        }
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
        $middlewares = [];
        $configFile = $this->getProjectDir() . '/config/middlewares.php';
        if (file_exists($configFile)) {
            $middlewares = require $configFile;
        }
        foreach ($middlewares as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                if (class_exists($class)) {
                    $middlewareInstance = new $class();
                    $this->container->registerServiceInstance($class, $middlewareInstance);
                    if (defined("$class::SERVICE_ALIAS")) {
                        $this->container->setAlias($class::SERVICE_ALIAS, $class);
                    }
                }
                $this->middlewares[] = $this->container->get($class);
            }
        }
    }

    public function registerParcels(): void
    {
        $parcels = [];
        $configFile = $this->getProjectDir() . '/config/parcels.php';
        if (file_exists($configFile)) {
            $parcels = require $configFile;
        }
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

    public function enableDebug(): void
    {
        error_reporting(-1);

        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            ini_set('display_errors', 0);
        } elseif (!filter_var(ini_get('log_errors'), \FILTER_VALIDATE_BOOLEAN) || ini_get('error_log')) {
            // CLI - display errors only if they're not already logged to STDERR
            ini_set('display_errors', 1);
        }

        @ini_set('zend.assertions', 1);
        ini_set('assert.active', 1);
        ini_set('assert.warning', 0);
        ini_set('assert.exception', 1);

        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();
    }
}
