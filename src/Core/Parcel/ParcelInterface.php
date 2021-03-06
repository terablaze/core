<?php

namespace TeraBlaze\Core\Parcel;

use Psr\EventDispatcher\EventDispatcherInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerAwareInterface;
use TeraBlaze\Container\ContainerAwareTrait;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\EventDispatcher\Dispatcher;

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
}
