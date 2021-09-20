<?php

namespace TeraBlaze\Core\Parcel;

use Psr\EventDispatcher\EventDispatcherInterface;
use TeraBlaze\Core\Console\Application;
use TeraBlaze\Routing\Router;
use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Config\Config;
use TeraBlaze\Config\Exception\InvalidContextException;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\EventDispatcher\Dispatcher;
use TeraBlaze\Routing\RouterInterface;
use TeraBlaze\View\View;

abstract class Parcel implements ParcelInterface
{
    /**
     * @var string|null $name
     */
    protected ?string $name = null;

    /**
     * @var string|null $path
     */
    protected ?string $path = null;

    /**
     * @var string|null $namespace
     */
    protected ?string $namespace = null;

    /**
     * @var Container|ContainerInterface $container
     */
    protected $container;

    /**
     * @var Dispatcher|EventDispatcherInterface $dispatcher
     */
    protected $dispatcher;

    /**
     * {@inheritDoc}
     */
    public function setContainer(?ContainerInterface $container = null): self
    {
        $this->container = $container;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setEventDispatcher(?EventDispatcherInterface $dispatcher = null): self
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    public function getKernel(): KernelInterface
    {
        return $this->container->get('kernel');
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): void
    {
    }

    /**
     * {@inheritdoc}
     *
     * This method can be overridden to register compilation passes,
     * other extensions, ...
     */
    public function build(ContainerInterface $container): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace(): string
    {
        if (null === $this->namespace) {
            $this->parseClassName();
        }

        return $this->namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        if (null === $this->path) {
            $reflected = new \ReflectionObject($this);
            $this->path = \dirname($reflected->getFileName());
        }

        return $this->path;
    }

    /**
     * Returns the parcel name (the class short name).
     */
    final public function getName(): string
    {
        if (null === $this->name) {
            $this->parseClassName();
        }

        return $this->name;
    }

    public function registerCommands(Application $application)
    {
    }

    public function registerRoutes(array $routes): void
    {
        if (!empty($routes) && is_array($routes)) {
            $this->container->make(RouterInterface::class, [
                'class' => Router::class,
                'alias' => 'routing'
            ])->addRoutes($routes);
        }
    }

    /**
     * @param string $context
     * @param string|null $prefix
     * @param string[] $paths
     * @return Config
     * @throws InvalidContextException
     */
    public function loadConfig(string $context, ?string $prefix = null, array $paths = []): Config
    {
        if (empty($paths)) {
            $paths = [$this->getKernel()->getEnvConfigDir(), $this->getKernel()->getConfigDir()];
        }
        $config = new Config(
            $context,
            $prefix ?? $context,
            $paths
        );
        $this->getKernel()->getConfig()->merge($config);
        return $config;
    }

    /**
     * @param string|string[] $path
     * @param string|null $namespace
     */
    public function loadViewFrom($path, ?string $namespace = null): void
    {
        if (null == $namespace) {
            $namespace = $this->getName();
        }
        $path = ArrayMethods::wrap($path);

        foreach ($path as $key => $viewPath) {
            $path[$key] = normalizeDir($this->getPath() . DIRECTORY_SEPARATOR . $viewPath);
        }

        View::addNamespacedPaths($namespace, $path);

        if ($this->getName() !== $namespace) {
            View::addNamespacedPaths($this->getName(), $path);
        }
    }

    private function parseClassName()
    {
        $pos = strrpos(static::class, '\\');
        $this->namespace = false === $pos ? '' : substr(static::class, 0, $pos);
        if (null === $this->name) {
            $this->name = false === $pos ? static::class : substr(static::class, $pos + 1);
        }
    }
}
