<?php

namespace TeraBlaze\Core\Kernel;

use Exception;
use LogicException;
use Middlewares\Utils\Factory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionException;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Configuration\Exception\ArgumentException;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;
use TeraBlaze\Container\Exception\ServiceNotFoundException;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\ErrorHandler\HandleExceptions;
use TeraBlaze\EventDispatcher\Dispatcher;
use TeraBlaze\EventDispatcher\ListenerProvider;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;

abstract class Kernel implements KernelInterface, RebootableInterface, TerminableInterface
{
    public const TERABLAZE_VERSION = "0.1.0";

    /**
     * @var ParcelInterface[] $parcels
     */
    protected array $parcels = [];

    /**
     * @var MiddlewareInterface[] $middlewares
     */
    protected array $middlewares = [];

    protected bool $booted = false;
    protected bool $debug;
    protected string $environment;
    protected ?string $projectDir = null;

    /** @var Container|null */
    protected ?Container $container = null;

    public static array $internalServices = [
        ListenerProviderInterface::class => ListenerProvider::class,
        EventDispatcherInterface::class => Dispatcher::class,
    ];

    public function __construct(string $environment, bool $debug)
    {
        $this->environment = $environment;
        $this->debug = $debug;
    }

    /**
     * @throws ServiceNotFoundException
     * @throws ArgumentException
     * @throws Exception
     */
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

        $this->container->registerServiceInstance('kernel', $this);
        $this->container->registerServiceInstance('configuration', $configuration);

        $constantsFile = $this->getProjectDir() . '/config/constants.php';
        if (file_exists($constantsFile)) {
            include_once($constantsFile);
        }

        $this->registerInternalServices();

        $this->initializeParcels();
        $this->registerMiddlewares();

        $this->booted = true;
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $this->shutdown();
        $this->boot();
    }

    /**
     * {@inheritdoc}
     * @throws ReflectionException
     */
    public function terminate(Request $request, Response $response): void
    {
        if (false === $this->booted) {
            return;
        }

        if ($this->getHttpKernel() instanceof TerminableInterface) {
            $this->getHttpKernel()->terminate($request, $response);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): void
    {
        if (false === $this->booted) {
            return;
        }

        $this->booted = false;

        foreach ($this->getParcels() as $parcel) {
            $parcel->shutdown();
            $parcel->setContainer(null);
        }

        $this->container = null;
    }

    /**
     * Handle a Request and turn it in to a response.
     * @param Request $request
     * @param bool $catch
     * @return ResponseInterface
     * @throws Exception
     */
    public function handle(Request $request, bool $catch = true): ResponseInterface
    {
        if (class_exists(Factory::class)) {
            Factory::setFactory(new \Middlewares\Utils\FactoryDiscovery(
                \TeraBlaze\HttpBase\Core\Psr7\Factory\Psr17Factory::class,
            ));
        }
        $this->boot();

        return $this->getHttpKernel()->handle($request, $catch);
    }

    /**
     * Gets a HTTP kernel from the container.
     *
     * @return HttpKernelInterface
     * @throws ReflectionException
     */
    protected function getHttpKernel(): HttpKernelInterface
    {
        if (!$this->container->has(HttpKernel::class)) {
            $this->container->registerService(HttpKernel::class, [
                'class' => HttpKernel::class,
                'arguments' => [
                    'middlewares' => $this->middlewares
                ]
            ]);
        }
        return $this->container->get(HttpKernel::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getParcels(): array
    {
        return $this->parcels;
    }

    /**
     * {@inheritdoc}
     */
    public function getParcel(string $name): ParcelInterface
    {
        if (!isset($this->parcels[$name])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Parcel "%s" does not exist or it is not enabled. Maybe you forgot to add it in the ' .
                    '"registerParcels()" method of your "%s.php" file?',
                    $name,
                    'config/parcels'
                )
            );
        }

        return $this->parcels[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * {@inheritdoc}
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function getProjectDir(): string
    {
        if (null === $this->projectDir) {
            $r = new \ReflectionObject($this);

            if (!file_exists($dir = $r->getFileName())) {
                throw new LogicException(
                    sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name)
                );
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
     */
    public function getContainer(): ContainerInterface
    {
        if (!$this->container) {
            throw new LogicException('Cannot retrieve the container from a non-booted kernel.');
        }

        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    public function registerParcels(): iterable
    {
        $parcels = [];
        $configFile = $this->getProjectDir() . '/config/parcels.php';
        if (file_exists($configFile)) {
            $parcels = require $configFile;
        }
        foreach ($parcels as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                if ($this->container->has($class)) {
                    yield $this->container->get($class);
                    continue;
                }
                if (!class_exists($class)) {
                    throw new Exception("Parcel with class name: {$class} not found");
                }
                /** @var ParcelInterface $class */
                yield new $class();
            }
        }
    }

    /**
     * Initializes parcels.
     *
     * @throws LogicException|Exception if two parcels share a common name
     */
    public function initializeParcels(): void
    {
        // init parcels
        $this->parcels = [];
        foreach ($this->registerParcels() as $parcel) {
            $name = $parcel->getName();
            if (isset($this->parcels[$name])) {
                throw new LogicException(sprintf('Trying to register two parcels with the same name "%s".', $name));
            }
            $this->parcels[$name] = $parcel;
//            $parcel->build($this->container);
//            $this->container->registerServiceInstance($name, $parcel);
        }

        foreach ($this->getParcels() as $parcel) {
            $parcel->setContainer($this->container);
            $parcel->boot();
        }
    }

    /**
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     * @throws ServiceNotFoundException
     * @throws Exception
     */
    public function registerMiddlewares(): void
    {
        $middlewares = [];
        $configFile = $this->getProjectDir() . '/config/middlewares.php';
        if (file_exists($configFile)) {
            $middlewares = require $configFile;
        }
        foreach ($middlewares as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                if ($this->container->has($class)) {
                    $this->middlewares[] = $this->container->get($class);
                    continue;
                }
                if (!class_exists($class)) {
                    throw new Exception("Middleware with class name: {$class} not found");
                }
                $this->container->registerService($class, ['class' => $class]);
                $this->middlewares[] = $this->container->get($class);
            }
        }
    }

    public function registerInternalServices(): void
    {
        foreach (static::$internalServices as $name => $internalService) {
            if (!class_exists($internalService)) {
                continue;
            }
            $this->container->registerService($name, ['class' => $internalService]);
        }
    }
}
