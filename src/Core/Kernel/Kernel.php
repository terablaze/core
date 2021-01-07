<?php

namespace TeraBlaze\Core\Kernel;

use Middlewares\Utils\Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Container\Container;
use TeraBlaze\Controller\ErrorController;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Router\Router;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

abstract class Kernel implements KernelInterface
{
    private $booted = false;
    private $debug;
    private $environment;
    private $projectDir;
    private $parcels;
    private $middlewares = [];
    private $exceptionHandler = null;

    /** @var Container */
    private $container;

    public function __construct(string $environment, bool $debug)
    {
        $this->environment = $environment;
        $this->debug = $debug;

        if ($this->debug) {
            $this->enableDebug();
        } else {
            $this->disableDebug();
        }
    }

    /**
     * Handle a Request and turn it in to a response.
     * @param ServerRequestInterface $request
     * @return ResponseInterface|Response
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
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

        if ($configuration) {
            $this->container->registerServiceInstance('configuration', $configuration);
        }
        $constantsFile = $this->getProjectDir() . '/config/constants.php';
        if (file_exists($constantsFile)) {
            include_once($constantsFile);
        }

        $this->registerMiddlewares();
        $this->registerParcels();

        if (class_exists(Run::class) && $this->exceptionHandler != null) {
            $this->container->registerServiceInstance(Run::class, $this->exceptionHandler);
        }

        $this->booted = true;
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

        if (class_exists(Run::class) && class_exists(PrettyPageHandler::class)) {
            $whoops = new Run;
            $whoops->pushHandler(new PrettyPageHandler);
            $whoops->register();
            $this->exceptionHandler = $whoops;
        }
    }

    public function disableDebug(): void
    {
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        set_exception_handler([$this, 'handle500']);
        set_error_handler([$this, 'handle500']);
    }

    public function handle500()
    {
        if (error_reporting()) {
            ob_start();

            $response = (new ErrorController())
                ->setContainer(Container::getContainer())
                ->renderErrorPage(Request::createFromGlobals(), 500)
                ->getBody();

            ob_get_clean();

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo (string)$response;

            flush();
            exit(1);
        } else {
            return false;
        }
    }
}
