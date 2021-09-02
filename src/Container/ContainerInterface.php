<?php

namespace TeraBlaze\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use ReflectionException;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\InvalidArgumentException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;
use TeraBlaze\Container\Exception\ServiceNotFoundException;

/**
 * The container interface. This extends the interface defined by
 * `container-interop` to include methods for retrieving parameters.
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Sets the alias of a registered service
     *
     * @param string $alias The alias to set
     * @param string $name The registered service which the alias is being set
     * @return Container
     * @throws ServiceNotFoundException
     */
    public function setAlias(string $alias, string $name): self;

    /**
     * Static method which returns an instance of the container
     *
     * @param array<string, mixed> $services
     * @param array<string, mixed> $parameters
     * @return ContainerInterface
     */
    public static function getContainer(array $services = [], array $parameters = []): ContainerInterface;

    /**
     * Registers services specified in the $servicesToRegister array
     * by calling the registerService() method (not the instances)
     *
     * @param array<string|int, mixed> $registrant
     * @return Container
     * @throws InvalidArgumentException
     */
    public function register(array $registrant): self;

    /**
     * Registers a single service (not the instance)
     *
     * @param string $key
     * @param array<string, mixed> $service
     * @return Container
     */
    public function registerService(string $key, array $service): self;

    /**
     * Registers a new service instance
     *
     * @param string|object $key
     * @param object|null $service
     * @return Container
     */
    public function registerServiceInstance($key, object $service = null): self;

    /**
     * @param object|string $name
     * @return $this
     */
    public function removeService($name): self;

    /**
     * Registers a parameter
     *
     * @param string $key
     * @param mixed $parameter
     * @return Container
     */
    public function registerParameter(string $key, $parameter): self;

    /**
     * Initialize a route action
     *
     * @param callable $callable The service.
     * @param array<string, mixed> $parameters The call parameters
     *
     * @return false|mixed
     *
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    public function call(callable $callable, array $parameters = []);

    /**
     * @param string $service
     * @param array<int|string, mixed> $definition
     * @param bool $replace
     * @return object
     * @throws ReflectionException
     */
    public function make(string $service, array $definition = [], bool $replace = false): object;

    /**
     * @param string $name
     * @return array<string, mixed>
     */
    public function getServiceRegistration(string $name): array;

    /**
     * Retrieve a parameter from the container.
     *
     * @param string $name The parameter name.
     *
     * @return mixed The parameter.
     *
     * @throws ParameterNotFoundException No entry was found for **this** identifier.
     * @throws ContainerException Error while retrieving the entry.
     */
    public function getParameter(string $name);

    /**
     * Check to see if the container has a parameter.
     *
     * @param string $name The parameter name.
     *
     * @return bool True if the container has the parameter, false otherwise.
     */
    public function hasParameter(string $name): bool;
}
