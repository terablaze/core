<?php

namespace Terablaze\Container;

interface ContainerAwareInterface
{
    /**
     * Sets the container.
     */
    public function setContainer(ContainerInterface $container = null): self;
}
