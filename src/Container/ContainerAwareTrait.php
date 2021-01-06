<?php

namespace TeraBlaze\Container;

use ReflectionException;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;
use TeraBlaze\Events\Events;

trait ContainerAwareTrait
{
    /**
     * @var Container $container
     */
    protected $container;

    /**
     * @param ContainerInterface $container
     * @return $this;
     */
    public function setContainer(ContainerInterface $container)
    {
        Events::fire("terablaze.controller.setContainer.before", array($this->getName()));

        $this->container = $container;

        Events::fire("terablaze.controller.setContainer.after", array($this->getName()));

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (!$this->container instanceof Container) {
            $this->container = Container::getContainer();
        }
        return $this->container->has($key);
    }

    /**
     * @param string $key
     * @return object
     * @throws ReflectionException
     */
    public function get(string $key): object
    {
        if (!$this->container instanceof Container) {
            $this->container = Container::getContainer();
        }
        return $this->container->get($key);
    }

    /**
     * @param string $key
     * @return array|mixed|string
     * @throws ContainerException
     * @throws ParameterNotFoundException
     */
    public function getParameter(string $key)
    {
        if (!$this->container instanceof Container) {
            $this->container = Container::getContainer();
        }
        return $this->container->getParameter($key);
    }
}
