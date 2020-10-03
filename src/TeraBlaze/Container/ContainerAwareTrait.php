<?php

namespace Terablaze\Container;

use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Events\Events;

trait ContainerAwareTrait
{
    /**
     * @var Container $container
     */
    protected $container;

    public function setContainer(ContainerInterface $container): void
    {
        Events::fire("terablaze.controller.setContainer.before", array($this->getName()));

        $this->container = $container;

        Events::fire("terablaze.controller.setContainer.after", array($this->getName()));
    }

    public function has(string $key): bool
    {
        if (!$this->container instanceof Container) {
            $this->container = Container::getContainer();
        }
        return $this->container->has($key);
    }

    public function get(string $key): object
    {
        if (!$this->container instanceof Container) {
            $this->container = Container::getContainer();
        }
        return $this->container->get($key);
    }

    public function getParameter(string $key)
    {
        if (!$this->container instanceof Container) {
            $this->container = Container::getContainer();
        }
        return $this->container->getParameter($key);
    }
}