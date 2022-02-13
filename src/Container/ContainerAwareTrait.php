<?php

namespace TeraBlaze\Container;

use ReflectionException;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;

trait ContainerAwareTrait
{
    /**
     * @var ContainerInterface|Container $container
     */
    protected $container;

    /**
     * @param ContainerInterface|null $container
     * @return $this;
     */
    public function setContainer(ContainerInterface $container = null): self
    {
        $this->container = $container;

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
    public function get(string $key)
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
