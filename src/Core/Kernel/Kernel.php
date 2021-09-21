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
use TeraBlaze\Config\Config;
use TeraBlaze\Config\ConfigInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Container\Exception\ServiceNotFoundException;
use TeraBlaze\Core\Kernel\Events\PostKernelBootEvent;
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

    protected Dispatcher $dispatcher;
    protected Request $initialRequest;
    protected string $configDir;
    protected string $envConfigDir;

    protected ConfigInterface $config;

    /**
     * Indicates if the application is running in the console.
     *
     * @var bool|null
     */
    protected $inConsole;

    public function __construct(string $environment, bool $debug)
    {
        $this->environment = $environment;
        $this->debug = $debug;
        $this->configDir = "{$this->getProjectDir()}/config/";
        $this->envConfigDir = "{$this->getProjectDir()}/config/{$this->getEnvironment()}/";
        $this->config = new Config();
    }

    /**
     * @throws ServiceNotFoundException
     * @throws Exception
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        (new HandleExceptions())->bootstrap($this);

        $paths = [$this->getEnvConfigDir(), $this->getConfigDir()];

        try {
            $services = (new Config('services', null, $paths))->toArray();
        } catch (Exception $exceptionS) {
            $services = [];
        }
        try {
            $parameters = (new Config('parameters', null, $paths))->toArray();
        } catch (Exception $exceptionP) {
            $parameters = [];
        }

        $this->container = Container::getContainer($services, $parameters);

        $this->container->registerServiceInstance('kernel', $this);
        $this->container->setAlias(KernelInterface::class, 'kernel');

        if (
            file_exists(
                $envConstantsFile = $this->getProjectDir() . '/config/' . $this->getEnvironment() . '/constants.php'
            )
        ) {
            include_once($envConstantsFile);
        } elseif (file_exists($constantsFile = $this->getProjectDir() . '/config/constants.php')) {
            include_once($constantsFile);
        }

        loadConfig('app');

        $this->bootEventDispatcher();

        $this->registerInternalServices();

        $this->initializeParcels();
        $this->registerMiddleWares();

        $this->booted = true;

        $this->dispatcher->dispatch(new PostKernelBootEvent($this));
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
            $parcel->setEventDispatcher(null);
        }

        $this->container = null;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
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
        $this->initialRequest = $request;
        $this->boot();

//        $this->dispatcher->dispatch()
        return $this->getHttpKernel()->handle($request, $catch);
    }

    public function getInitialRequest(): ?Request
    {
        return $this->initialRequest ?? null;
    }

    /**
     * @throws ReflectionException
     */
    public function getCurrentRequest(): ?Request
    {
        if ($this->container->has('request')) {
            /** @var Request $request */
            $request = $this->container->get('request');
            return $request;
        }
        return $this->getInitialRequest();
    }

    /**
     * Gets a HTTP kernel from the container.
     *
     * @return HttpKernelInterface
     * @throws ReflectionException
     */
    protected function getHttpKernel(): HttpKernelInterface
    {
        return $this->container->make(HttpKernel::class, [
            'class' => HttpKernel::class,
            'arguments' => [
                'middlewares' => $this->middlewares
            ]
        ]);
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
                    static::class
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

    public function inConsole(): bool
    {
        if ($this->inConsole === null) {
            $this->inConsole = in_array(\PHP_SAPI, ['cli', 'phpdbg'], true);
        }

        return $this->inConsole;
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

    /**
     * @param string $configDir
     * @return KernelInterface
     */
    public function setConfigDir(string $configDir): KernelInterface
    {
        $this->configDir = $configDir;
        return $this;
    }

    /**
     * @return string
     */
    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    /**
     * @param string $envConfigDir
     * @return KernelInterface
     */
    public function setEnvConfigDir(string $envConfigDir): KernelInterface
    {
        $this->envConfigDir = $envConfigDir;
        return $this;
    }

    /**
     * @return string
     */
    public function getEnvConfigDir(): string
    {
        return $this->envConfigDir;
    }

    public function getStorageDir(): string
    {
        $dir = $this->getProjectDir() . '/storage/';
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    public function getCacheDir(): string
    {
        $dir = $this->getProjectDir() . '/storage/cache/' . $this->environment . '/';
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    public function getLogsDir(): string
    {
        $dir = $this->getProjectDir() . '/storage/logs/' . $this->environment . '/';
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    public function getSessionsDir(): string
    {
        $dir = $this->getProjectDir() . '/storage/sessions/' . $this->environment . '/';
        if (!is_dir($dir)) {
            makeDir($dir);
        }
        return $dir;
    }

    /**
     * @return ContainerInterface|Container
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
        $parcels = loadConfigArray('parcels');
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
            $parcel->setEventDispatcher($this->dispatcher);
            $parcel->boot();
        }
    }

    /**
     * @throws ReflectionException
     */
    public function registerMiddleWares(): void
    {
        $middlewares = loadConfigArray('middlewares');
        foreach ($middlewares as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                $this->registerMiddleWare($class);
            }
        }
    }

    /**
     * @param string $class
     * @throws ReflectionException
     */
    public function registerMiddleWare(string $class): void
    {
        if ($this->container->has($class)) {
            $this->middlewares[$class] = $this->container->get($class);
            return;
        }
        if (!class_exists($class)) {
            throw new Exception("Middleware with class name: {$class} not found");
        }
        $this->container->registerService($class, ['class' => $class]);
        $this->middlewares[$class] = $this->container->get($class);
    }

    protected function bootEventDispatcher()
    {
        $eventServices = [
            EventDispatcherInterface::class => [
                'class' => Dispatcher::class
            ],
            ListenerProviderInterface::class => [
                'class' => ListenerProvider::class
            ]
        ];
        $this->container->register(['services' => $eventServices]);
        $this->dispatcher = $this->container->get(EventDispatcherInterface::class);
    }

    public function registerInternalServices(): void
    {
        foreach (static::$internalServices as $name => $internalService) {
//            if (!class_exists($internalService)) {
//                continue;
//            }
            if ($this->container->has($name)) {
                $this->container->setAlias($name, $internalService);
                continue;
            }
            if ($this->container->has($internalService)) {
                $this->container->setAlias($internalService, $name);
                continue;
            }
            $this->container->registerService($name, ['class' => $internalService]);
        }
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        if ($this->dispatcher instanceof EventDispatcherInterface) {
            $this->bootEventDispatcher();
        }
        return $this->dispatcher;
    }
}
