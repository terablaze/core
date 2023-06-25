<?php

namespace Terablaze\Core\Parcel;

use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionException;
use Terablaze\Console\Application;
use Terablaze\Core\Exception\ParcelNotLoadedException;
use Terablaze\Core\Scheduling\Schedule;
use Terablaze\Routing\Router;
use Terablaze\Support\ArrayMethods;
use Terablaze\Config\Config;
use Terablaze\Config\Exception\InvalidContextException;
use Terablaze\Container\Container;
use Terablaze\Container\ContainerInterface;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\EventDispatcher\Dispatcher;
use Terablaze\Routing\RouterInterface;
use Terablaze\Translation\TranslationParcel;
use Terablaze\Translation\Translator;
use Terablaze\View\View;

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
     * The paths that should be published.
     *
     * @var array
     */
    public static $publishes = [];

    /**
     * The paths that should be published by group.
     *
     * @var array
     */
    public static $publishGroups = [];

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

    public function schedule(Schedule $schedule)
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
            $paths = [
                $this->getKernel()->getEnvConfigDir(),
                $this->getKernel()->getConfigDir(),
                $this->getPath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR
            ];
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
    public function loadViewsFrom($path, ?string $namespace = null): void
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

    /**
     * Register a translation file namespace.
     *
     * @param string $path
     * @param string|null $namespace
     * @return void
     */
    protected function loadTranslationsFrom(string $path, ?string $namespace = null)
    {
        if (null == $namespace) {
            $namespace = $this->getName();
        }
        $path = normalizeDir($this->getPath() . DIRECTORY_SEPARATOR . $path);
        $this->getTranslator()->addNamespace($namespace, $path);
    }

    /**
     * Register a JSON translation file path.
     *
     * @param string $path
     * @return void
     */
    protected function loadJsonTranslationsFrom(string $path)
    {
        $path = normalizeDir($this->getPath() . DIRECTORY_SEPARATOR . $path);
        $this->getTranslator()->addJsonPath($path);
    }

    private function getTranslator(): Translator
    {
        if (!$this->container->has('translator')) {
            throw new ParcelNotLoadedException(sprintf(
                "Translator parcel not loaded.
                Ensure the translator parcel: %s is loaded in your parcels config file",
                TranslationParcel::class
            ));
        }
        return $this->container->get('translator');
    }

    private function parseClassName()
    {
        $pos = strrpos(static::class, '\\');
        $this->namespace = false === $pos ? '' : substr(static::class, 0, $pos);
        if (null === $this->name) {
            $this->name = false === $pos ? static::class : substr(static::class, $pos + 1);
        }
    }

    /**
     * Register paths to be published by the publish command.
     *
     * @param  array  $paths
     * @param  mixed  $groups
     * @return void
     */
    protected function publishes(array $paths, $groups = null)
    {
        $this->ensurePublishArrayInitialized($class = static::class);

        static::$publishes[$class] = array_merge(static::$publishes[$class], $paths);

        foreach ((array) $groups as $group) {
            $this->addPublishGroup($group, $paths);
        }
    }

    /**
     * Ensure the publish array for the service provider is initialized.
     *
     * @param  string  $class
     * @return void
     */
    protected function ensurePublishArrayInitialized($class)
    {
        if (! array_key_exists($class, static::$publishes)) {
            static::$publishes[$class] = [];
        }
    }

    /**
     * Add a publish group / tag to the service provider.
     *
     * @param  string  $group
     * @param  array  $paths
     * @return void
     */
    protected function addPublishGroup($group, $paths)
    {
        if (! array_key_exists($group, static::$publishGroups)) {
            static::$publishGroups[$group] = [];
        }

        static::$publishGroups[$group] = array_merge(
            static::$publishGroups[$group], $paths
        );
    }

    /**
     * Get the paths to publish.
     *
     * @param  string|null  $provider
     * @param  string|null  $group
     * @return array
     */
    public static function pathsToPublish($provider = null, $group = null)
    {
        if (! is_null($paths = static::pathsForProviderOrGroup($provider, $group))) {
            return $paths;
        }

        return collect(static::$publishes)->reduce(function ($paths, $p) {
            return array_merge($paths, $p);
        }, []);
    }

    /**
     * Get the paths for the provider or group (or both).
     *
     * @param  string|null  $provider
     * @param  string|null  $group
     * @return array
     */
    protected static function pathsForProviderOrGroup($provider, $group)
    {
        if ($provider && $group) {
            return static::pathsForProviderAndGroup($provider, $group);
        } elseif ($group && array_key_exists($group, static::$publishGroups)) {
            return static::$publishGroups[$group];
        } elseif ($provider && array_key_exists($provider, static::$publishes)) {
            return static::$publishes[$provider];
        } elseif ($group || $provider) {
            return [];
        }
    }

    /**
     * Get the paths for the provider and group.
     *
     * @param  string  $provider
     * @param  string  $group
     * @return array
     */
    protected static function pathsForProviderAndGroup($provider, $group)
    {
        if (! empty(static::$publishes[$provider]) && ! empty(static::$publishGroups[$group])) {
            return array_intersect_key(static::$publishes[$provider], static::$publishGroups[$group]);
        }

        return [];
    }

    /**
     * Get the service providers available for publishing.
     *
     * @return array
     */
    public static function publishableProviders()
    {
        return array_keys(static::$publishes);
    }

    /**
     * Get the groups available for publishing.
     *
     * @return array
     */
    public static function publishableGroups()
    {
        return array_keys(static::$publishGroups);
    }
}
