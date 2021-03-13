<?php

namespace TeraBlaze\Core\Kernel;

use Exception;
use Middlewares\Utils\Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\ErrorHandler\HandleExceptions;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;

abstract class Kernel implements KernelInterface
{
    public const TERABLAZE_VERSION = "0.1.0";

    protected $booted = false;
    protected $debug;
    protected $environment;
    protected $projectDir;
    protected $parcels;
    protected $middlewares = [];
    protected $currentRequest = null;

    /** @var Container */
    protected $container;

    public function __construct(string $environment, bool $debug)
    {
        $this->environment = $environment;
        $this->debug = $debug;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        (new HandleExceptions())->bootstrap($this);

        $configuration = (new Configuration("phparray", $this))->initialize();

        try {
            $services = $configuration->parseArray('services');
        } catch (Exception $exceptionS) {
            $services = [];
        }
        try {
            $parameters = $configuration->parseArray('parameters');
        } catch (Exception $exceptionP) {
            $parameters = [];
        }
        $this->container = Container::getContainer($services, $parameters);
        $this->container->registerService(static::class, ['class' => static::class]);
        $this->container->setAlias('app.kernel', static::class);
        $this->container->setAlias('kernel', static::class);
        $this->container->registerServiceInstance(static::class, $this);

        if ($configuration) {
            $this->container->registerServiceInstance('configuration', $configuration);
        }

        $constantsFile = $this->getProjectDir() . '/config/constants.php';
        if (file_exists($constantsFile)) {
            include_once($constantsFile);
        }

        $this->registerMiddlewares();
        $this->registerParcels();
        $this->booted = true;
    }

    /**
     * Handle a Request and turn it in to a response.
     * @param ServerRequestInterface $request
     * @return ResponseInterface|Response
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->currentRequest = $request;

        if (class_exists(Factory::class)) {
            Factory::setFactory(new \Middlewares\Utils\FactoryDiscovery(
                \TeraBlaze\HttpBase\Core\Psr7\Factory\Psr17Factory::class,
            ));
        }
        $this->boot();

        $handler = new Handler($this->middlewares);

        $this->container->registerServiceInstance('request', $request);

        return $handler->handle($request);
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * {@inheritdoc}
     */
    public function isDebug()
    {
        return $this->debug;
    }

    public function getCurrentRequest(): Request
    {
        return $this->currentRequest = $this->currentRequest ?? Request::createFromGlobals();
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

    public function getCacheDir(): string
    {
        $dir = $this->getProjectDir() . '/var/cache/' . $this->environment . '/';
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    public function getLogsDir(): string
    {
        $dir = $this->getProjectDir() . '/var/logs/' . $this->environment . '/';
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    public function getSessionsDir(): string
    {
        $dir = $this->getProjectDir() . '/var/sessions/' . $this->environment . '/';
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
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
                    $middleware = $this->container->get($class);
                    $calls = $this->container->getService($class)['calls'] ?? [];
                    if (!empty($calls)) {
                        $this->container->initializeServiceCalls($middleware, $calls);
                    }
                }
                $this->middlewares[] = $middleware;
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
                    throw new \Exception("An error occurred while building parcel {$class} with additional message: {$e->getMessage()}");
                }
            }
        }
    }
}
