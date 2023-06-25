<?php

namespace Terablaze\Core\Parcel;

use Psr\EventDispatcher\EventDispatcherInterface;
use Terablaze\Config\Config;
use Terablaze\Config\Exception\InvalidContextException;
use Terablaze\Container\ContainerInterface;
use Terablaze\Console\Application;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\Core\Scheduling\Schedule;
use Terablaze\EventDispatcher\Dispatcher;

interface ParcelInterface
{

    /**
     * @param ContainerInterface|null $container
     * @return $this;
     */
    public function setContainer(?ContainerInterface $container = null): self;

    /**
     * @param Dispatcher|EventDispatcherInterface|null $dispatcher
     * @return $this;
     */
    public function setEventDispatcher(?EventDispatcherInterface $dispatcher = null): self;

    public function getKernel(): KernelInterface;

    /**
     * Boots the Parcel.
     */
    public function boot(): void;

    /**
     * Shutdowns the Parcel.
     */
    public function shutdown(): void;

    /**
     * Builds the parcel.
     *
     * It is only ever called once when the cache is empty.
     */
    public function build(ContainerInterface $container): void;

    /**
     * Returns the parcel name (the class short name).
     *
     * @return string The Parcel name
     */
    public function getName(): string;

    /**
     * Gets the Parcel namespace.
     *
     * @return string The Parcel namespace
     */
    public function getNamespace(): string;

    /**
     * Gets the Parcel directory path.
     *
     * The path should always be returned as a Unix path (with /).
     *
     * @return string The Parcel absolute path
     */
    public function getPath(): string;

    /**
     * Adds parcel routes to global routes
     *
     * @param array<string|int, array> $routes
     */
    public function registerRoutes(array $routes): void;

    public function registerCommands(Application $application);

    public function schedule(Schedule $schedule);

    /**
     * @param string|string[] $path
     * @param string|null $namespace
     */
    public function loadViewsFrom($path, ?string $namespace = null): void;

    /**
     * @param string $context
     * @param string|null $prefix
     * @param string[] $paths
     * @return Config
     * @throws InvalidContextException
     */
    public function loadConfig(string $context, ?string $prefix = null, array $paths = []): Config;
}
