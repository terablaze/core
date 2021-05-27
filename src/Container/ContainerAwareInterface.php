<?php

namespace TeraBlaze\Container;

interface ContainerAwareInterface
{
    /**
     * Sets the container.
     */
    public function setContainer(ContainerInterface $container = null): self;
}
