<?php

namespace TeraBlaze\Core\Parcel;

use Psr\EventDispatcher\EventDispatcherInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\EventDispatcher\Dispatcher;

abstract class Parcel implements ParcelInterface
{
    protected $name;
    protected $extension;
    protected $path;
    private $namespace;

    /**
     * @var ContainerInterface|Container $container
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

    private function parseClassName()
    {
        $pos = strrpos(static::class, '\\');
        $this->namespace = false === $pos ? '' : substr(static::class, 0, $pos);
        if (null === $this->name) {
            $this->name = false === $pos ? static::class : substr(static::class, $pos + 1);
        }
    }
}
