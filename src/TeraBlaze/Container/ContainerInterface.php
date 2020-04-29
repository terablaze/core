<?php

namespace TeraBlaze\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;

/**
 * The container interface. This extends the interface defined by
 * `container-interop` to include methods for retrieving parameters.
 */
interface ContainerInterface extends PsrContainerInterface
{
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